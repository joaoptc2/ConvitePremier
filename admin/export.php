<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$out = fopen('php://output', 'w');
$csv = static function (array $row) use ($out): void {
    fputcsv($out, $row, ';', '"', '\\');
};

// Modelo vazio para importação
if (isset($_GET['modelo'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modelo-convidados.csv"');
    fwrite($out, "\xEF\xBB\xBF");
    $csv(['nome', 'telefone', 'email', 'grupo', 'acompanhantes', 'tratamento']);
    $csv(['Maria Silva', '(31) 99999-0001', 'maria@exemplo.com', 'Diretoria', '1', 'convidada']);
    $csv(['João e Ana Souza', '(31) 99999-0002', '', 'Parceiros', '0', 'plural']);
    $csv(['Carlos Pereira', '31999990003', '', 'Médicos', '0', 'convidado']);
    exit;
}

$where = [];
$params = [];
$status = (string)($_GET['status'] ?? '');
if (in_array($status, ['pending', 'confirmed', 'declined'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if (($_GET['grupo'] ?? '') !== '') {
    $where[] = 'group_name = ?';
    $params[] = (string)$_GET['grupo'];
}
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR response_name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$envio = (string)($_GET['envio'] ?? '');
if ($envio === 'nao') {
    $where[] = 'sent_at IS NULL';
} elseif ($envio === 'sim') {
    $where[] = 'sent_at IS NOT NULL';
} elseif ($envio === 'naoviu') {
    $where[] = 'sent_at IS NOT NULL AND first_viewed_at IS NULL';
}

$guests = db_all('SELECT * FROM guests' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY name', $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="convidados-' . date('Y-m-d-His') . '.csv"');
fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8

$csv([
    'Nome', 'Tratamento', 'Telefone', 'E-mail', 'Grupo', 'Acompanhantes permitidos', 'Resposta', 'Total de pessoas',
    'Nome informado', 'Acompanhantes confirmados', 'Nomes dos acompanhantes', 'Recado', 'Respondido em',
    'Enviado em', 'Visualizações', 'Primeira visualização', 'Check-in', 'Observações', 'Link',
]);
foreach ($guests as $g) {
    $csv([
        $g['name'],
        treatment_label($g['treatment']),
        format_phone($g['phone']),
        $g['email'],
        $g['group_name'],
        (int)$g['max_companions'],
        status_label($g['status']),
        guest_people($g),
        $g['response_name'],
        (int)$g['companions'],
        str_replace("\n", ', ', (string)$g['companion_names']),
        $g['message'],
        $g['responded_at'] ? fmt_datetime($g['responded_at']) : '',
        $g['sent_at'] ? fmt_datetime($g['sent_at']) : '',
        (int)$g['view_count'],
        $g['first_viewed_at'] ? fmt_datetime($g['first_viewed_at']) : '',
        $g['checked_in_at'] ? fmt_datetime($g['checked_in_at']) : '',
        $g['notes'],
        guest_link($g),
    ]);
}
