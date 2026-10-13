<?php
declare(strict_types=1);

define('ADMIN_PUBLIC', true);
require dirname(__DIR__) . '/app/admin.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;
if (is_post()) {
    csrf_check();
    $ip = client_ip();
    if (login_throttled($ip)) {
        $error = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
    } else {
        $user = trim((string)($_POST['user'] ?? ''));
        $pass = (string)($_POST['pass'] ?? '');
        $hash = (string)setting('admin_pass_hash', '');
        if ($hash !== '' && hash_equals((string)setting('admin_user', ''), $user) && password_verify($pass, $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $user;
            db_query('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
            redirect('index.php');
        }
        db_query('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)', [$ip, now()]);
        $error = 'Usuário ou senha inválidos.';
    }
}

admin_header('Entrar');
?>
<div class="login">
  <img class="login-logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="Hospital Premier · 10 anos" width="220" height="68">
  <h1>Gestão de convites</h1>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Usuário<input type="text" name="user" required autofocus autocomplete="username" value="<?= e($_POST['user'] ?? '') ?>"></label>
    <label>Senha<input type="password" name="pass" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Entrar</button>
  </form>
</div>
<?php admin_footer();
