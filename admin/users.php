<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireAdmin();

$db = getDb();
$user = getCurrentUser();
$flash = flashGet();

$userEditId = (int) ($_GET['user_edit'] ?? 0);
$userEditRow = null;
if ($userEditId > 0) {
    $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$userEditId]);
    $userEditRow = $stmt->fetch() ?: null;
}

$warehouseUsers = $db->query(
    "SELECT id, username, email, created_at,
            (SELECT COUNT(*) FROM user_count_records WHERE user_id = users.id) AS record_count
     FROM users
     WHERE role = 'user'
     ORDER BY username ASC"
)->fetchAll();

$listUrl = BASE_URL . '/admin/users.php';
$addUrl = $listUrl . '?add=1';
$openUserModal = $userEditRow || isset($_GET['add']);

adminPageStart('User Management', 'users', $user);
adminRenderFlash($flash);
?>
    <section class="panel" id="users">
      <div class="panel-head-row">
        <h2>User Management</h2>
        <a href="<?= htmlspecialchars($addUrl) ?>" class="btn btn-primary btn-add-shipment">+ Add User</a>
      </div>
      <p class="hint">Warehouse user accounts are created here. Public registration is disabled.</p>

      <h3 class="panel-subtitle">Warehouse Users</h3>
      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Records</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$warehouseUsers): ?>
              <tr><td colspan="5" class="empty">No warehouse users yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($warehouseUsers as $wu): ?>
            <tr>
              <td><?= htmlspecialchars($wu['username']) ?></td>
              <td><?= htmlspecialchars($wu['email']) ?></td>
              <td><?= (int) $wu['record_count'] ?></td>
              <td><?= htmlspecialchars(substr($wu['created_at'] ?? '', 0, 10)) ?></td>
              <td class="actions-cell">
                <a class="btn btn-sm btn-secondary"
                   href="<?= BASE_URL ?>/admin/users.php?user_edit=<?= (int) $wu['id'] ?>">Edit</a>
                <form method="post" action="<?= BASE_URL ?>/admin/user_action.php" class="inline-form"
                      onsubmit="return confirm('Delete this user and all their counting records?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $wu['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div id="user-modal" class="modal-overlay<?= $openUserModal ? ' is-open' : '' ?>"
         aria-hidden="<?= $openUserModal ? 'false' : 'true' ?>"
         data-list-url="<?= htmlspecialchars($listUrl) ?>">
      <div class="modal-dialog" role="dialog" aria-labelledby="user-modal-title">
        <div class="modal-header">
          <h2 id="user-modal-title"><?= $userEditRow ? 'Edit User' : 'Add User' ?></h2>
          <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= BASE_URL ?>/admin/user_action.php" class="form-grid form-grid-users modal-form">
          <input type="hidden" name="action" value="<?= $userEditRow ? 'update' : 'create' ?>">
          <?php if ($userEditRow): ?>
            <input type="hidden" name="id" value="<?= (int) $userEditRow['id'] ?>">
          <?php endif; ?>
          <div class="form-group">
            <label for="user_username">Username</label>
            <input type="text" id="user_username" name="username" required
                   value="<?= htmlspecialchars($userEditRow['username'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="user_email">Email</label>
            <input type="email" id="user_email" name="email" required
                   value="<?= htmlspecialchars($userEditRow['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="user_password"><?= $userEditRow ? 'New Password (optional)' : 'Password' ?></label>
            <div class="password-wrap">
              <input type="password" id="user_password"
                     name="<?= $userEditRow ? 'new_password' : 'password' ?>"
                     <?= $userEditRow ? '' : 'required minlength="6"' ?>>
              <button type="button" class="toggle-password" data-target="user_password">Show</button>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-save"><?= $userEditRow ? 'Update User' : 'Create User' ?></button>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($listUrl) ?>" data-modal-close>Cancel</a>
          </div>
        </form>
      </div>
    </div>
<?php
adminPageEnd([
    BASE_URL . '/assets/js/auth.js',
    BASE_URL . '/assets/js/admin-modal.js',
]);
