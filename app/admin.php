<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

session_boot();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (!defined('ADMIN_PUBLIC')) {
    require_login();
    if (empty($_SESSION['db_checked'])) {
        try {
            db_upgrade();
        } catch (Throwable $e) {
            // não impede o uso do painel
        }
        $_SESSION['db_checked'] = 1;
    }
}

function admin_header(string $title, string $active = ''): void
{
    $nav = [
        'index' => ['index.php', 'Convidados'],
        'import' => ['import.php', 'Importar'],
        'checkin' => ['checkin.php', 'Check-in'],
        'settings' => ['settings.php', 'Configurações'],
    ];
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · Convite Premier</title>
<link rel="icon" type="image/png" href="<?= e(asset('assets/img/icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<?php if (is_logged_in()): ?>
<header class="topbar">
  <a class="brand" href="index.php">
    <img src="<?= e(asset('assets/img/logo.png')) ?>" alt="Hospital Premier" width="120" height="37">
    <span>Convites</span>
  </a>
  <nav class="nav">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $active === $key ? ' class="is-active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(base_path() . '/') ?>" target="_blank" rel="noopener">Ver site</a>
    <a href="logout.php" class="logout">Sair</a>
  </nav>
</header>
<?php endif; ?>
<main class="wrap">
<?php foreach (flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

function status_badge(array $g): string
{
    $label = status_label($g['status']);
    if ($g['status'] === 'confirmed' && (int)$g['companions'] > 0) {
        $label .= ' +' . (int)$g['companions'];
    }
    return '<span class="badge badge-' . e($g['status']) . '">' . e($label) . '</span>';
}

function existing_groups(): array
{
    return array_column(
        db_all("SELECT DISTINCT group_name FROM guests WHERE group_name IS NOT NULL AND group_name <> '' ORDER BY group_name"),
        'group_name'
    );
}
