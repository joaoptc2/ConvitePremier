<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

/* ---------- Ações em lote ---------- */
if (is_post()) {
    csrf_check();
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $bulk = (string)($_POST['bulk'] ?? '');
    if (!$ids) {
        flash('Selecione ao menos um convidado.', 'error');
    } else {
        $in = implode(',', array_fill(0, count($ids), '?'));
        switch ($bulk) {
            case 'sent':
                db_query("UPDATE guests SET sent_at = COALESCE(sent_at, ?), updated_at = ? WHERE id IN ($in)", array_merge([now(), now()], $ids));
                flash(count($ids) . ' convite(s) marcados como enviados.');
                break;
            case 'unsent':
                db_query("UPDATE guests SET sent_at = NULL, updated_at = ? WHERE id IN ($in)", array_merge([now()], $ids));
                flash(count($ids) . ' convite(s) marcados como não enviados.');
                break;
            case 'reset':
                db_query("UPDATE guests SET status = 'pending', companions = 0, companion_names = NULL, responded_at = NULL, updated_at = ? WHERE id IN ($in)", array_merge([now()], $ids));
                flash(count($ids) . ' resposta(s) redefinidas para pendente.');
                break;
            case 'delete':
                db_query("DELETE FROM activity WHERE guest_id IN ($in)", $ids);
                db_query("DELETE FROM guests WHERE id IN ($in)", $ids);
                flash(count($ids) . ' convidado(s) excluídos.');
                break;
            default:
                flash('Escolha uma ação.', 'error');
        }
    }
    redirect('index.php' . (!empty($_POST['return']) ? '?' . preg_replace('/[^\w=&%+.\-]/', '', (string)$_POST['return']) : ''));
}

