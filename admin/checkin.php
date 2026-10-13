<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$showAll = isset($_GET['todos']);
$guests = db_all(
    'SELECT * FROM guests' . ($showAll ? '' : " WHERE status = 'confirmed'") . ' ORDER BY name'
);
$stats = guest_stats();
$presentPeople = (int)db_value("SELECT COALESCE(SUM(1 + companions), 0) FROM guests WHERE checked_in_at IS NOT NULL");

admin_header('Check-in', 'checkin');
?>
<section class="panel">
  <div class="panel-head">
    <h1>Check-in do evento</h1>
    <div class="checkin-count">
      <strong data-present><?= $stats['checked_in'] ?></strong> de <?= $stats['confirmed'] ?> convites
      <span class="muted">· <?= $presentPeople ?> de <?= $stats['people'] ?> pessoas</span>
    </div>
  </div>
  <div class="filters">
    <input type="search" placeholder="Digite o nome do convidado…" data-live-search autofocus>
    <?php if ($showAll): ?>
      <a class="link" href="checkin.php">Mostrar só confirmados</a>
    <?php else: ?>
      <a class="link" href="checkin.php?todos=1">Incluir não confirmados</a>
    <?php endif; ?>
  </div>

  <ul class="checkin-list">
    <?php foreach ($guests as $g): ?>
      <li data-id="<?= (int)$g['id'] ?>" data-search="<?= e(mb_strtolower($g['name'] . ' ' . $g['response_name'] . ' ' . $g['companion_names'] . ' ' . $g['phone'])) ?>" class="<?= $g['checked_in_at'] ? 'is-in' : '' ?>">
        <div>
          <strong><?= e($g['name']) ?></strong>
          <div class="meta">
            <?= status_badge($g) ?>
            <?php if ($g['group_name']): ?><span class="chip"><?= e($g['group_name']) ?></span><?php endif; ?>
            <?php if ($g['companion_names']): ?><span>Com: <?= e(str_replace("\n", ', ', $g['companion_names'])) ?></span><?php endif; ?>
          </div>
        </div>
        <button type="button" class="btn btn-checkin" data-checkin>
          <?= $g['checked_in_at'] ? 'Presente · ' . e(date('H:i', strtotime($g['checked_in_at']))) : 'Fazer check-in' ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$guests): ?><p class="muted">Nenhum convidado confirmado ainda.</p><?php endif; ?>
</section>
<?php admin_footer();
