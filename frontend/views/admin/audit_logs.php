<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Institutional Accountability & Comprehensive Audit Trail
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Institutional Audit Trail';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Filter & Search
$searchQuery = trim($_GET['search'] ?? '');
$actionFilter = $_GET['action_type'] ?? 'all';
$limit = 100;

$sql = "SELECT * FROM audit_trail WHERE 1=1";
$params = [];

if ($actionFilter !== 'all') {
    $sql .= " AND action = :act";
    $params['act'] = $actionFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(user_name) LIKE :q OR LOWER(staff_id) LIKE :q OR LOWER(details) LIKE :q OR LOWER(action) LIKE :q)";
    $params['q'] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY created_at DESC LIMIT :lim";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Unique actions for filter
$actionsList = $db->query("SELECT DISTINCT action FROM audit_trail ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        INSTITUTIONAL INTEGRITY & GOVERNANCE
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Comprehensive System Audit Trail
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Tamper-resistant audit log capturing all authentication, premise gate entries, claim authorizations, and payroll calculations.
      </p>
    </div>

    <button type="button" class="btn btn-secondary" onclick="window.print();">
      <i class="fa-solid fa-print"></i> Print Audit Log
    </button>
  </div>

  <!-- Search & Filter Controls -->
  <form action="" method="GET" class="no-print" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0; flex:2;">
        <label class="form-label">Search Audit Events</label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" class="form-control" placeholder="Search by staff name, ID, description, or action..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Action Type</label>
        <select name="action_type" class="form-select">
          <option value="all">All Action Types</option>
          <?php foreach ($actionsList as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= base_url('frontend/views/admin/audit_logs.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <!-- Audit Table -->
  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Log ID</th>
          <th>Timestamp</th>
          <th>User & Staff ID</th>
          <th>Action Type</th>
          <th>Target Entity</th>
          <th>Details of Event</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr class="empty-row"><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No audit logs found for this filter.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><span style="font-family:monospace; font-size:0.75rem; color:var(--text-muted);">#<?= $l['id'] ?></span></td>
              <td style="font-size:0.82rem; white-space:nowrap;"><?= format_datetime($l['created_at']) ?></td>
              <td>
                <strong><?= htmlspecialchars($l['user_name'] ?? 'System') ?></strong><br>
                <small style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($l['staff_id'] ?? 'SYS') ?></small>
              </td>
              <td>
                <span class="badge badge-secondary" style="font-family:monospace; font-size:0.72rem;">
                  <?= htmlspecialchars($l['action']) ?>
                </span>
              </td>
              <td>
                <span style="font-size:0.8rem; color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($l['entity']) ?>:<?= $l['entity_id'] ?></span>
              </td>
              <td style="max-width:320px; font-size:0.84rem;">
                <?= htmlspecialchars($l['details']) ?>
              </td>
              <td>
                <span style="font-family:monospace; font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($l['ip_address']) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
