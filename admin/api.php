<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$guest = db_row('SELECT * FROM guests WHERE id = ?', [$id]);
if (!$guest) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'Convidado não encontrado']));
}

switch ($_POST['action'] ?? '') {
    case 'mark_sent':
        if (!$guest['sent_at']) {
            db_query('UPDATE guests SET sent_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $id]);
        }
        echo json_encode(['ok' => true, 'sent_at' => fmt_datetime($guest['sent_at'] ?: now())]);
        break;

    case 'toggle_checkin':
        $value = $guest['checked_in_at'] ? null : now();
        db_query('UPDATE guests SET checked_in_at = ?, updated_at = ? WHERE id = ?', [$value, now(), $id]);
        if ($value) {
            log_activity($id, 'checkin');
        }
        echo json_encode(['ok' => true, 'checked_in' => $value !== null, 'time' => $value ? date('H:i', strtotime($value)) : null]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ação inválida']);
}
