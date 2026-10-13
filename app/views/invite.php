<?php
/** @var array|null $guest */
/** @var string|null $error */
/** @var bool $editing */
$eventName = (string)setting('event_name');
$ogTitle = $guest ? 'Convite para ' . first_name($guest['name']) . ' · ' . $eventName : $eventName;
$ogDesc = setting('tagline') . ' — ' . event_date_long() . ', ' . event_time_range() . '.';
$hasAnswered = $guest && $guest['status'] !== 'pending';
$showForm = $guest && rsvp_open() && (!$hasAnswered || $editing);
$maxComp = $guest ? (int)$guest['max_companions'] : 0;
$plural = ($guest['treatment'] ?? 'm') === 'p';
$deadline = rsvp_deadline_text();
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1b375c">
<title><?= e($ogTitle) ?></title>
<meta name="description" content="<?= e($ogDesc) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:image" content="<?= e(base_url('assets/img/og.png')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<link rel="icon" type="image/png" href="<?= e(asset('assets/img/icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,400;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/convite.css')) ?>">
</head>
<body>
<main class="stage">
  <article class="card">
    <div class="card-inner">
      <header class="brand">
        <img class="logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Hospital Premier · 10 anos" width="224" height="69">
        <p class="tagline"><span><?= e(setting('tagline')) ?></span></p>
      </header>

      <?php if ($guest): ?>
        <p class="greeting">Olá, <?= e($plural ? $guest['name'] : first_name($guest['name'])) ?></p>
      <?php endif; ?>

      <h1 class="headline">
        <span class="h1"><?= e($guest ? $headline1 : 'Você é nosso') ?></span>
        <em class="h2"><?= e($guest ? $headline2 : 'convidado') ?></em>
      </h1>

      <div class="days" aria-label="Data do evento">
        <?php foreach (event_day_strip() as $day): ?>
          <div class="day<?= $day['active'] ? ' is-active' : '' ?>">
            <span class="wd"><?= e($day['weekday']) ?></span>
            <span class="num"><?= (int)$day['day'] ?></span>
            <?php if ($day['active']): ?>
              <svg class="ring" viewBox="0 0 160 140" aria-hidden="true">
                <ellipse cx="80" cy="72" rx="74" ry="62" transform="rotate(-6 80 72)"/>
                <ellipse cx="78" cy="70" rx="70" ry="64" transform="rotate(4 78 70)"/>
              </svg>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <dl class="details">
        <div><dt>Data</dt><dd><?= e(event_date_long()) ?></dd></div>
        <div><dt>Horário</dt><dd><?= e(event_time_range()) ?></dd></div>
        <div>
          <dt>Local</dt>
          <dd>
            <?php if (setting('venue_name')): ?><strong class="venue"><?= e(setting('venue_name')) ?></strong><br><?php endif; ?>
            <?= e(setting('address')) ?>
            <?php if (setting('map_url')): ?>
              <a class="map" href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener">Ver no mapa</a>
            <?php endif; ?>
          </dd>
        </div>
        <?php if (setting('dress_code')): ?>
          <div><dt>Traje</dt><dd><?= e(setting('dress_code')) ?></dd></div>
        <?php endif; ?>
      </dl>

      <hr class="rule">

      <section class="rsvp" id="resposta">
        <?php if ($error): ?>
          <p class="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if (!$guest): ?>
          <p class="question">Convite pessoal</p>
          <p class="note">
            <?= $token !== '' ? 'Não encontramos este convite. Confira se o link foi copiado por completo.' : 'Este convite é pessoal e intransferível.' ?><br>
            Para confirmar presença, use o link que você recebeu.
          </p>

        <?php elseif ($showForm): ?>
          <form method="post" action="" class="rsvp-form" autocomplete="on">
            <p class="question"><?= e($question) ?></p>
            <?php if ($deadline && !$hasAnswered): ?>
              <p class="note small">Confirme até <?= e($deadline) ?>.</p>
            <?php endif; ?>

            <label class="sr-only" for="name">Seu nome completo</label>
            <input class="field" id="name" name="name" type="text" maxlength="190" required
                   placeholder="Seu nome completo"
                   value="<?= e($guest['response_name'] ?: $guest['name']) ?>">

            <?php if ($maxComp > 0): ?>
              <div class="companions" data-companions>
                <label class="label" for="companions">Acompanhantes</label>
                <select class="field" id="companions" name="companions">
                  <option value="0">Irei sem acompanhante</option>
                  <?php for ($i = 1; $i <= $maxComp; $i++): ?>
                    <option value="<?= $i ?>"<?= (int)$guest['companions'] === $i ? ' selected' : '' ?>>
                      Levarei <?= $i ?> acompanhante<?= $i > 1 ? 's' : '' ?>
                    </option>
                  <?php endfor; ?>
                </select>
                <?php $names = $guest['companion_names'] ? explode("\n", $guest['companion_names']) : []; ?>
                <?php for ($i = 0; $i < $maxComp; $i++): ?>
                  <input class="field companion-name" type="text" maxlength="120"
                         name="companion_names[]" data-idx="<?= $i + 1 ?>"
                         placeholder="Nome do acompanhante <?= $maxComp > 1 ? $i + 1 : '' ?>"
                         value="<?= e($names[$i] ?? '') ?>">
                <?php endfor; ?>
              </div>
            <?php endif; ?>

            <?php if (setting('ask_message') === '1'): ?>
              <label class="sr-only" for="message">Recado</label>
              <textarea class="field textarea" id="message" name="message" rows="2" maxlength="1000"
                        placeholder="Deixe um recado (opcional)"><?= e($guest['message'] ?? '') ?></textarea>
            <?php endif; ?>

            <button class="btn btn-solid" type="submit" name="action" value="confirm">Confirmar presença</button>
            <button class="btn btn-outline" type="submit" name="action" value="decline" formnovalidate>Não poderei ir</button>
          </form>

        <?php elseif ($guest['status'] === 'confirmed'): ?>
          <div class="result">
            <p class="question">Presença confirmada</p>
            <p class="note">
              Obrigado, <?= e(first_name($guest['response_name'] ?: $guest['name'])) ?>!
              Esperamos <?= $plural ? 'vocês' : 'você' ?> no dia <?= e(event_date_long()) ?>.
              <?php if ((int)$guest['companions'] > 0): ?>
                <br><span class="small">Confirmado com <?= (int)$guest['companions'] ?> acompanhante<?= (int)$guest['companions'] > 1 ? 's' : '' ?>.</span>
              <?php endif; ?>
            </p>
            <a class="btn btn-solid" href="<?= e(google_calendar_link()) ?>" target="_blank" rel="noopener">Salvar na agenda</a>
            <a class="btn btn-outline" href="<?= e(base_path() . '/ics.php') ?>">Baixar evento (.ics)</a>
            <?php if ($allowChange && rsvp_open()): ?>
              <a class="change" href="<?= e(self_link($guest['token'], 'editar=1')) ?>#resposta">Alterar resposta</a>
            <?php endif; ?>
          </div>

        <?php elseif ($guest['status'] === 'declined'): ?>
          <div class="result">
            <p class="question">Que pena!</p>
            <p class="note">
              Sentiremos <?= $plural ? 'a falta de vocês' : 'sua falta' ?>, <?= e(first_name($guest['response_name'] ?: $guest['name'])) ?>.
              Obrigado por nos avisar.
            </p>
            <?php if ($allowChange && rsvp_open()): ?>
              <a class="change" href="<?= e(self_link($guest['token'], 'editar=1')) ?>#resposta">Mudou de ideia? Alterar resposta</a>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <p class="question">Confirmações encerradas</p>
          <p class="note">O prazo para confirmar presença terminou. Em caso de dúvida, fale com a organização do evento.</p>
        <?php endif; ?>
      </section>
    </div>
  </article>
</main>
<script src="<?= e(asset('assets/js/convite.js')) ?>" defer></script>
</body>
</html>