/* ---------- Filtros ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$group = (string)($_GET['grupo'] ?? '');
$sent = (string)($_GET['envio'] ?? '');
$sort = (string)($_GET['ordem'] ?? 'nome');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR response_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['pending', 'confirmed', 'declined'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($group !== '') {
    $where[] = 'group_name = ?';
    $params[] = $group;
}
if ($sent === 'nao') {
    $where[] = 'sent_at IS NULL';
} elseif ($sent === 'sim') {
    $where[] = 'sent_at IS NOT NULL';
} elseif ($sent === 'naoviu') {
    $where[] = 'sent_at IS NOT NULL AND first_viewed_at IS NULL';
}
$orderBy = [
    'nome' => 'name ASC',
    'resposta' => 'CASE WHEN responded_at IS NULL THEN 1 ELSE 0 END, responded_at DESC',
    'recentes' => 'id DESC',
][$sort] ?? 'name ASC';

$guests = db_all('SELECT * FROM guests' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $orderBy, $params);
$stats = guest_stats();
$groups = existing_groups();
$filtered = $where !== [];
$returnQuery = http_build_query(array_filter(['q' => $q, 'status' => $status, 'grupo' => $group, 'envio' => $sent, 'ordem' => $sort !== 'nome' ? $sort : '']));

$activity = db_all('SELECT a.*, g.name FROM activity a LEFT JOIN guests g ON g.id = a.guest_id ORDER BY a.id DESC LIMIT 12');
$activityText = [
    'viewed' => 'abriu o convite',
    'confirmed' => 'confirmou presença',
    'declined' => 'informou que não poderá ir',
    'manual' => 'teve a resposta alterada pela equipe',
    'checkin' => 'fez check-in',
];

$answered = $stats['confirmed'] + $stats['declined'];
$pct = $stats['total'] > 0 ? round($answered / $stats['total'] * 100) : 0;

admin_header('Convidados', 'index');
?>
<section class="stats">
  <div class="stat"><span class="stat-num"><?= $stats['total'] ?></span><span class="stat-label">Convidados</span></div>
  <div class="stat stat-ok"><span class="stat-num"><?= $stats['people'] ?></span><span class="stat-label">Pessoas confirmadas</span><small><?= $stats['confirmed'] ?> convite(s)</small></div>
  <div class="stat stat-no"><span class="stat-num"><?= $stats['declined'] ?></span><span class="stat-label">Não vão</span></div>
  <div class="stat stat-wait"><span class="stat-num"><?= $stats['pending'] ?></span><span class="stat-label">Pendentes</span></div>
  <div class="stat"><span class="stat-num"><?= $stats['sent'] ?></span><span class="stat-label">Enviados</span><small><?= $stats['total'] - $stats['sent'] ?> a enviar</small></div>
  <div class="stat"><span class="stat-num"><?= $stats['viewed'] ?></span><span class="stat-label">Abriram o link</span></div>
</section>

<div class="progress" title="<?= $pct ?>% responderam">
  <?php if ($stats['total'] > 0): ?>
    <span class="p-ok" style="width:<?= $stats['confirmed'] / $stats['total'] * 100 ?>%"></span>
    <span class="p-no" style="width:<?= $stats['declined'] / $stats['total'] * 100 ?>%"></span>
  <?php endif; ?>
</div>
<p class="muted small"><?= $pct ?>% dos convidados já responderam.</p>

<div class="layout">
  <section class="panel main-panel">
    <div class="panel-head">
      <h1>Lista de convidados</h1>
      <div class="actions">
        <a class="btn btn-primary" href="guest.php">+ Novo convidado</a>
        <a class="btn" href="export.php<?= $returnQuery ? '?' . e($returnQuery) : '' ?>">Exportar CSV</a>
      </div>
    </div>

    <form class="filters" method="get">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar nome, telefone ou e-mail">
      <select name="status">
        <option value="">Todas as respostas</option>
        <?php foreach (['pending' => 'Pendentes', 'confirmed' => 'Confirmados', 'declined' => 'Não vão'] as $k => $v): ?>
          <option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <select name="envio">
        <option value="">Envio: todos</option>
        <option value="nao"<?= $sent === 'nao' ? ' selected' : '' ?>>Não enviados</option>
        <option value="sim"<?= $sent === 'sim' ? ' selected' : '' ?>>Enviados</option>
        <option value="naoviu"<?= $sent === 'naoviu' ? ' selected' : '' ?>>Enviados e não abertos</option>
      </select>
      <?php if ($groups): ?>
        <select name="grupo">
          <option value="">Todos os grupos</option>
          <?php foreach ($groups as $gname): ?>
            <option value="<?= e($gname) ?>"<?= $group === $gname ? ' selected' : '' ?>><?= e($gname) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <select name="ordem">
        <option value="nome"<?= $sort === 'nome' ? ' selected' : '' ?>>Ordem: nome</option>
        <option value="resposta"<?= $sort === 'resposta' ? ' selected' : '' ?>>Respostas recentes</option>
        <option value="recentes"<?= $sort === 'recentes' ? ' selected' : '' ?>>Cadastrados recentes</option>
      </select>
      <button class="btn" type="submit">Filtrar</button>
      <?php if ($filtered || $sort !== 'nome'): ?><a class="link" href="index.php">Limpar</a><?php endif; ?>
    </form>

    <?php if (!$guests): ?>
      <div class="empty">
        <?php if ($filtered): ?>
          <p>Nenhum convidado encontrado com esses filtros.</p>
        <?php else: ?>
          <p>Nenhum convidado cadastrado ainda.</p>
          <p><a class="btn btn-primary" href="guest.php">Adicionar convidado</a> <a class="btn" href="import.php">Importar lista</a></p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <form method="post" id="bulk-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
        <div class="bulk">
          <label class="check"><input type="checkbox" data-check-all> <span><?= count($guests) ?> na lista</span></label>
          <select name="bulk">
            <option value="">Ações com selecionados…</option>
            <option value="sent">Marcar como enviado</option>
            <option value="unsent">Marcar como não enviado</option>
            <option value="reset">Redefinir resposta (pendente)</option>
            <option value="delete">Excluir</option>
          </select>
          <button class="btn btn-sm" type="submit" data-bulk-submit>Aplicar</button>
        </div>

        <table class="guests">
          <thead>
            <tr>
              <th class="c-check"></th>
              <th>Convidado</th>
              <th>Resposta</th>
              <th>Envio</th>
              <th class="c-actions">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($guests as $g): $link = guest_link($g); ?>
            <tr data-id="<?= (int)$g['id'] ?>">
              <td class="c-check"><input type="checkbox" name="ids[]" value="<?= (int)$g['id'] ?>" aria-label="Selecionar <?= e($g['name']) ?>"></td>
              <td class="c-name">
                <a class="name" href="guest.php?id=<?= (int)$g['id'] ?>"><?= e($g['name']) ?></a>
                <div class="meta">
                  <?php if ($g['group_name']): ?><span class="chip"><?= e($g['group_name']) ?></span><?php endif; ?>
                  <?php if ($g['phone']): ?><span><?= e(format_phone($g['phone'])) ?></span><?php endif; ?>
                  <?php if ((int)$g['max_companions'] > 0): ?><span>até +<?= (int)$g['max_companions'] ?></span><?php endif; ?>
                </div>
              </td>
              <td class="c-status">
                <?= status_badge($g) ?>
                <?php if ($g['responded_at']): ?><div class="meta"><?= e(fmt_datetime($g['responded_at'])) ?></div><?php endif; ?>
                <?php if ($g['message']): ?><div class="msg" title="<?= e($g['message']) ?>">“<?= e(mb_strimwidth($g['message'], 0, 60, '…')) ?>”</div><?php endif; ?>
              </td>
              <td class="c-sent">
                <?php if ($g['sent_at']): ?>
                  <span class="dot dot-ok" data-sent-label>Enviado</span>
                <?php else: ?>
                  <span class="dot" data-sent-label>Não enviado</span>
                <?php endif; ?>
                <div class="meta">
                  <?= (int)$g['view_count'] > 0 ? 'Aberto ' . (int)$g['view_count'] . 'x' : 'Não aberto' ?>
                </div>
              </td>
              <td class="c-actions">
                <a class="icon-btn wa" href="<?= e(whatsapp_link($g)) ?>" target="_blank" rel="noopener" data-mark-sent title="Enviar pelo WhatsApp">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.4-.2Z"/></svg>
                  <span>WhatsApp</span>
                </a>
                <button type="button" class="icon-btn" data-copy="<?= e($link) ?>" data-copy-message="<?= e(invite_message($g)) ?>" data-mark-sent title="Copiar link">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.6 13.4a1 1 0 0 1 0-1.4l3.5-3.5a3 3 0 1 1 4.2 4.2l-2 2a1 1 0 1 1-1.4-1.4l2-2a1 1 0 1 0-1.4-1.4l-3.5 3.5a1 1 0 0 1-1.4 0Zm2.8-2.8a1 1 0 0 1 0 1.4l-3.5 3.5a3 3 0 1 1-4.2-4.2l2-2a1 1 0 1 1 1.4 1.4l-2 2a1 1 0 1 0 1.4 1.4l3.5-3.5a1 1 0 0 1 1.4 0Z"/></svg>
                  <span>Copiar</span>
                </button>
                <a class="icon-btn" href="<?= e($link) ?>" target="_blank" rel="noopener" title="Ver convite">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 9 4.5 10 7-1 2.5-5 7-10 7S3 14.5 2 12c1-2.5 5-7 10-7Zm0 2c-3.5 0-6.6 3-7.8 5 1.2 2 4.3 5 7.8 5s6.6-3 7.8-5c-1.2-2-4.3-5-7.8-5Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"/></svg>
                  <span>Ver</span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    <?php endif; ?>
  </section>

  <aside class="panel side-panel">
    <h2>Atividade recente</h2>
    <?php if (!$activity): ?>
      <p class="muted small">Nenhuma atividade ainda. Quando os convidados abrirem o link ou responderem, aparecerá aqui.</p>
    <?php else: ?>
      <ul class="feed">
        <?php foreach ($activity as $a): ?>
          <li class="feed-<?= e($a['action']) ?>">
            <strong><?= e($a['name'] ?? 'Convidado removido') ?></strong>
            <?= e($activityText[$a['action']] ?? $a['action']) ?><?= $a['detail'] ? ' (' . e($a['detail']) . ')' : '' ?>
            <time><?= e(fmt_datetime($a['created_at'])) ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</div>
<?php admin_footer();
