<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_layout.php';

requireUserRole();

$db = getDb();
$user = getCurrentUser();
$flash = flashGet();
$userId = (int) $_SESSION['user_id'];

$stmt = $db->prepare(
    'SELECT username, email, created_at,
            (SELECT COUNT(*) FROM user_count_records WHERE user_id = users.id) AS record_count
     FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: $user;

userPageStart('Profile', 'profile', $user);
userRenderFlash($flash);
?>
    <section class="panel profile-panel">
      <h2>My Profile</h2>
      <dl class="profile-dl">
        <div class="profile-dl-row">
          <dt>Username</dt>
          <dd><?= htmlspecialchars($profile['username'] ?? '') ?></dd>
        </div>
        <div class="profile-dl-row">
          <dt>Email</dt>
          <dd><?= htmlspecialchars($profile['email'] ?? '') ?></dd>
        </div>
        <div class="profile-dl-row">
          <dt>Role</dt>
          <dd>Warehouse User</dd>
        </div>
        <div class="profile-dl-row">
          <dt>Counting records</dt>
          <dd><?= (int) ($profile['record_count'] ?? 0) ?></dd>
        </div>
        <?php if (!empty($profile['created_at'])): ?>
        <div class="profile-dl-row">
          <dt>Account created</dt>
          <dd><?= htmlspecialchars(substr($profile['created_at'], 0, 10)) ?></dd>
        </div>
        <?php endif; ?>
      </dl>
      <p class="hint">Username and email are managed by your administrator. Contact admin if they need to be changed.</p>
    </section>

    <section class="panel">
      <h2>Change Password</h2>
      <form method="post" action="<?= BASE_URL ?>/user/profile_action.php" class="profile-form">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label for="current_password">Current Password</label>
          <div class="password-wrap">
            <input type="password" id="current_password" name="current_password" required>
            <button type="button" class="toggle-password" data-target="current_password">Show</button>
          </div>
        </div>
        <div class="form-group">
          <label for="new_password">New Password</label>
          <div class="password-wrap">
            <input type="password" id="new_password" name="new_password" required minlength="6">
            <button type="button" class="toggle-password" data-target="new_password">Show</button>
          </div>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <div class="password-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            <button type="button" class="toggle-password" data-target="confirm_password">Show</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    </section>
<?php
userPageEnd([BASE_URL . '/assets/js/auth.js']);
