<?php
/** @var array $guest */
/** @var bool $isAdminPreview */
$eventName = (string)setting('event_name');
$plural = ($guest['treatment'] ?? 'm') === 'p';
$photoCount = photos_count();
$reviewUrl = trim((string)setting('review_url'));
$seconds = max(0, (int)setting('photos_seconds'));
$alreadyUnlocked = !empty($guest['photos_unlocked_at']);
$wait = $alreadyUnlocked ? 0 : $seconds;
$tokenParam = rawurlencode($guest['token']);
$api = base_path() . '/fotos.php?c=' . $tokenParam;
$reviewHref = $api . '&acao=avaliar';
$ogTitle = 'Fotos para ' . first_name($guest['name']) . ' · ' . $eventName;
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1b375c">
<title><?= e($ogTitle) ?></title>
<meta name="description" content="As fotos do evento estão disponíveis.">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="As fotos do evento estão disponíveis.">
<meta property="og:image" content="<?= e(base_url('assets/img/og.png')) ?>">
<link rel="icon" type="image/png" href="<?= e(asset('assets/img/icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/convite.css')) ?>">
</head>
<body>
<main class="stage stage-col">
  <article class="card">
    <div class="card-inner">
      <header class="brand">
        <img class="logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Hospital Premier · 10 anos" width="250" height="77">
        <p class="tagline"><span><?= e(setting('tagline')) ?></span></p>
      </header>

      <p class="greeting"><span>Olá,</span> <?= e($plural ? $guest['name'] : first_name($guest['name'])) ?></p>
      <h1 class="photos-title"><?= e(setting('photos_title')) ?></h1>

      <div class="ornament" aria-hidden="true"><span></span></div>

      <?php if ($photoCount === 0): ?>
        <section class="rsvp">
          <p class="note">Estamos preparando as fotos com muito carinho. Volte em breve por este mesmo link.</p>
        </section>
      <?php else: ?>
        <section class="gate" id="gate"
                 data-api="<?= e($api) ?>"
                 data-seconds="<?= (int)$wait ?>">
          <?php if (!$alreadyUnlocked): ?>
            <p class="note gate-message"><?= nl2br(e(photos_message_text('photos_message', $guest))) ?></p>
          <?php endif; ?>

          <ol class="steps<?= $reviewUrl === '' ? ' no-review' : '' ?>">
            <?php if ($reviewUrl !== ''): ?>
              <li class="step">
                <span class="step-num">1</span>
                <div class="step-body">
                  <strong>Avalie o Hospital Premier</strong>
                  <a class="btn btn-review" href="<?= e($reviewHref) ?>" target="_blank" rel="noopener" data-review>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.3L12 17.1l-5.7 3.1 1.2-6.3-4.7-4.4 6.4-.8L12 2.8Z"/></svg>
                    Avaliar no Google
                  </a>
                </div>
              </li>
            <?php endif; ?>
            <li class="step">
              <span class="step-num"><?= $reviewUrl !== '' ? '2' : '1' ?></span>
              <div class="step-body">
                <strong data-gate-label><?= $wait > 0 ? 'Suas fotos serão liberadas em' : 'Suas fotos estão liberadas' ?></strong>
                <div class="countdown" data-countdown<?= $wait > 0 ? '' : ' hidden' ?>>
                  <svg viewBox="0 0 100 100" aria-hidden="true">
                    <circle class="track" cx="50" cy="50" r="44"/>
                    <circle class="bar" cx="50" cy="50" r="44" pathLength="100"/>
                  </svg>
                  <span class="countdown-num"><b data-count><?= (int)$wait ?></b><small>seg</small></span>
                </div>
                <button type="button" class="btn btn-solid" data-unlock<?= $wait > 0 ? ' hidden' : '' ?>>Ver minhas fotos</button>
                <p class="alert" data-gate-error hidden></p>
              </div>
            </li>
          </ol>
        </section>
      <?php endif; ?>
    </div>
  </article>

  <?php if ($photoCount > 0): ?>
  <section class="card gallery-card" id="galeria" hidden>
    <div class="card-inner">
      <div class="gallery-head">
        <h2 class="question">Fotos do evento</h2>
        <p class="note small"><span data-gallery-count></span> fotos. Toque em uma foto para ampliar e baixar.</p>
        <?php if ($reviewUrl !== '' && empty($guest['review_clicked_at'])): ?>
          <p class="note small">Ainda não avaliou? <a class="change" href="<?= e($reviewHref) ?>" target="_blank" rel="noopener" data-review>Avaliar no Google</a></p>
        <?php endif; ?>
      </div>
      <div class="gallery" data-gallery></div>
    </div>
  </section>

  <div class="lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <button type="button" class="lb-btn lb-close" data-lb-close aria-label="Fechar">&times;</button>
    <button type="button" class="lb-btn lb-prev" data-lb-prev aria-label="Foto anterior">&#8249;</button>
    <img data-lb-img alt="">
    <button type="button" class="lb-btn lb-next" data-lb-next aria-label="Próxima foto">&#8250;</button>
    <div class="lb-bar">
      <span data-lb-pos></span>
      <a class="lb-download" data-lb-download href="#" download>Baixar foto</a>
    </div>
  </div>
  <?php endif; ?>
</main>
<script src="<?= e(asset('assets/js/fotos.js')) ?>" defer></script>
</body>
</html>
