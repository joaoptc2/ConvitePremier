<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

function to_utf8(string $s): string
{
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
    return $s;
}

function parse_treatment(string $v): string
{
    $v = mb_strtolower(trim($v));
    if ($v === '') {
        return 'm';
    }
    if (in_array($v, ['f', 'feminino', 'convidada', 'nossa', 'a'], true)) {
        return 'f';
    }
    if (in_array($v, ['p', 'plural', 'convidados', 'casal', 'familia', 'família', 'nossos'], true)) {
        return 'p';
    }
    return 'm';
}

function normalize_header(string $h): string
{
    $h = mb_strtolower(trim($h));
    $h = strtr($h, ['á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
    $map = [
        'name' => ['nome', 'nome completo', 'convidado', 'name'],
        'phone' => ['telefone', 'celular', 'whatsapp', 'fone', 'phone', 'tel'],
        'email' => ['email', 'e-mail', 'mail'],
        'group' => ['grupo', 'categoria', 'setor', 'group'],
        'companions' => ['acompanhantes', 'acompanhante', 'acomp', 'acompanhantes permitidos', '+1'],
        'treatment' => ['tratamento', 'genero', 'sexo'],
    ];
    foreach ($map as $key => $aliases) {
        if (in_array($h, $aliases, true)) {
            return $key;
        }
    }
    return '';
}

$result = null;
if (is_post()) {
    csrf_check();
    $raw = '';
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        if ($_FILES['file']['size'] > 2 * 1024 * 1024) {
            flash('Arquivo muito grande (máx. 2 MB).', 'error');
            redirect('import.php');
        }
        $raw = (string)file_get_contents($_FILES['file']['tmp_name']);
    } else {
        $raw = (string)($_POST['text'] ?? '');
    }
    $raw = trim(to_utf8(str_replace("\r\n", "\n", $raw)));

    if ($raw === '') {
        flash('Envie um arquivo CSV ou cole a lista de nomes.', 'error');
        redirect('import.php');
    }

    $lines = array_values(array_filter(explode("\n", str_replace("\r", "\n", $raw)), static fn($l) => trim($l) !== ''));
    $first = $lines[0];
    $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    if (substr_count($first, "\t") > 0) {
        $delimiter = "\t";
    }

    // Detecta cabeçalho
    $cols = ['name', 'phone', 'email', 'group', 'companions', 'treatment'];
    $headerCells = str_getcsv($first, $delimiter, '"', '\\');
    $mapped = array_map('normalize_header', $headerCells);
    if (in_array('name', $mapped, true)) {
        $cols = $mapped;
        array_shift($lines);
    }

    $skipDup = !empty($_POST['skip_duplicates']);
    $defaultGroup = clean_text($_POST['default_group'] ?? '', 120);
    $existing = [];
    foreach (db_all('SELECT name FROM guests') as $r) {
        $existing[mb_strtolower($r['name'])] = true;
    }

    $added = 0;
    $skipped = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($lines as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $row = [];
            foreach ($cols as $i => $key) {
                if ($key !== '' && !isset($row[$key])) {
                    $row[$key] = trim((string)($cells[$i] ?? ''));
                }
            }
            $name = clean_text($row['name'] ?? '');
            if ($name === '') {
                $skipped++;
                continue;
            }
            $key = mb_strtolower($name);
            if ($skipDup && isset($existing[$key])) {
                $skipped++;
                continue;
            }
            $existing[$key] = true;
            $email = clean_text($row['email'] ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }
            $group = clean_text($row['group'] ?? '', 120) ?: $defaultGroup;
            db_query(
                'INSERT INTO guests (token, name, treatment, phone, email, group_name, max_companions, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    generate_token(), $name, parse_treatment($row['treatment'] ?? ''),
                    ($p = clean_text($row['phone'] ?? '', 40)) !== '' ? $p : null,
                    $email !== '' ? $email : null,
                    $group !== '' ? $group : null,
                    max(0, min(20, (int)($row['companions'] ?? 0))),
                    'pending', now(), now(),
                ]
            );
            $added++;
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('Erro ao importar: ' . $ex->getMessage(), 'error');
        redirect('import.php');
    }

    flash("{$added} convidado(s) importados" . ($skipped ? ", {$skipped} linha(s) ignoradas (vazias ou já cadastradas)" : '') . '.');
    redirect('index.php?ordem=recentes');
}

admin_header('Importar convidados', 'import');
?>
<section class="panel narrow">
  <h1>Importar convidados</h1>
  <p class="muted">Cada convidado importado recebe automaticamente o seu link exclusivo.</p>

  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Opção 1 · Planilha (CSV)</legend>
      <p class="small muted">
        No Excel ou Google Planilhas, salve como <strong>CSV</strong>. Colunas aceitas:
        <code>nome</code>, <code>telefone</code>, <code>email</code>, <code>grupo</code>, <code>acompanhantes</code>, <code>tratamento</code>
        (convidado / convidada / plural). Só o nome é obrigatório.
        <a class="link" href="export.php?modelo=1">Baixar planilha modelo</a>
      </p>
      <input type="file" name="file" accept=".csv,.txt,text/csv">
    </fieldset>

    <fieldset>
      <legend>Opção 2 · Colar lista</legend>
      <p class="small muted">Um convidado por linha. Pode ser só o nome, ou <code>nome;telefone;email;grupo;acompanhantes;tratamento</code>.</p>
      <textarea name="text" rows="8" placeholder="Maria Silva;(31) 99999-0001&#10;João Souza;31999990002&#10;Ana Costa"></textarea>
    </fieldset>

    <label>Grupo padrão (para quem vier sem grupo)
      <input type="text" name="default_group" list="groups" maxlength="120">
      <datalist id="groups"><?php foreach (existing_groups() as $gname): ?><option value="<?= e($gname) ?>"><?php endforeach; ?></datalist>
    </label>
    <label class="check"><input type="checkbox" name="skip_duplicates" value="1" checked> <span>Ignorar nomes que já estão na lista</span></label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Importar</button>
    </div>
  </form>
</section>
<?php admin_footer();
