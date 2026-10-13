<?php
declare(strict_types=1);

/*
 * Instalador: cria o arquivo app/config.php, as tabelas e o usuário administrador.
 * Depois de instalado ele se bloqueia sozinho.
 */
define('APP_ROOT', __DIR__);
define('APP_DIR', __DIR__ . '/app');
define('CONFIG_FILE', APP_DIR . '/config.php');

require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/event.php';

date_default_timezone_set('America/Sao_Paulo');

/**
 * 'fresh'      = sem config.php (instalação nova)
 * 'incomplete' = config.php existe e conecta, mas não há administrador
 * 'installed'  = tudo pronto (instalador bloqueado)
 * 'broken'     = config.php existe mas o banco não responde (instalador bloqueado por segurança)
 */
function install_state(): string
{
    if (!is_file(CONFIG_FILE)) {
        return 'fresh';
    }
    try {
        $cfg = require CONFIG_FILE;
        $pdo = db_connect($cfg);
    } catch (Throwable $e) {
        return 'broken';
    }
    try {
        $hash = $pdo->query("SELECT svalue FROM settings WHERE skey = 'admin_pass_hash'")->fetchColumn();
        return is_string($hash) && $hash !== '' ? 'installed' : 'incomplete';
    } catch (Throwable $e) {
        return 'incomplete'; // tabelas ainda não criadas
    }
}

function detect_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

$state = install_state();
$done = $state === 'installed';
$broken = $state === 'broken';
$errors = [];
$configText = null;
$in = [
    'base_url' => detect_base_url(),
    'db_driver' => 'mysql',
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'admin_user' => 'admin',
];

