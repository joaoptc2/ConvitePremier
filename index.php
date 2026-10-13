<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$token = isset($_GET['c']) ? preg_replace('/[^A-Za-z0-9]/', '', (string)$_GET['c']) : '';
$guest = $token !== '' ? db_row('SELECT * FROM guests WHERE token = ?', [$token]) : null;

// Administrador logado visualizando o convite: não conta como visualização do convidado
$isAdminPreview = false;
if (isset($_COOKIE['convite_sess'])) {
    session_boot();
    $isAdminPreview = !empty($_SESSION['admin']);
}

/** Link desta mesma página, respeitando o formato usado na requisição (/c/TOKEN ou ?c=TOKEN). */
function self_link(string $token, string $extra = ''): string
{
    $path = (string)strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    if (substr(rtrim($path, '/'), -strlen('/c/' . $token)) === '/c/' . $token) {
        return $path . ($extra !== '' ? '?' . $extra : '');
    }
    return $path . '?c=' . rawurlencode($token) . ($extra !== '' ? '&' . $extra : '');
}

$error = null;
$allowChange = setting('allow_change') === '1';

if ($guest && is_post()) {
    $action = $_POST['action'] ?? '';
    $alreadyAnswered = $guest['status'] !== 'pending';

    if (!in_array($action, ['confirm', 'decline'], true)) {
        $error = 'Ação inválida.';
    } elseif (!rsvp_open()) {
        $error = 'O prazo de confirmação foi encerrado.';
    } elseif ($alreadyAnswered && !$allowChange) {
        $error = 'Sua resposta já foi registrada.';
    } else {
        $name = clean_text($_POST['name'] ?? '');
        if ($name === '') {
            $name = $guest['name'];
        }
        $companions = 0;
        $companionNames = [];
        if ($action === 'confirm' && (int)$guest['max_companions'] > 0) {
            $companions = max(0, min((int)$guest['max_companions'], (int)($_POST['companions'] ?? 0)));
            $rawNames = is_array($_POST['companion_names'] ?? null) ? $_POST['companion_names'] : [];
            for ($i = 0; $i < $companions; $i++) {
                $n = clean_text($rawNames[$i] ?? '', 120);
                $companionNames[] = $n !== '' ? $n : 'Acompanhante ' . ($i + 1);
            }
        }
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1000);
        $status = $action === 'confirm' ? 'confirmed' : 'declined';

        db_query(
            'UPDATE guests SET status = ?, response_name = ?, companions = ?, companion_names = ?, message = ?, responded_at = ?, updated_at = ? WHERE id = ?',
            [$status, $name, $companions, $companionNames ? implode("\n", $companionNames) : null, $message !== '' ? $message : null, now(), now(), $guest['id']]
        );
        log_activity((int)$guest['id'], $status, $companions > 0 ? "+{$companions} acompanhante(s)" : null);
        redirect(self_link($guest['token']) . '#resposta');
    }
}

if ($guest && !is_post() && !$isAdminPreview) {
    $isFirst = empty($guest['first_viewed_at']);
    db_query(
        'UPDATE guests SET view_count = view_count + 1, first_viewed_at = COALESCE(first_viewed_at, ?), last_viewed_at = ? WHERE id = ?',
        [now(), now(), $guest['id']]
    );
    if ($isFirst) {
        log_activity((int)$guest['id'], 'viewed');
    }
}

$editing = $guest && isset($_GET['editar']) && $allowChange && rsvp_open();
[$headline1, $headline2, $question] = headline_parts($guest['treatment'] ?? 'm');

require APP_DIR . '/views/invite.php';
