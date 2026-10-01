<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Staff Notifications Center
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Notifications';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('staff');

$db = get_db();
$userId = $currentUser['id'];

// Handle Mark All Read action
if (isset($_POST['mark_all_read'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $stmtUp = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :uid");
        $stmtUp->execute(['uid' => $userId]);
        set_flash_message('success', 'All notifications marked as read.');
        header('Location: ' . base_url('frontend/views/staff/notifications.php'));
        exit;
    }
}

// Fetch all notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute(['uid' => $userId]);
$notifications = $stmt->fetchAll();
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">System Notifications</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Real-time alerts regarding premise clocking, overtime approvals, and duty timesheets
      </p>
    </div>

    <?php if (!empty($notifications)): ?>
      <form action="" method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="mark_all_read" value="1">
        <button type="submit" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-check-double"></i> Mark All as Read
        </button>
      </form>
    <?php endif; ?>
  </div>

  <div style="display:flex; flex-direction:column; gap:12px;">
    <?php if (empty($notifications)): ?>
      <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
        <i class="fa-regular fa-bell-slash" style="font-size:2.5rem; color:var(--border-color); margin-bottom:12px; display:block;"></i>
        You have no notifications at this time.
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div style="padding:16px; border-radius:var(--radius-md); border:1px solid <?= $n['is_read'] ? 'var(--border-color)' : 'var(--ehc-primary-light)' ?>; background:<?= $n['is_read'] ? 'var(--bg-card)' : 'var(--bg-subtle)' ?>; display:flex; align-items:flex-start; gap:16px;">
          <div style="width:40px; height:40px; border-radius:50%; background:<?= $n['type'] === 'success' ? '#ECFDF5; color:#059669;' : ($n['type'] === 'danger' ? '#FEF2F2; color:#DC2626;' : '#E0F2FE; color:#0288D1;') ?> display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <?php if ($n['type'] === 'success'): ?>
              <i class="fa-solid fa-check"></i>
            <?php elseif ($n['type'] === 'danger'): ?>
              <i class="fa-solid fa-triangle-exclamation"></i>
            <?php else: ?>
              <i class="fa-solid fa-info"></i>
            <?php endif; ?>
          </div>

          <div style="flex:1;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <h4 style="font-size:0.95rem; color:var(--ehc-dark); margin:0;">
                <?= htmlspecialchars($n['title']) ?>
                <?php if (!$n['is_read']): ?>
                  <span class="badge badge-warning" style="font-size:0.68rem; margin-left:6px;">New</span>
                <?php endif; ?>
              </h4>
              <span style="font-size:0.75rem; color:var(--text-muted);"><?= format_datetime($n['created_at']) ?></span>
            </div>
            <p style="font-size:0.86rem; color:var(--text-main); margin-top:4px; margin-bottom:4px;">
              <?= htmlspecialchars($n['message']) ?>
            </p>
            <?php if (!empty($n['link'])): ?>
              <a href="<?= htmlspecialchars($n['link']) ?>" style="font-size:0.8rem; font-weight:700;">View Details &rarr;</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
