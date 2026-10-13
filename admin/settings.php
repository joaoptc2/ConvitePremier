<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$eventFields = ['event_name', 'tagline', 'event_date', 'time_start', 'time_end', 'venue_name', 'address', 'map_url', 'dress_code', 'rsvp_deadline', 'whatsapp_message'];

if (is_post()) {
    csrf_check();
    $op = (string)($_POST['op'] ?? '');

    if ($op === 'event') {
        $errors = [];
        foreach ($eventFields as $f) {
            $value = trim(str_replace("\r", '', (string)($_POST[$f] ?? '')));
            if (in_array($f, ['event_date', 'rsvp_deadline'], true) && $value !== '' && !DateTime::createFromFormat('Y-m-d', $value)) {
                $errors[] = 'Data inválida.';
                continue;
            }
            if (in_array($f, ['time_start', 'time_end'], true) && $value !== '' && !preg_match('/^\d{2}:\d{2}$/', $value)) {
                $errors[] = 'Horário inválido.';
                continue;
            }
            if ($f === 'map_url' && $value !== '' && !preg_match('#^https?://#i', $value)) {
                $errors[] = 'O link do mapa deve começar com https://';
                continue;
            }
            setting_set($f, $value);
        }
        setting_set('allow_change', !empty($_POST['allow_change']) ? '1' : '0');
        setting_set('ask_message', !empty($_POST['ask_message']) ? '1' : '0');
        $errors ? flash(implode(' ', array_unique($errors)), 'error') : flash('Configurações do evento salvas.');
        redirect('settings.php');
    }

    if ($op === 'password') {
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $user = clean_text($_POST['user'] ?? '', 60);
        if (!password_verify($current, (string)setting('admin_pass_hash', ''))) {
            flash('Senha atual incorreta.', 'error');
        } elseif ($user === '') {
            flash('Informe o usuário.', 'error');
        } elseif ($new !== '' && mb_strlen($new) < 8) {
            flash('A nova senha deve ter pelo menos 8 caracteres.', 'error');
        } elseif ($new !== '' && $new !== (string)($_POST['confirm'] ?? '')) {
            flash('A confirmação não confere com a nova senha.', 'error');
        } else {
            setting_set('admin_user', $user);
            if ($new !== '') {
                setting_set('admin_pass_hash', password_hash($new, PASSWORD_DEFAULT));
            }
            $_SESSION['admin'] = $user;
            flash('Acesso atualizado.');
        }
        redirect('settings.php');
    }
}

$v = static fn(string $k): string => (string)setting($k);
$example = ['name' => 'Maria Silva', 'token' => 'ExEmPlo123', 'phone' => '', 'treatment' => 'f'];

admin_header('Configurações', 'settings');
?>
<div class="layout">
  <section class="panel main-panel">
    <h1>Evento</h1>
    <form method="post" class="form grid-form">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="event">
      <label class="span-2">Nome do evento
        <input type="text" name="event_name" value="<?= e($v('event_name')) ?>" maxlength="190">
        <small>Aparece no título da página e na prévia do link.</small>
      </label>
      <label class="span-2">Frase de destaque
        <input type="text" name="tagline" value="<?= e($v('tagline')) ?>" maxlength="120">
      </label>
      <label>Data do evento
        <input type="date" name="event_date" value="<?= e($v('event_date')) ?>" required>
      </label>
      <label>Confirmar até (opcional)
        <input type="date" name="rsvp_deadline" value="<?= e($v('rsvp_deadline')) ?>">
        <small>Depois desta data o convite não aceita novas respostas.</small>
      </label>
      <label>Início
        <input type="time" name="time_start" value="<?= e($v('time_start')) ?>" required>
      </label>
      <label>Término
        <input type="time" name="time_end" value="<?= e($v('time_end')) ?>">
      </label>
      <label class="span-2">Nome do local (opcional)
        <input type="text" name="venue_name" value="<?= e($v('venue_name')) ?>" maxlength="190">
      </label>
      <label class="span-2">Endereço
        <input type="text" name="address" value="<?= e($v('address')) ?>" maxlength="255">
      </label>
      <label class="span-2">Link do mapa
        <input type="url" name="map_url" value="<?= e($v('map_url')) ?>">
        <small>Cole o link de compartilhamento do Google Maps ou Waze.</small>
      </label>
      <label>Traje
        <input type="text" name="dress_code" value="<?= e($v('dress_code')) ?>" maxlength="120">
      </label>
      <div class="checks">
        <label class="check"><input type="checkbox" name="allow_change" value="1"<?= $v('allow_change') === '1' ? ' checked' : '' ?>> <span>Permitir que o convidado altere a resposta</span></label>
        <label class="check"><input type="checkbox" name="ask_message" value="1"<?= $v('ask_message') === '1' ? ' checked' : '' ?>> <span>Mostrar campo de recado</span></label>
      </div>
      <label class="span-2">Mensagem do WhatsApp
        <textarea name="whatsapp_message" rows="8"><?= e($v('whatsapp_message')) ?></textarea>
        <small><code>{voce}</code> vira “você” ou “vocês” e <code>{convidado}</code> vira “nosso convidado”, “nossa convidada” ou “nossos convidados”, conforme o tratamento de cada pessoa.<br>Variáveis: <code>{nome}</code> <code>{primeiro_nome}</code> <code>{voce}</code> <code>{convidado}</code> <code>{link}</code> <code>{data}</code> <code>{horario}</code></small>
      </label>
      <div class="span-2 form-actions">
        <button class="btn btn-primary" type="submit">Salvar evento</button>
      </div>
    </form>
  </section>

  <aside class="panel side-panel">
    <h2>Prévia da mensagem</h2>
    <div class="wa-preview"><?= nl2br(e(invite_message($example))) ?></div>

    <h2>Endereço do site</h2>
    <p class="small"><code><?= e(base_url()) ?></code></p>
    <p class="small muted">Os links dos convidados usam este endereço. Para trocar (ex.: ao apontar o domínio definitivo), edite <code>base_url</code> em <code>app/config.php</code>. Os links continuam válidos, só muda o domínio.</p>

    <h2>Acesso</h2>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="password">
      <label>Usuário<input type="text" name="user" value="<?= e($v('admin_user')) ?>" required autocomplete="username"></label>
      <label>Senha atual<input type="password" name="current" required autocomplete="current-password"></label>
      <label>Nova senha<input type="password" name="new" minlength="8" autocomplete="new-password" placeholder="Deixe em branco para manter"></label>
      <label>Confirmar nova senha<input type="password" name="confirm" autocomplete="new-password"></label>
      <button class="btn btn-block" type="submit">Atualizar acesso</button>
    </form>
  </aside>
</div>
<?php admin_footer();
