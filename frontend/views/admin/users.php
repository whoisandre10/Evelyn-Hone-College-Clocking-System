<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Admin User Management (Full CRUD, Roles, Rates & Status)
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Manage Staff & Users';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Search & Filter parameters
$searchQuery = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$deptFilter = $_GET['department_id'] ?? 'all';

$sql = "
    SELECT u.*, d.name AS dept_name, d.code AS dept_code, s.name AS school_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN schools s ON d.school_id = s.id
    WHERE 1=1
";
$params = [];

if ($roleFilter !== 'all') {
    $sql .= " AND u.role = :role";
    $params['role'] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $sql .= " AND u.status = :status";
    $params['status'] = $statusFilter;
}

if ($deptFilter !== 'all') {
    $sql .= " AND u.department_id = :dept_id";
    $params['dept_id'] = (int)$deptFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(u.full_name) LIKE :q OR LOWER(u.staff_id) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.nrc_number) LIKE :q)";
    $params['q'] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY u.role, u.full_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Fetch departments for dropdowns
$departments = $db->query("SELECT d.id, d.name, s.name AS school_name FROM departments d JOIN schools s ON d.school_id = s.id ORDER BY s.id, d.name")->fetchAll();
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Staff & User Directory</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Manage academic faculty, administrative staff, billing rates, and security credentials.
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')">
      <i class="fa-solid fa-user-plus"></i> Enroll New Staff Member
    </button>
  </div>

  <!-- Search & Filter Controls -->
  <form action="" method="GET" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0; flex:2;">
        <label class="form-label">Search Staff</label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" class="form-control" placeholder="Search by name, staff ID, email, NRC..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
          <option value="lecturer" <?= $roleFilter === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
          <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Members of Staff</option>
          <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Account Status</label>
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
          <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Filter</button>
        <a href="<?= base_url('frontend/views/admin/users.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <!-- Users Table -->
  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Staff ID</th>
          <th>Staff Name & Email</th>
          <th>Role & Category</th>
          <th>Department & School</th>
          <th>NRC Number</th>
          <th>Hourly Rate</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($usersList)): ?>
          <tr class="empty-row"><td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No staff records matched the search criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($usersList as $u): ?>
            <tr>
              <td>
                <strong style="font-family:monospace; color:var(--ehc-dark);"><?= htmlspecialchars($u['staff_id']) ?></strong><br>
                <small style="color:var(--text-muted); font-size:0.75rem;"><?= htmlspecialchars($u['rfid_card_id'] ?? 'No RFID') ?></small>
              </td>
              <td>
                <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                <span style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                <?php if (!empty($u['phone'])): ?>
                  <br><span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($u['phone']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : ($u['role'] === 'lecturer' ? 'badge-warning' : 'badge-info') ?>">
                  <?= ucfirst($u['role']) ?>
                </span><br>
                <small style="color:var(--text-muted); text-transform:capitalize;"><?= str_replace('_', ' ', $u['employment_type']) ?></small>
              </td>
              <td>
                <div style="font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($u['dept_name'] ?? 'General Institutional') ?></div>
                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($u['school_name'] ?? '') ?></div>
              </td>
              <td style="font-family:monospace; font-size:0.85rem;">
                <?= htmlspecialchars($u['nrc_number'] ?? '-') ?>
              </td>
              <td style="font-weight:700; color:var(--ehc-primary);">
                <?= format_currency($u['hourly_rate']) ?>
              </td>
              <td>
                <?php if ($u['status'] === 'active'): ?>
                  <span class="badge badge-success">Active</span>
                <?php elseif ($u['status'] === 'suspended'): ?>
                  <span class="badge badge-danger">Suspended</span>
                <?php else: ?>
                  <span class="badge badge-secondary"><?= ucfirst($u['status']) ?></span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem; color:var(--text-muted);">
                <?= !empty($u['last_login']) ? format_datetime($u['last_login']) : '<span style="font-style:italic;">Never</span>' ?>
              </td>
              <td>
                <div style="display:flex; gap:6px;">
                  <!-- Edit Button -->
                  <button type="button" class="btn btn-secondary btn-sm" onclick='populateEditModal(<?= json_encode($u) ?>)' title="Edit Staff Details">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>

                  <!-- Toggle Suspend Button -->
                  <?php if ($u['id'] != $currentUser['id']): ?>
                    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST" style="margin:0;" onsubmit="return confirm('Toggle account status for <?= htmlspecialchars($u['full_name']) ?>?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/users.php') ?>">
                      <button type="submit" class="btn <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> btn-sm" title="<?= $u['status'] === 'active' ? 'Suspend Staff' : 'Activate Staff' ?>">
                        <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                      </button>
                    </form>

                    <!-- Delete Button -->
                    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST" style="margin:0;" onsubmit="return confirm('WARNING: Are you sure you want to permanently remove staff record for <?= htmlspecialchars($u['full_name']) ?>? This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/users.php') ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Staff Record">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= ADD USER MODAL ================= -->
<div class="modal-backdrop" id="addUserModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--ehc-primary);"></i> Enroll New Staff Member</h3>
      <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/users.php') ?>">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Staff ID <span class="required">*</span></label>
            <input type="text" name="staff_id" class="form-control" placeholder="e.g. EHC-LEC-109" required>
          </div>

          <div class="form-group">
            <label class="form-label">RFID Premise Card ID</label>
            <input type="text" name="rfid_card_id" class="form-control" placeholder="RFID-880099 (Auto-assigned if blank)">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Patrick Mwale" required>
          </div>

          <div class="form-group">
            <label class="form-label">Official Email <span class="required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="p.mwale@evelynhone.edu.zm" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="+260 977 000000">
          </div>

          <div class="form-group">
            <label class="form-label">NRC Number</label>
            <input type="text" name="nrc_number" class="form-control" placeholder="e.g. 192837/11/1">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">System Role <span class="required">*</span></label>
            <select name="role" class="form-select" required>
              <option value="lecturer">Lecturer (Teaching Faculty)</option>
              <option value="staff">Staff (Administrative / Support)</option>
              <option value="admin">Administrator (Management & HR)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Employment Category <span class="required">*</span></label>
            <select name="employment_type" class="form-select" required>
              <option value="full_time">Full-Time</option>
              <option value="part_time">Part-Time (Contract Hourly)</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">School / Department <span class="required">*</span></label>
            <select name="department_id" class="form-select" required>
              <option value="">-- Select Department --</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>">
                  <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['school_name']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Hourly Base Rate (ZMW)</label>
            <input type="number" step="0.01" name="hourly_rate" class="form-control" value="220.00">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Designation / Title</label>
            <input type="text" name="designation" class="form-control" placeholder="e.g. Senior Lecturer">
          </div>

          <div class="form-group">
            <label class="form-label">Initial Password <span class="required">*</span></label>
            <input type="password" name="password" class="form-control" value="Staff@12345" required>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enroll Staff</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= EDIT USER MODAL ================= -->
<div class="modal-backdrop" id="editUserModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-user-pen" style="color:var(--ehc-primary);"></i> Edit Staff Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="editUserId">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/users.php') ?>">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name <span class="required">*</span></label>
            <input type="text" name="full_name" id="editFullName" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Official Email <span class="required">*</span></label>
            <input type="email" name="email" id="editEmail" class="form-control" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" id="editPhone" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">NRC Number</label>
            <input type="text" name="nrc_number" id="editNrc" class="form-control">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">RFID Premise Card ID</label>
            <input type="text" name="rfid_card_id" id="editRfid" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">Hourly Base Rate (ZMW)</label>
            <input type="number" step="0.01" name="hourly_rate" id="editRate" class="form-control">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">System Role <span class="required">*</span></label>
            <select name="role" id="editRole" class="form-select" required>
              <option value="lecturer">Lecturer</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Employment Category <span class="required">*</span></label>
            <select name="employment_type" id="editEmpType" class="form-select" required>
              <option value="full_time">Full-Time</option>
              <option value="part_time">Part-Time</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Department <span class="required">*</span></label>
            <select name="department_id" id="editDept" class="form-select" required>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Status <span class="required">*</span></label>
            <select name="status" id="editStatus" class="form-select" required>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Designation / Title</label>
          <input type="text" name="designation" id="editDesignation" class="form-control">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  function populateEditModal(u) {
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editFullName').value = u.full_name;
    document.getElementById('editEmail').value = u.email;
    document.getElementById('editPhone').value = u.phone || '';
    document.getElementById('editNrc').value = u.nrc_number || '';
    document.getElementById('editRfid').value = u.rfid_card_id || '';
    document.getElementById('editRate').value = u.hourly_rate;
    document.getElementById('editRole').value = u.role;
    document.getElementById('editEmpType').value = u.employment_type;
    document.getElementById('editDept').value = u.department_id || '';
    document.getElementById('editStatus').value = u.status;
    document.getElementById('editDesignation').value = u.designation || '';
    openModal('editUserModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
