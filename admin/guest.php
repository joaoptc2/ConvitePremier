<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$id = (int)($_GET['id'] ?? 0);
$guest = $id ? db_row('SELECT * FROM guests WHERE id = ?', [$id]) : null;
if ($id && !$guest) {
    flash('Convidado não encontrado.', 'error');
    redirect('index.php');
}

$errors = [];
$form = $guest ?: [
    'name' => '', 'treatment' => 'm', 'phone' => '', 'email' => '', 'group_name' => '',
    'max_companions' => 0, 'notes' => '', 'status' => 'pending', 'companions' => 0, 'companion_names' => '',
];

if (is_post()) {
    csrf_check();
    $op = (string)($_POST['op'] ?? 'save');

    if ($guest && $op === 'delete') {
        db_query('DELETE FROM activity WHERE guest_id = ?', [$guest['id']]);
        db_query('DELETE FROM guests WHERE id = ?', [$guest['id']]);
        flash('Convidado “' . $guest['name'] . '” excluído.');
        redirect('index.php');
    }

    if ($guest && $op === 'regenerate') {
        db_query('UPDATE guests SET token = ?, updated_at = ? WHERE id = ?', [generate_token(), now(), $guest['id']]);
        flash('Novo link gerado. O link anterior deixou de funcionar.');
        redirect('guest.php?id=' . $guest['id']);
    }

    $form = [
        'name' => clean_text($_POST['name'] ?? ''),
        'treatment' => in_array($_POST['treatment'] ?? '', ['m', 'f', 'p'], true) ? $_POST['treatment'] : 'm',
        'phone' => clean_text($_POST['phone'] ?? '', 40),
        'email' => clean_text($_POST['email'] ?? ''),
        'group_name' => clean_text($_POST['group_name'] ?? '', 120),
        'max_companions' => max(0, min(20, (int)($_POST['max_companions'] ?? 0))),
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'status' => in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'declined'], true) ? $_POST['status'] : 'pending',
        'companions' => max(0, (int)($_POST['companions'] ?? 0)),
        'companion_names' => trim(str_replace("\r", '', (string)($_POST['companion_names'] ?? ''))),
    ];
    if ($form['name'] === '') {
        $errors[] = 'Informe o nome do convidado.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail inválido.';
    }
    if ($form['status'] !== 'confirmed') {
        $form['companions'] = 0;
    }
    $form['companions'] = min($form['companions'], $form['max_companions']);

    if (!$errors) {
        $nullable = static function ($v) {
            return $v === '' ? null : $v;
        };
        if ($guest) {
            $statusChanged = $guest['status'] !== $form['status'];
            $respondedAt = $statusChanged ? ($form['status'] === 'pending' ? null : now()) : $guest['responded_at'];
            db_query(
                'UPDATE guests SET name = ?, treatment = ?, phone = ?, email = ?, group_name = ?, max_companions = ?, notes = ?,
                 status = ?, companions = ?, companion_names = ?, responded_at = ?, updated_at = ? WHERE id = ?',
                [
                    $form['name'], $form['treatment'], $nullable($form['phone']), $nullable($form['email']), $nullable($form['group_name']),
                    $form['max_companions'], $nullable($form['notes']), $form['status'], $form['companions'],
                    $form['companions'] > 0 ? $nullable($form['companion_names']) : null, $respondedAt, now(), $guest['id'],
                ]
            );
            if ($statusChanged) {
                log_activity((int)$guest['id'], 'manual', status_label($form['status']));
            }
            flash('Alterações salvas.');
            redirect('guest.php?id=' . $guest['id']);
        }

        db_query(
            'INSERT INTO guests (token, name, treatment, phone, email, group_name, max_companions, notes, status, companions, companion_names, responded_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                generate_token(), $form['name'], $form['treatment'], $nullable($form['phone']), $nullable($form['email']),
                $nullable($form['group_name']), $form['max_companions'], $nullable($form['notes']), $form['status'],
                $form['companions'], $form['companions'] > 0 ? $nullable($form['companion_names']) : null,
                $form['status'] !== 'pending' ? now() : null, now(), now(),
            ]
        );
        $newId = (int)db()->lastInsertId();
        flash('Convidado “' . $form['name'] . '” adicionado. O link exclusivo já está pronto.');
        redirect(!empty($_POST['add_another']) ? 'guest.php' : 'guest.php?id=' . $newId);
    }
}

$groups = existing_groups();
admin_header($guest ? $guest['name'] : 'Novo convidado', 'index');
?>
<p><a class="link" href="index.php">← Voltar para a lista</a></p>

