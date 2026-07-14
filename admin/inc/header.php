<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/php/helpers.php';
require_once dirname(__DIR__, 2) . '/php/messages.php';

jota_admin_require_login();
try {
    $counts = jota_messages_counts();
} catch (Throwable $e) {
    $counts = ['all' => 0, 'new' => 0, 'read' => 0, 'archived' => 0];
}
$pageTitle = $pageTitle ?? 'Painel';
$activeNav = $activeNav ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= jota_h($pageTitle) ?> · JOTA Admin</title>
  <link rel="icon" type="image/png" href="../assets/img/favicon.png" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <img src="../assets/img/logo-icon-white.png" alt="JOTA Advocacia" width="48" height="48" />
      <span>Área interna</span>
    </div>
    <nav class="admin-nav">
      <a class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>" href="index.php">Dashboard</a>
      <a class="<?= $activeNav === 'mensagens' ? 'is-active' : '' ?>" href="mensagens.php">
        Mensagens
        <?php if (($counts['new'] ?? 0) > 0): ?>
          <em class="admin-badge"><?= (int) $counts['new'] ?></em>
        <?php endif; ?>
      </a>
      <a href="../index.html" target="_blank" rel="noopener">Ver site</a>
    </nav>
    <div class="admin-sidebar-foot">
      <span><?= jota_h(JOTA_ADMIN_USER) ?></span>
      <a href="logout.php">Sair</a>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <h1><?= jota_h($pageTitle) ?></h1>
      <span class="admin-topbar-meta"><?= jota_h(defined('JOTA_ADMIN_DISPLAY_NAME') ? JOTA_ADMIN_DISPLAY_NAME : JOTA_ADMIN_USER) ?> · <?= jota_h(date('d/m/Y H:i')) ?></span>
    </header>
    <main class="admin-content">
