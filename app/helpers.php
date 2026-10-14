<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ------------------------------------------------------------------ */
/* Configurações (tabela settings)                                    */
/* ------------------------------------------------------------------ */

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $all = settings_all();
    if (array_key_exists($key, $all)) {
        return $all[$key];
    }
    return $default ?? (event_defaults()[$key] ?? null);
}

function setting_set(string $key, ?string $value): void
{
    $exists = db_value('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
    if ((int)$exists > 0) {
        db_query('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
    } else {
        db_query('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    }
}

/* ------------------------------------------------------------------ */
/* Sessão, CSRF e autenticação                                        */
/* ------------------------------------------------------------------ */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('convite_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Sessão expirada. Volte, recarregue a página e tente novamente.');
    }
}

function is_logged_in(): bool
{
    session_boot();
    return !empty($_SESSION['admin']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function login_throttled(string $ip): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    db_query('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $count = (int)db_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?', [$ip, $since]);
    return $count >= 8;
}

function flash(string $message, string $type = 'ok'): void
{
    session_boot();
    $_SESSION['flash'][] = ['msg' => $message, 'type' => $type];
}

function flashes(): array
{
    session_boot();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------ */
/* Convidados                                                         */
/* ------------------------------------------------------------------ */

function generate_token(int $length = 10): string
{
    // Sem caracteres ambíguos (0/O, 1/l/I)
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    do {
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }
    } while ((int)db_value('SELECT COUNT(*) FROM guests WHERE token = ?', [$token]) > 0);
    return $token;
}

function base_url(string $path = ''): string
{
    return rtrim((string)config('base_url', ''), '/') . '/' . ltrim($path, '/');
}

function guest_link(array $guest): string
{
    if (config('pretty_urls', true)) {
        return base_url('c/' . $guest['token']);
    }
    return base_url('?c=' . $guest['token']);
}

function normalize_phone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    $digits = ltrim($digits, '0');
    // Número brasileiro sem DDI (DDD + 8/9 dígitos)
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }
    return $digits;
}

function format_phone(?string $phone): string
{
    $d = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($d) === 13 && strpos($d, '55') === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string)$phone;
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] ?? $name;
}

function invite_message(array $guest): string
{
    $tpl = (string)setting('whatsapp_message');
    $t = $guest['treatment'] ?? 'm';
    return strtr($tpl, [
        '{voce}' => $t === 'p' ? 'vocês' : 'você',
        '{convidado}' => ['m' => 'nosso convidado', 'f' => 'nossa convidada', 'p' => 'nossos convidados'][$t] ?? 'nosso convidado',
        '{nome}' => $guest['name'],
        '{primeiro_nome}' => first_name($guest['name']),
        '{link}' => guest_link($guest),
        '{data}' => event_date_long(),
        '{horario}' => event_time_range(),
    ]);
}

function whatsapp_link(array $guest): string
{
    // Usa api.whatsapp.com/send em vez de wa.me: o redirecionamento do wa.me
    // corrompe emojis de 4 bytes (aparecem como "?" ou "�" na mensagem).
    $params = ['text' => invite_message($guest)];
    $phone = normalize_phone($guest['phone'] ?? '');
    if ($phone !== '') {
        $params = ['phone' => $phone] + $params;
    }
    return 'https://api.whatsapp.com/send?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function status_label(string $status): string
{
    return [
        'pending' => 'Pendente',
        'confirmed' => 'Confirmado',
        'declined' => 'Não vai',
    ][$status] ?? $status;
}

function treatment_label(string $t): string
{
    return ['m' => 'Convidado', 'f' => 'Convidada', 'p' => 'Convidados (plural)'][$t] ?? 'Convidado';
}

function log_activity(?int $guestId, string $action, ?string $detail = null): void
{
    db_query(
        'INSERT INTO activity (guest_id, action, detail, ip, created_at) VALUES (?, ?, ?, ?, ?)',
        [$guestId, $action, $detail !== null ? mb_substr($detail, 0, 250) : null, client_ip(), now()]
    );
}

function fmt_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function clean_text(?string $s, int $max = 190): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    return mb_substr($s, 0, $max);
}

function guest_people(array $g): int
{
    return $g['status'] === 'confirmed' ? 1 + (int)$g['companions'] : 0;
}

function guest_stats(): array
{
    $row = db_row("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) AS declined,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 + companions ELSE 0 END) AS people,
        SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN first_viewed_at IS NOT NULL THEN 1 ELSE 0 END) AS viewed,
        SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in,
        SUM(1 + max_companions) AS capacity
        FROM guests");
    return array_map('intval', $row ?: []);
}

/** Caminho base do site (ex.: "" ou "/convite"), usado para assets com caminho absoluto. */
function base_path(): string
{
    $path = (string)parse_url((string)config('base_url', ''), PHP_URL_PATH);
    return rtrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return base_path() . '/' . ltrim($path, '/') . $v;
}
