<?php
declare(strict_types=1);

/** Valores padrão do evento (podem ser alterados em Admin > Configurações). */
function event_defaults(): array
{
    return [
        'event_name' => 'Hospital Premier · 10 anos',
        'tagline' => 'Uma década de cuidado',
        'event_date' => '2026-11-07',
        'time_start' => '13:00',
        'time_end' => '19:00',
        'venue_name' => '',
        'address' => 'R. José Laporte Neto, 73 · Estoril, Belo Horizonte – MG',
        'map_url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('R. José Laporte Neto, 73, Estoril, Belo Horizonte - MG'),
        'dress_code' => 'Casual',
        'rsvp_deadline' => '',
        'allow_change' => '1',
        'ask_message' => '1',
        'whatsapp_message' => "Olá, {primeiro_nome}! 💙\n\nO Hospital Premier completa 10 anos e queremos {voce} com a gente para celebrar essa década de cuidado.\n\n📅 {data}\n🕐 {horario}\n\nAcesse seu convite e confirme sua presença:\n{link}",
    ];
}

const PT_MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const PT_WEEKDAYS_SHORT = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];

function event_date_obj(): DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)setting('event_date'));
    return $d ?: new DateTimeImmutable('today');
}

function event_date_long(): string
{
    $d = event_date_obj();
    return $d->format('d') . ' de ' . PT_MONTHS[(int)$d->format('n')] . ' de ' . $d->format('Y');
}

function fmt_hour(string $hhmm): string
{
    [$h, $m] = array_pad(explode(':', $hhmm), 2, '00');
    $h = (int)$h;
    return $m === '00' || $m === '' ? "{$h}h" : "{$h}h{$m}";
}

function event_time_range(): string
{
    $start = (string)setting('time_start');
    $end = (string)setting('time_end');
    return $end !== '' ? fmt_hour($start) . ' às ' . fmt_hour($end) : fmt_hour($start);
}

/** Dia anterior, dia do evento e dia seguinte para a faixa do calendário. */
function event_day_strip(): array
{
    $d = event_date_obj();
    $days = [];
    foreach ([-1, 0, 1] as $offset) {
        $day = $d->modify(($offset >= 0 ? '+' : '') . $offset . ' day');
        $days[] = [
            'weekday' => PT_WEEKDAYS_SHORT[(int)$day->format('w')],
            'day' => (int)$day->format('j'),
            'active' => $offset === 0,
        ];
    }
    return $days;
}

function rsvp_open(): bool
{
    $deadline = (string)setting('rsvp_deadline');
    if ($deadline === '') {
        return true;
    }
    return time() <= strtotime($deadline . ' 23:59:59');
}

function rsvp_deadline_text(): string
{
    $deadline = (string)setting('rsvp_deadline');
    if ($deadline === '' || !($ts = strtotime($deadline))) {
        return '';
    }
    return date('d', $ts) . ' de ' . PT_MONTHS[(int)date('n', $ts)];
}

/** Textos que variam conforme o tratamento do convidado (m, f, p). */
function headline_parts(string $treatment): array
{
    switch ($treatment) {
        case 'f':
            return ['Você é nossa', 'convidada', 'Você vem?'];
        case 'p':
            return ['Vocês são nossos', 'convidados', 'Vocês vêm?'];
        default:
            return ['Você é nosso', 'convidado', 'Você vem?'];
    }
}

function event_ics(): string
{
    $d = event_date_obj();
    $start = (string)setting('time_start') ?: '00:00';
    $end = (string)setting('time_end') ?: $start;
    $tz = new DateTimeZone((string)config('timezone', 'America/Sao_Paulo'));
    $dtStart = new DateTime($d->format('Y-m-d') . ' ' . $start, $tz);
    $dtEnd = new DateTime($d->format('Y-m-d') . ' ' . $end, $tz);
    $utc = new DateTimeZone('UTC');
    $esc = static function (string $s): string {
        return str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
    };
    $location = trim(((string)setting('venue_name') !== '' ? setting('venue_name') . ' · ' : '') . setting('address'));
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Convite Premier//PT-BR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . md5((string)config('base_url') . $d->format('Ymd')) . '@convite',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $dtStart->setTimezone($utc)->format('Ymd\THis\Z'),
        'DTEND:' . $dtEnd->setTimezone($utc)->format('Ymd\THis\Z'),
        'SUMMARY:' . $esc((string)setting('event_name')),
        'LOCATION:' . $esc($location),
        'DESCRIPTION:' . $esc((string)setting('tagline') . ' — Traje: ' . setting('dress_code')),
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", $lines) . "\r\n";
}

function google_calendar_link(): string
{
    $d = event_date_obj();
    $tz = new DateTimeZone((string)config('timezone', 'America/Sao_Paulo'));
    $utc = new DateTimeZone('UTC');
    $start = (new DateTime($d->format('Y-m-d') . ' ' . (setting('time_start') ?: '00:00'), $tz))->setTimezone($utc);
    $end = (new DateTime($d->format('Y-m-d') . ' ' . (setting('time_end') ?: setting('time_start') ?: '00:00'), $tz))->setTimezone($utc);
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => setting('event_name'),
        'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
        'location' => setting('address'),
        'details' => setting('tagline'),
    ]);
}