<div class="layout">
  <section class="panel main-panel">
    <h1><?= $guest ? 'Editar convidado' : 'Novo convidado' ?></h1>
    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" class="form grid-form">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <label class="span-2">Nome completo *
        <input type="text" name="name" required maxlength="190" value="<?= e($form['name']) ?>" autofocus>
      </label>
      <label>Tratamento no convite
        <select name="treatment">
          <?php foreach (['m' => '“Você é nosso convidado”', 'f' => '“Você é nossa convidada”', 'p' => '“Vocês são nossos convidados”'] as $k => $v): ?>
            <option value="<?= $k ?>"<?= $form['treatment'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>WhatsApp / telefone
        <input type="tel" name="phone" maxlength="40" placeholder="(31) 99999-9999" value="<?= e($form['phone']) ?>">
      </label>
      <label>E-mail
        <input type="email" name="email" maxlength="190" value="<?= e($form['email']) ?>">
      </label>
      <label>Grupo
        <input type="text" name="group_name" maxlength="120" list="groups" placeholder="Ex.: Médicos, Diretoria, Parceiros" value="<?= e($form['group_name']) ?>">
        <datalist id="groups"><?php foreach ($groups as $gname): ?><option value="<?= e($gname) ?>"><?php endforeach; ?></datalist>
      </label>
      <label>Acompanhantes permitidos
        <input type="number" name="max_companions" min="0" max="20" value="<?= (int)$form['max_companions'] ?>">
        <small>0 = convite individual</small>
      </label>
      <label class="span-2">Observações internas
        <textarea name="notes" rows="2" placeholder="Visível só para a equipe"><?= e($form['notes']) ?></textarea>
      </label>

      <fieldset class="span-2">
        <legend>Resposta</legend>
        <div class="grid-form inner">
          <label>Status
            <select name="status">
              <?php foreach (['pending', 'confirmed', 'declined'] as $s): ?>
                <option value="<?= $s ?>"<?= $form['status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
              <?php endforeach; ?>
            </select>
            <small>Altere manualmente se o convidado respondeu por outro canal.</small>
          </label>
          <label>Acompanhantes confirmados
            <input type="number" name="companions" min="0" max="20" value="<?= (int)$form['companions'] ?>">
          </label>
          <label class="span-2">Nomes dos acompanhantes (um por linha)
            <textarea name="companion_names" rows="2"><?= e($form['companion_names']) ?></textarea>
          </label>
        </div>
      </fieldset>

      <div class="span-2 form-actions">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <?php if (!$guest): ?>
          <button class="btn" type="submit" name="add_another" value="1">Salvar e adicionar outro</button>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <?php if ($guest): $link = guest_link($guest); ?>
  <aside class="panel side-panel">
    <h2>Link exclusivo</h2>
    <div class="linkbox">
      <input type="text" readonly value="<?= e($link) ?>" onclick="this.select()">
    </div>
    <div class="stack">
      <a class="btn btn-wa" href="<?= e(whatsapp_link($guest)) ?>" target="_blank" rel="noopener" data-mark-sent data-id="<?= (int)$guest['id'] ?>">Enviar pelo WhatsApp</a>
      <button type="button" class="btn" data-copy="<?= e($link) ?>" data-mark-sent data-id="<?= (int)$guest['id'] ?>">Copiar link</button>
      <button type="button" class="btn" data-copy="<?= e(invite_message($guest)) ?>" data-mark-sent data-id="<?= (int)$guest['id'] ?>">Copiar mensagem completa</button>
      <a class="btn" href="<?= e($link) ?>" target="_blank" rel="noopener">Ver convite</a>
    </div>

    <h2>Acompanhamento</h2>
    <dl class="facts">
      <dt>Resposta</dt><dd><?= status_badge($guest) ?></dd>
      <?php if ($guest['response_name'] && $guest['response_name'] !== $guest['name']): ?>
        <dt>Nome informado</dt><dd><?= e($guest['response_name']) ?></dd>
      <?php endif; ?>
      <dt>Respondido em</dt><dd><?= e(fmt_datetime($guest['responded_at'])) ?></dd>
      <dt>Enviado em</dt><dd data-sent-label><?= e(fmt_datetime($guest['sent_at'])) ?></dd>
      <dt>Aberto</dt><dd><?= (int)$guest['view_count'] ?>x <?= $guest['first_viewed_at'] ? '· 1ª vez ' . e(fmt_datetime($guest['first_viewed_at'])) : '' ?></dd>
      <dt>Check-in</dt><dd><?= e(fmt_datetime($guest['checked_in_at'])) ?></dd>
      <?php if ($guest['message']): ?>
        <dt>Recado</dt><dd class="quote"><?= nl2br(e($guest['message'])) ?></dd>
      <?php endif; ?>
    </dl>

    <h2>Zona de cuidado</h2>
    <form method="post" onsubmit="return confirm('Gerar um novo link? O link atual deixará de funcionar.');">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="regenerate">
      <button class="btn btn-sm btn-block" type="submit">Gerar novo link</button>
    </form>
    <form method="post" onsubmit="return confirm('Excluir este convidado? Esta ação não pode ser desfeita.');">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="delete">
      <button class="btn btn-sm btn-danger btn-block" type="submit">Excluir convidado</button>
    </form>
  </aside>
  <?php endif; ?>
</div>
<?php admin_footer();
