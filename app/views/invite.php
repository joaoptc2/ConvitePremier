<?php
/** @var array|null $guest */
/** @var string|null $error */
/** @var bool $editing */
$eventName = (string)setting('event_name');
$ogTitle = $guest ? 'Convite para ' . first_name($guest['name']) . ' · ' . $eventName : $eventName;
$ogDesc = $guest
    ? setting('tagline') . ' — ' . event_date_long() . ', ' . event_time_range() . '.'
    : 'Convite pessoal. Acesse pelo link exclusivo que você recebeu.';
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
        <img class="logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Hospital Premier · 10 anos" width="250" height="77">
        <p class="tagline"><span><?= e(setting('tagline')) ?></span></p>
      </header>

      <?php if (!$guest): ?>
      <section class="restricted">
        <div class="ornament" aria-hidden="true"><span></span></div>
        <span class="seal" aria-hidden="true">
          <svg viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg>
        </span>
        <h1 class="question">Convite pessoal</h1>
        <?php if ($token !== ''): ?>
          <p class="note">Não encontramos este convite.<br>Confira se o link foi aberto por completo, exatamente como você recebeu.</p>
        <?php else: ?>
          <p class="note">Este convite é pessoal e intransferível.</p>
        <?php endif; ?>
        <p class="note">Para ver os detalhes e confirmar sua presença, acesse pelo <strong>link exclusivo</strong> que enviamos para você.</p>
        <p class="note small">Não recebeu o link? Fale com a organização do evento.</p>
      </section>
      <?php else: ?>

      <p class="greeting"><span>Olá,</span> <?= e($plural ? $guest['name'] : first_name($guest['name'])) ?></p>

      <h1 class="headline">
        <span class="h1"><?= e($headline1) ?></span>
        <em class="h2"><?= e($headline2) ?></em>
      </h1>

      <div class="ornament" aria-hidden="true"><span></span></div>

      <dl class="details">
        <div class="detail">
          <dt>Data</dt>
          <dd class="big"><?= e(event_date_full()) ?></dd>
        </div>
        <div class="detail">
          <dt>Horário</dt>
          <dd class="big">Das <?= e(event_time_range()) ?></dd>
        </div>
        <div class="detail">
          <dt>Local</dt>
          <dd>
            <?php if (setting('venue_name')): ?><strong class="venue"><?= e(setting('venue_name')) ?></strong><?php endif; ?>
            <span class="address"><?= implode('<br>', array_map('e', preg_split('/\s+·\s+/u', (string)setting('address')))) ?></span>
            <?php if (setting('map_url')): ?>
              <a class="map" href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
                Ver no mapa
              </a>
            <?php endif; ?>
          </dd>
        </div>
        <?php if (setting('dress_code')): ?>
          <div class="detail">
            <dt>Traje</dt>
            <dd class="big"><?= e(setting('dress_code')) ?></dd>
          </div>
        <?php endif; ?>
      </dl>

      <hr class="rule">

      <section class="rsvp" id="resposta">
        <?php if ($error): ?>
          <p class="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($showForm): ?>
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
            <span class="seal" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M5 12.5l4.2 4.2L19 7"/></svg>
            </span>
            <p class="question">Presença confirmada</p>
            <p class="note">
              Obrigado, <?= e(first_name($guest['response_name'] ?: $guest['name'])) ?>!
              Esperamos <?= $plural ? 'vocês' : 'você' ?> no dia <?= e(event_date_long()) ?>.
              <?php if ((int)$guest['companions'] > 0): ?>
                <br><span class="small">Confirmado com <?= (int)$guest['companions'] ?> acompanhante<?= (int)$guest['companions'] > 1 ? 's' : '' ?>.</span>
              <?php endif; ?>
            </p>
            <a class="btn btn-outline" href="<?= e(google_calendar_link()) ?>" target="_blank" rel="noopener">Salvar na agenda</a>
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
      <?php endif; ?>
    </div>
  </article>
</main>
<script src="<?= e(asset('assets/js/convite.js')) ?>" defer></script>
</body>
</html>
