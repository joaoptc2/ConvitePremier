<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    csrf_check();
    $op = (string)($_POST['op'] ?? '');

    if ($op === 'upload') {
        header('Content-Type: application/json; charset=utf-8');
        $f = $_FILES['photo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo maior que o limite do servidor.'
                : 'Falha no envio (código ' . (int)$code . ').';
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
        }
        try {
            $photo = photo_store_upload($f['tmp_name'], (string)$f['name']);
            exit(json_encode(['ok' => true, 'id' => (int)$photo['id'], 'thumb' => photo_url($photo, 'thumb')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }

    if ($op === 'settings') {
        $review = trim((string)($_POST['review_url'] ?? ''));
        if ($review !== '' && !preg_match('#^https://#i', $review)) {
            flash('O link de avaliação deve começar com https://', 'error');
            redirect('photos.php');
        }
        setting_set('review_url', $review);
        setting_set('photos_seconds', (string)max(0, min(120, (int)($_POST['photos_seconds'] ?? 20))));
        foreach (['photos_title', 'photos_message', 'whatsapp_photos_message'] as $k) {
            setting_set($k, trim(str_replace("\r", '', (string)($_POST[$k] ?? ''))));
        }
        $enable = !empty($_POST['photos_enabled']) ? '1' : '0';
        if ($enable === '1' && setting('photos_enabled') !== '1') {
            flash('Modo fotos ativado. Os links dos convidados agora abrem a página de fotos.');
        } elseif ($enable === '0' && setting('photos_enabled') === '1') {
            flash('Modo fotos desativado. Os links voltaram a mostrar o convite.');
        } else {
            flash('Configurações das fotos salvas.');
        }
        setting_set('photos_enabled', $enable);
        redirect('photos.php');
    }

    if ($op === 'delete') {
        $photo = db_row('SELECT * FROM photos WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($photo) {
            photo_delete($photo);
        }
        if ($ajax) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => (bool)$photo]));
        }
        flash('Foto excluída.');
        redirect('photos.php');
    }

    if ($op === 'delete_all') {
        $n = 0;
        foreach (photos_all() as $photo) {
            photo_delete($photo);
            $n++;
        }
        flash("{$n} foto(s) excluídas.");
        redirect('photos.php');
    }
}

