<?php
declare(strict_types=1);

/*
 * Galeria de fotos do evento.
 * Arquivos: uploads/fotos/<arquivo original>, uploads/fotos/web/<nome>.jpg (até 1800px)
 * e uploads/fotos/thumb/<nome>.jpg (até 600px).
 */

const PHOTO_WEB_MAX = 1800;
const PHOTO_THUMB_MAX = 600;

function photos_enabled(): bool
{
    return setting('photos_enabled') === '1';
}

/** Coluna que registra o envio da mensagem atual (convite ou fotos). */
function sent_column(): string
{
    return photos_enabled() ? 'photos_sent_at' : 'sent_at';
}

function photos_dir(string $sub = ''): string
{
    return APP_ROOT . '/uploads/fotos' . ($sub !== '' ? '/' . $sub : '');
}

function photo_base(array $photo): string
{
    return pathinfo($photo['file'], PATHINFO_FILENAME);
}

function photo_url(array $photo, string $size = 'web'): string
{
    $path = $size === 'orig'
        ? 'uploads/fotos/' . $photo['file']
        : 'uploads/fotos/' . $size . '/' . photo_base($photo) . '.jpg';
    return base_path() . '/' . $path;
}

function photos_all(): array
{
    return db_all('SELECT * FROM photos ORDER BY original_name, id');
}

function photos_count(): int
{
    return (int)db_value('SELECT COUNT(*) FROM photos');
}

/** Converte valores do php.ini como "128M" em bytes. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }
    $n = (float)$value;
    switch (strtolower(substr($value, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return (int)$n;
}

function upload_limit_bytes(): int
{
    return min(ini_bytes((string)ini_get('upload_max_filesize')), ini_bytes((string)ini_get('post_max_size')));
}

function photos_ensure_dirs(): void
{
    foreach (['', 'web', 'thumb'] as $sub) {
        $dir = photos_dir($sub);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $dir . '. Verifique as permissões.');
        }
    }
    $guard = APP_ROOT . '/uploads/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Options -Indexes\n<FilesMatch \"\\.(php\\d?|phtml|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}

/** Abre a imagem com GD já na orientação correta (EXIF dos celulares). */
function photo_open(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($path);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($path);
            break;
        case 'image/webp':
            $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            break;
        default:
            $img = false;
    }
    if (!$img) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $o = (int)($exif['Orientation'] ?? 1);
        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }
        $angle = [3 => 180, 4 => 180, 5 => 270, 6 => 270, 7 => 90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $rotated = imagerotate($img, $angle, 0);
            imagedestroy($img);
            $img = $rotated;
        }
    }
    return $img;
}

function photo_save_resized($img, string $dest, int $max): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // PNG transparente vira fundo branco
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($out, true);
    if (!imagejpeg($out, $dest, 82)) {
        imagedestroy($out);
        throw new RuntimeException('Não foi possível salvar a imagem redimensionada.');
    }
    imagedestroy($out);
}

/** Processa um arquivo enviado e retorna a linha criada na tabela photos. */
function photo_store_upload(string $tmpPath, string $originalName): array
{
    $info = @getimagesize($tmpPath);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($types[$info['mime']])) {
        throw new RuntimeException('Formato não suportado. Envie fotos em JPG, PNG ou WebP.');
    }
    photos_ensure_dirs();
    @ini_set('memory_limit', '768M');
    @set_time_limit(120);

    $base = bin2hex(random_bytes(10));
    $file = $base . '.' . $types[$info['mime']];
    $origDest = photos_dir() . '/' . $file;
    $moved = is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $origDest) : copy($tmpPath, $origDest);
    if (!$moved) {
        throw new RuntimeException('Não foi possível gravar o arquivo no servidor.');
    }

    try {
        $img = photo_open($origDest, $info['mime']);
        $w = imagesx($img);
        $h = imagesy($img);
        photo_save_resized($img, photos_dir('web') . '/' . $base . '.jpg', PHOTO_WEB_MAX);
        photo_save_resized($img, photos_dir('thumb') . '/' . $base . '.jpg', PHOTO_THUMB_MAX);
        imagedestroy($img);
    } catch (Throwable $e) {
        @unlink($origDest);
        @unlink(photos_dir('web') . '/' . $base . '.jpg');
        throw $e;
    }

    db_query(
        'INSERT INTO photos (file, original_name, width, height, bytes, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$file, mb_substr($originalName, 0, 190), $w, $h, (int)filesize($origDest), now()]
    );
    return db_row('SELECT * FROM photos WHERE id = ?', [(int)db()->lastInsertId()]);
}

function photo_delete(array $photo): void
{
    @unlink(photos_dir() . '/' . $photo['file']);
    @unlink(photos_dir('web') . '/' . photo_base($photo) . '.jpg');
    @unlink(photos_dir('thumb') . '/' . photo_base($photo) . '.jpg');
    db_query('DELETE FROM photos WHERE id = ?', [$photo['id']]);
}

/** Nome amigável para download: "foto-007.jpg". */
function photo_download_name(array $photo, int $index): string
{
    return sprintf('premier-10-anos-%03d.%s', $index, pathinfo($photo['file'], PATHINFO_EXTENSION));
}

function photos_message_text(string $key, array $guest): string
{
    $t = $guest['treatment'] ?? 'm';
    return strtr((string)setting($key), [
        '{nome}' => $guest['name'],
        '{primeiro_nome}' => first_name($guest['name']),
        '{voce}' => $t === 'p' ? 'vocês' : 'você',
    ]);
}
