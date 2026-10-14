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
const PT_WEEKDAYS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

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

/** Ex.: "Sábado, 7 de novembro de 2026" */
function event_date_full(): string
{
    $d = event_date_obj();
    return PT_WEEKDAYS[(int)$d->format('w')] . ', ' . (int)$d->format('j') . ' de ' . PT_MONTHS[(int)$d->format('n')] . ' de ' . $d->format('Y');
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
