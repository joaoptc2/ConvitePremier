<?php
declare(strict_types=1);

/*
 * Fotos do evento para o convidado.
 *   ?c=TOKEN&acao=avaliar  registra o clique e redireciona para a avaliação no Google
 *   ?c=TOKEN&acao=liberar  (POST) devolve a lista de fotos, depois do tempo de espera
 */
require __DIR__ . '/app/bootstrap.php';

$token = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? ''));
$guest = $token !== '' ? db_row('SELECT * FROM guests WHERE token = ?', [$token]) : null;
$action = (string)($_GET['acao'] ?? '');

$isAdminPreview = false;
if (isset($_COOKIE['convite_sess'])) {
    session_boot();
    $isAdminPreview = !empty($_SESSION['admin']);
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$guest || !photos_enabled()) {
    if ($action === 'liberar') {
        json_out(['ok' => false, 'error' => 'Link inválido.'], 404);
    }
    redirect(base_path() . '/');
}

if ($action === 'avaliar') {
    $url = trim((string)setting('review_url'));
    if (!$isAdminPreview) {
        if (empty($guest['review_clicked_at'])) {
            log_activity((int)$guest['id'], 'review');
        }
        db_query('UPDATE guests SET review_clicked_at = COALESCE(review_clicked_at, ?) WHERE id = ?', [now(), $guest['id']]);
    }
    redirect(preg_match('#^https://#i', $url) ? $url : base_path() . '/');
}

if ($action === 'liberar' && is_post()) {
    if (empty($guest['photos_unlocked_at']) && !$isAdminPreview) {
        $seconds = max(0, (int)setting('photos_seconds'));
        $gate = $guest['photos_gate_at'] ? strtotime($guest['photos_gate_at']) : false;
        $elapsed = $gate ? time() - $gate : -1;
        // 2 s de tolerância para atraso de rede
        if ($gate === false || $elapsed < $seconds - 2) {
            json_out(['ok' => false, 'wait' => max(1, $seconds - max(0, $elapsed)), 'error' => 'Aguarde a contagem terminar.'], 409);
        }
        db_query('UPDATE guests SET photos_unlocked_at = ? WHERE id = ?', [now(), $guest['id']]);
        log_activity((int)$guest['id'], 'photos');
    }

    $list = [];
    foreach (photos_all() as $i => $p) {
        $list[] = [
            't' => photo_url($p, 'thumb'),
            'w' => photo_url($p, 'web'),
            'o' => photo_url($p, 'orig'),
            'n' => photo_download_name($p, $i + 1),
            'r' => $p['width'] > 0 ? round($p['width'] / max(1, $p['height']), 3) : 1,
        ];
    }
    json_out(['ok' => true, 'photos' => $list]);
}

redirect(base_path() . '/');