$photos = photos_all();
$enabled = photos_enabled();
$v = static fn(string $k): string => (string)setting($k);
$limit = upload_limit_bytes();
$stats = db_row("SELECT
    SUM(CASE WHEN photos_sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent,
    SUM(CASE WHEN photos_opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
    SUM(CASE WHEN photos_unlocked_at IS NOT NULL THEN 1 ELSE 0 END) AS unlocked,
    SUM(CASE WHEN review_clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS reviewed,
    COUNT(*) AS total
    FROM guests") ?: [];
$stats = array_map('intval', $stats);
$sample = db_row('SELECT * FROM guests ORDER BY id LIMIT 1');
$totalBytes = array_sum(array_map(static fn($p) => (int)$p['bytes'], $photos));

admin_header('Fotos', 'photos');
?>
<section class="stats stats-4">
  <div class="stat"><span class="stat-num"><?= count($photos) ?></span><span class="stat-label">Fotos enviadas</span><small><?= number_format($totalBytes / 1048576, 1, ',', '.') ?> MB</small></div>
  <div class="stat"><span class="stat-num"><?= $stats['opened'] ?? 0 ?></span><span class="stat-label">Abriram a página</span><small><?= $stats['sent'] ?? 0 ?> link(s) enviados</small></div>
  <div class="stat stat-ok"><span class="stat-num"><?= $stats['unlocked'] ?? 0 ?></span><span class="stat-label">Viram as fotos</span></div>
  <div class="stat stat-wait"><span class="stat-num"><?= $stats['reviewed'] ?? 0 ?></span><span class="stat-label">Clicaram em avaliar</span></div>
</section>

<div class="layout">
  <section class="panel main-panel">
    <div class="panel-head">
      <h1>Fotos do evento</h1>
      <?php if ($photos): ?>
        <form method="post" onsubmit="return confirm('Excluir TODAS as fotos? Esta ação não pode ser desfeita.');">
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="delete_all">
          <button class="btn btn-sm btn-danger" type="submit">Excluir todas</button>
        </form>
      <?php endif; ?>
    </div>

    <label class="dropzone" data-dropzone data-limit="<?= $limit === PHP_INT_MAX ? 0 : $limit ?>">
      <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-file-input>
      <strong>Arraste as fotos para cá</strong>
      <span>ou clique para escolher. JPG, PNG ou WebP<?= $limit !== PHP_INT_MAX ? ', até ' . e(number_format($limit / 1048576, 0, ',', '.')) . ' MB cada' : '' ?>.</span>
    </label>
    <div class="upload-status" data-upload-status hidden>
      <div class="progress"><span class="p-ok" data-upload-bar style="width:0"></span></div>
      <p class="small" data-upload-text></p>
      <ul class="upload-errors small" data-upload-errors></ul>
    </div>

    <?php if (!$photos): ?>
      <p class="muted empty-photos" data-empty>Nenhuma foto enviada ainda.</p>
    <?php endif; ?>
    <div class="photo-grid" data-photo-grid>
      <?php foreach ($photos as $p): ?>
        <figure class="photo-item" data-id="<?= (int)$p['id'] ?>">
          <a href="<?= e(photo_url($p, 'web')) ?>" target="_blank" rel="noopener"><img src="<?= e(photo_url($p, 'thumb')) ?>" alt="" loading="lazy"></a>
          <button type="button" class="photo-del" data-photo-delete title="Excluir foto" aria-label="Excluir foto">&times;</button>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="panel side-panel">
    <h2>Modo fotos</h2>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="settings">
      <label class="check toggle">
        <input type="checkbox" name="photos_enabled" value="1"<?= $enabled ? ' checked' : '' ?>>
        <span><strong>Ativar página de fotos</strong><br><small>O link de cada convidado passa a abrir as fotos no lugar do convite.</small></span>
      </label>
      <label>Link de avaliação no Google
        <input type="url" name="review_url" value="<?= e($v('review_url')) ?>" placeholder="https://g.page/r/.../review">
        <small>No Perfil da Empresa do hospital, use “Pedir avaliações” e copie o link.</small>
      </label>
      <label>Contagem antes de liberar (segundos)
        <input type="number" name="photos_seconds" min="0" max="120" value="<?= e($v('photos_seconds')) ?>">
      </label>
      <label>Título
        <input type="text" name="photos_title" maxlength="120" value="<?= e($v('photos_title')) ?>">
      </label>
      <label>Mensagem
        <textarea name="photos_message" rows="5"><?= e($v('photos_message')) ?></textarea>
        <small>Variáveis: <code>{primeiro_nome}</code> <code>{nome}</code> <code>{voce}</code></small>
      </label>
      <label>Mensagem do WhatsApp com as fotos
        <textarea name="whatsapp_photos_message" rows="6"><?= e($v('whatsapp_photos_message')) ?></textarea>
        <small>Usada pelo botão WhatsApp da lista enquanto o modo fotos estiver ativo. Variáveis: <code>{primeiro_nome}</code> <code>{nome}</code> <code>{link}</code> <code>{voce}</code> <code>{convidado}</code></small>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Salvar</button>
    </form>
    <?php if ($sample): ?>
      <p class="small" style="margin-top:14px"><a class="link" href="<?= e(guest_link($sample)) ?>" target="_blank" rel="noopener">Ver como um convidado vê</a></p>
      <p class="small muted">Visitas feitas por você, logado no painel, não entram nas estatísticas.</p>
    <?php endif; ?>
  </aside>
</div>
<script src="<?= e(asset('assets/js/admin-photos.js')) ?>" defer></script>
<?php admin_footer();
