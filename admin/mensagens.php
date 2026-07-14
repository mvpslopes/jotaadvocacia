<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/php/helpers.php';
jota_admin_require_login();

require_once dirname(__DIR__) . '/php/messages.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (string) ($_POST['id'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if ($id !== '') {
        if ($action === 'read') {
            jota_messages_update_status($id, 'read');
        } elseif ($action === 'new') {
            jota_messages_update_status($id, 'new');
        } elseif ($action === 'archive') {
            jota_messages_update_status($id, 'archived');
        }
    }
    $redirect = 'mensagens.php';
    if (!empty($_GET['status'])) {
        $redirect .= '?status=' . rawurlencode((string) $_GET['status']);
    }
    if (!empty($_POST['id'])) {
        $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . 'id=' . rawurlencode((string) $_POST['id']);
    }
    header('Location: ' . $redirect);
    exit;
}

$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'new', 'read', 'archived'], true)) {
    $statusFilter = 'all';
}

$all = jota_messages_all();
$counts = jota_messages_counts();
$messages = array_values(array_filter($all, static function ($item) use ($statusFilter) {
    if ($statusFilter === 'all') {
        return true;
    }
    return ($item['status'] ?? 'new') === $statusFilter;
}));

$selectedId = (string) ($_GET['id'] ?? '');
$selected = null;
if ($selectedId !== '') {
    $selected = jota_messages_find($selectedId);
}
if (!$selected && $messages) {
    $selected = $messages[0];
    $selectedId = (string) ($selected['id'] ?? '');
}

$pageTitle = 'Mensagens';
$activeNav = 'mensagens';
require __DIR__ . '/inc/header.php';
?>

<section class="admin-toolbar">
  <div class="admin-tabs">
    <a class="<?= $statusFilter === 'all' ? 'is-active' : '' ?>" href="mensagens.php?status=all">Todas (<?= (int) $counts['all'] ?>)</a>
    <a class="<?= $statusFilter === 'new' ? 'is-active' : '' ?>" href="mensagens.php?status=new">Novas (<?= (int) $counts['new'] ?>)</a>
    <a class="<?= $statusFilter === 'read' ? 'is-active' : '' ?>" href="mensagens.php?status=read">Lidas (<?= (int) $counts['read'] ?>)</a>
    <a class="<?= $statusFilter === 'archived' ? 'is-active' : '' ?>" href="mensagens.php?status=archived">Arquivadas (<?= (int) $counts['archived'] ?>)</a>
  </div>
</section>

<section class="admin-inbox">
  <aside class="admin-inbox-list">
    <?php if (!$messages): ?>
      <p class="admin-muted" style="padding:20px">Nenhuma mensagem neste filtro.</p>
    <?php endif; ?>
    <?php foreach ($messages as $item): ?>
      <?php
        $isActive = ($item['id'] ?? '') === $selectedId;
        $isNew = ($item['status'] ?? '') === 'new';
        $when = !empty($item['created_at']) ? date('d/m H:i', strtotime((string) $item['created_at'])) : '';
      ?>
      <a class="admin-inbox-item <?= $isActive ? 'is-active' : '' ?> <?= $isNew ? 'is-new' : '' ?>"
         href="mensagens.php?status=<?= rawurlencode($statusFilter) ?>&id=<?= rawurlencode((string) $item['id']) ?>">
        <div>
          <strong><?= jota_h((string) ($item['name'] ?? 'Sem nome')) ?></strong>
          <span><?= jota_h((string) ($item['subject'] ?? '')) ?></span>
        </div>
        <em><?= jota_h($when) ?></em>
      </a>
    <?php endforeach; ?>
  </aside>

  <article class="admin-inbox-detail admin-card">
    <?php if (!$selected): ?>
      <p class="admin-muted">Selecione uma mensagem para ler.</p>
    <?php else: ?>
      <?php
        if (($selected['status'] ?? '') === 'new') {
            jota_messages_update_status((string) $selected['id'], 'read');
            $selected['status'] = 'read';
        }
        $phone = (string) ($selected['phone'] ?? '');
        $reply = "Olá " . ((string) ($selected['name'] ?? '')) . "! Sou da equipe JOTA Advocacia e estou retornando o contato feito pelo site.";
        $wa = jota_whatsapp_link($phone, $reply);
        $created = !empty($selected['created_at']) ? date('d/m/Y H:i', strtotime((string) $selected['created_at'])) : '';
      ?>
      <div class="admin-detail-head">
        <div>
          <h2><?= jota_h((string) ($selected['name'] ?? '')) ?></h2>
          <p><?= jota_h((string) ($selected['subject'] ?? '')) ?> · <?= jota_h($created) ?></p>
        </div>
        <div class="admin-detail-actions">
          <a class="admin-btn admin-btn--gold" href="<?= jota_h($wa) ?>" target="_blank" rel="noopener">Responder no WhatsApp</a>
        </div>
      </div>

      <dl class="admin-meta">
        <div>
          <dt>Telefone</dt>
          <dd><a href="<?= jota_h($wa) ?>" target="_blank" rel="noopener"><?= jota_h($phone) ?></a></dd>
        </div>
        <div>
          <dt>E-mail</dt>
          <dd>
            <?php $email = (string) ($selected['email'] ?? ''); ?>
            <?php if ($email !== ''): ?>
              <a href="mailto:<?= jota_h($email) ?>"><?= jota_h($email) ?></a>
            <?php else: ?>
              —
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt>Status</dt>
          <dd><?= jota_h((string) ($selected['status'] ?? '')) ?></dd>
        </div>
        <div>
          <dt>IP</dt>
          <dd><?= jota_h((string) ($selected['ip'] ?? '—')) ?></dd>
        </div>
      </dl>

      <div class="admin-message-body">
        <?= nl2br(jota_h((string) ($selected['message'] ?? '(Sem mensagem adicional)'))) ?>
      </div>

      <form method="post" class="admin-detail-forms" action="mensagens.php?status=<?= rawurlencode($statusFilter) ?>">
        <input type="hidden" name="id" value="<?= jota_h((string) $selected['id']) ?>" />
        <button class="admin-btn admin-btn--outline" type="submit" name="action" value="new">Marcar como nova</button>
        <button class="admin-btn admin-btn--outline" type="submit" name="action" value="read">Marcar como lida</button>
        <button class="admin-btn admin-btn--outline" type="submit" name="action" value="archive">Arquivar</button>
      </form>
    <?php endif; ?>
  </article>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
