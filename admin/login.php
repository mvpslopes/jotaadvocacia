<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/php/helpers.php';

jota_admin_boot_session();

if (jota_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (jota_admin_attempt_login($user, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Usuário ou senha incorretos.';
    usleep(400000);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Login · JOTA Admin</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon.png" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-login-body">
  <form class="admin-login-card" method="post" action="login.php" autocomplete="off">
    <img src="../assets/img/logo-navy.png" alt="JOTA Advocacia" width="160" height="46" />
    <h1>Área interna</h1>
    <p>Acesso restrito à equipe da JOTA Advocacia.</p>
    <?php if ($error !== ''): ?>
      <div class="admin-alert admin-alert--error"><?= jota_h($error) ?></div>
    <?php endif; ?>
    <label>
      Usuário
      <input type="text" name="user" required autofocus />
    </label>
    <label>
      Senha
      <input type="password" name="password" required />
    </label>
    <button type="submit" class="admin-btn admin-btn--gold">Entrar</button>
  </form>
</body>
</html>