if (!$done && !$broken && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($in as $k => $v) {
        $in[$k] = trim((string)($_POST[$k] ?? $v));
    }
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!preg_match('#^https?://#i', $in['base_url'])) {
        $errors[] = 'Endereço do site inválido (deve começar com https://).';
    }
    if (!in_array($in['db_driver'], ['mysql', 'sqlite'], true)) {
        $errors[] = 'Banco de dados inválido.';
    }
    if ($in['db_driver'] === 'mysql' && ($in['db_name'] === '' || $in['db_user'] === '')) {
        $errors[] = 'Informe o nome do banco e o usuário do MySQL.';
    }
    if ($in['admin_user'] === '') {
        $errors[] = 'Informe o usuário do painel.';
    }
    if (mb_strlen($adminPass) < 8) {
        $errors[] = 'A senha do painel deve ter pelo menos 8 caracteres.';
    } elseif ($adminPass !== (string)($_POST['admin_pass2'] ?? '')) {
        $errors[] = 'As senhas do painel não conferem.';
    }

    if (!$errors) {
        $cfg = [
            'base_url' => rtrim($in['base_url'], '/'),
            'db_driver' => $in['db_driver'],
            'db_host' => $in['db_host'],
            'db_name' => $in['db_name'],
            'db_user' => $in['db_user'],
            'db_pass' => $dbPass,
            'db_path' => APP_DIR . '/data/convite.sqlite',
            'pretty_urls' => true,
            'timezone' => 'America/Sao_Paulo',
        ];
        try {
            $pdo = db_connect($cfg);
            db_install_schema($pdo, $cfg['db_driver']);

            $set = static function (string $k, string $v) use ($pdo): void {
                $st = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?');
                $st->execute([$k]);
                $sql = $st->fetchColumn() > 0 ? 'UPDATE settings SET svalue = ? WHERE skey = ?' : 'INSERT INTO settings (svalue, skey) VALUES (?, ?)';
                $pdo->prepare($sql)->execute([$v, $k]);
            };
            foreach (event_defaults() as $k => $v) {
                $st = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?');
                $st->execute([$k]);
                if ((int)$st->fetchColumn() === 0) {
                    $set($k, $v);
                }
            }
            $set('admin_user', $in['admin_user']);
            $set('admin_pass_hash', password_hash($adminPass, PASSWORD_DEFAULT));

            $php = "<?php\n// Gerado pelo instalador em " . date('d/m/Y H:i') . "\nreturn " . var_export($cfg, true) . ";\n";
            // Mantém o caminho do SQLite relativo à pasta app/
            $php = str_replace(var_export($cfg['db_path'], true), "__DIR__ . '/data/convite.sqlite'", $php);

            if (@file_put_contents(CONFIG_FILE, $php) === false) {
                $configText = $php;
            } else {
                @chmod(CONFIG_FILE, 0640);
                $done = true;
            }
        } catch (PDOException $e) {
            $errors[] = 'Não foi possível conectar ao banco: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalação · Convite Premier</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<main class="wrap">
  <div class="login" style="max-width:560px">
    <img class="login-logo" src="assets/img/logo.png" alt="Hospital Premier · 10 anos" width="220" height="68">
    <h1>Instalação</h1>

    <?php if ($done): ?>
      <div class="flash">Tudo pronto! O sistema está instalado.</div>
      <p class="muted">Por segurança, você pode apagar o arquivo <code>install.php</code> do servidor.</p>
      <p><a class="btn btn-primary" href="admin/">Acessar o painel</a></p>

    <?php elseif ($broken): ?>
      <div class="flash flash-error">O arquivo <code>app/config.php</code> já existe, mas não foi possível conectar ao banco de dados.</div>
      <p class="small muted">Confira os dados do banco em <code>app/config.php</code>. Para reinstalar do zero, apague esse arquivo e recarregue esta página.</p>

    <?php elseif ($configText !== null): ?>
      <div class="flash flash-error">O banco foi criado, mas não consegui gravar <code>app/config.php</code> (permissão de escrita).</div>
      <p class="small" style="text-align:left">Crie o arquivo <code>app/config.php</code> pelo Gerenciador de Arquivos da Hostinger com o conteúdo abaixo e depois acesse o painel:</p>
      <textarea rows="14" readonly style="font-family:monospace;font-size:12px"><?= e($configText) ?></textarea>
      <p><a class="btn btn-primary" href="admin/">Acessar o painel</a></p>

    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <fieldset>
          <legend>Site</legend>
          <label>Endereço do site
            <input type="url" name="base_url" value="<?= e($in['base_url']) ?>" required>
            <small>Usado para montar os links dos convidados.</small>
          </label>
        </fieldset>

        <fieldset>
          <legend>Banco de dados</legend>
          <label>Tipo
            <select name="db_driver" id="driver">
              <option value="mysql"<?= $in['db_driver'] === 'mysql' ? ' selected' : '' ?>>MySQL (recomendado na Hostinger)</option>
              <option value="sqlite"<?= $in['db_driver'] === 'sqlite' ? ' selected' : '' ?>>SQLite (arquivo, sem configuração)</option>
            </select>
          </label>
          <div id="mysql-fields" class="form">
            <p class="small muted" style="margin:0">Crie o banco em hPanel → Bancos de dados → Gerenciamento e copie os dados aqui.</p>
            <label>Servidor<input type="text" name="db_host" value="<?= e($in['db_host']) ?>"></label>
            <label>Nome do banco<input type="text" name="db_name" value="<?= e($in['db_name']) ?>" placeholder="u123456789_convite"></label>
            <label>Usuário do banco<input type="text" name="db_user" value="<?= e($in['db_user']) ?>" placeholder="u123456789_convite"></label>
            <label>Senha do banco<input type="password" name="db_pass" autocomplete="off"></label>
          </div>
        </fieldset>

        <fieldset>
          <legend>Acesso ao painel</legend>
          <label>Usuário<input type="text" name="admin_user" value="<?= e($in['admin_user']) ?>" required autocomplete="username"></label>
          <label>Senha (mín. 8 caracteres)<input type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></label>
          <label>Repita a senha<input type="password" name="admin_pass2" required minlength="8" autocomplete="new-password"></label>
        </fieldset>

        <button class="btn btn-primary btn-block" type="submit">Instalar</button>
      </form>
      <script>
        (function () {
          var d = document.getElementById('driver'), f = document.getElementById('mysql-fields');
          function s() { f.style.display = d.value === 'mysql' ? '' : 'none'; }
          d.addEventListener('change', s); s();
        })();
      </script>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
