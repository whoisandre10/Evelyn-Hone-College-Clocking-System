<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Official Schools, Academic Departments & Heads of Department (HODs) Management
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Schools & Heads of Department';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_department') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $schoolId = (int)$_POST['school_id'];
        $deptName = sanitize_input($_POST['name'] ?? '');
        $deptCode = strtoupper(sanitize_input($_POST['code'] ?? ''));
        $hodName = sanitize_input($_POST['hod_name'] ?? '');
        $hodEmail = strtolower(sanitize_input($_POST['hod_email'] ?? ''));
        $hodPhone = sanitize_input($_POST['hod_phone'] ?? '');
        $office = sanitize_input($_POST['office_location'] ?? '');

        if (!empty($deptName) && !empty($deptCode) && !empty($hodName)) {
            $stmtIns = $db->prepare("
                INSERT INTO departments (school_id, name, code, hod_name, hod_email, hod_phone, office_location)
                VALUES (:sid, :name, :code, :hod, :email, :phone, :off)
            ");
            $stmtIns->execute([
                'sid'   => $schoolId,
                'name'  => $deptName,
                'code'  => $deptCode,
                'hod'   => $hodName,
                'email' => $hodEmail,
                'phone' => $hodPhone,
                'off'   => $office
            ]);
            log_audit_trail('DEPARTMENT_CREATED', 'departments', null, "Created department {$deptName} ({$deptCode}), HOD: {$hodName}", $currentUser['id']);
            set_flash_message('success', "Department '{$deptName}' added with HOD {$hodName}.");
            header('Location: ' . base_url('frontend/views/admin/departments.php'));
            exit;
        }
    }
}

// Handle Update HOD & Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_department') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $deptId = (int)$_POST['dept_id'];
        $hodName = sanitize_input($_POST['hod_name'] ?? '');
        $hodEmail = strtolower(sanitize_input($_POST['hod_email'] ?? ''));
        $hodPhone = sanitize_input($_POST['hod_phone'] ?? '');
        $office = sanitize_input($_POST['office_location'] ?? '');

        $stmtUp = $db->prepare("
            UPDATE departments 
            SET hod_name = :hod, hod_email = :email, hod_phone = :phone, office_location = :off 
            WHERE id = :id
        ");
        $stmtUp->execute([
            'hod'   => $hodName,
            'email' => $hodEmail,
            'phone' => $hodPhone,
            'off'   => $office,
            'id'    => $deptId
        ]);
        log_audit_trail('DEPARTMENT_UPDATED', 'departments', $deptId, "Updated HOD for department #{$deptId} to {$hodName}", $currentUser['id']);
        set_flash_message('success', "Department leadership updated for HOD {$hodName}.");
        header('Location: ' . base_url('frontend/views/admin/departments.php'));
        exit;
    }
}

// Fetch all schools and their departments along with staff counts
$schools = $db->query("SELECT * FROM schools ORDER BY id ASC")->fetchAll();

$stmtDepts = $db->query("
    SELECT d.*, s.name AS school_name, s.code AS school_code,
           COUNT(u.id) AS enrolled_staff_count
    FROM departments d
    JOIN schools s ON d.school_id = s.id
    LEFT JOIN users u ON d.id = u.department_id AND u.status = 'active'
    GROUP BY d.id, s.id, s.name, s.code
    ORDER BY s.id, d.name
");
$allDepts = $stmtDepts->fetchAll();

// Group departments by school
$deptsGrouped = [];
foreach ($allDepts as $d) {
    $deptsGrouped[$d['school_name']][] = $d;
}
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        EVELYN HONE COLLEGE ACADEMIC & ADMINISTRATIVE STRUCTURE
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Current Schools, Departments & Heads of Department (HODs)
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Official institutional faculties governing academic lecturers and administrative members of staff.
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('addDeptModal')">
      <i class="fa-solid fa-plus-circle"></i> Add Department
    </button>
  </div>
</div>

<!-- Schools Overview Grid -->
<div class="stats-grid" style="margin-bottom:28px;">
  <?php foreach ($schools as $sch): ?>
    <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
      <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
      <div class="stat-content">
        <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Faculty Code: <?= htmlspecialchars($sch['code']) ?></div>
        <div style="font-size:1.05rem; font-weight:800; color:var(--ehc-dark); margin-top:2px;"><?= htmlspecialchars($sch['name']) ?></div>
        <div style="font-size:0.78rem; color:var(--ehc-primary-light); font-weight:700; margin-top:4px;">
          <i class="fa-solid fa-user-tie"></i> Dean / Head: <?= htmlspecialchars($sch['dean_name'] ?? 'College Administration') ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Departments by School -->
<?php foreach ($deptsGrouped as $schoolName => $depts): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header-flex" style="border-bottom:1px solid var(--border-color); padding-bottom:14px; margin-bottom:16px;">
      <h3 class="card-title" style="font-size:1.15rem;">
        <i class="fa-solid fa-building-columns" style="color:var(--ehc-primary);"></i> <?= htmlspecialchars($schoolName) ?>
      </h3>
      <span class="badge badge-info"><?= count($depts) ?> Departments</span>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Code</th>
            <th>Department Name</th>
            <th>Head of Department (HOD)</th>
            <th>Contact Email</th>
            <th>Office Location</th>
            <th>Active Staff</th>
            <th class="no-print">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($depts as $d): ?>
            <tr>
              <td><span class="badge badge-secondary" style="font-family:monospace; font-weight:700;"><?= htmlspecialchars($d['code']) ?></span></td>
              <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
              <td>
                <div style="font-weight:700; color:var(--ehc-dark);">
                  <i class="fa-solid fa-user-check" style="color:#10B981; margin-right:4px;"></i> <?= htmlspecialchars($d['hod_name']) ?>
                </div>
                <?php if (!empty($d['hod_phone'])): ?>
                  <small style="color:var(--text-muted);"><?= htmlspecialchars($d['hod_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <a href="mailto:<?= htmlspecialchars($d['hod_email']) ?>" style="font-size:0.85rem; color:var(--ehc-primary);">
                  <?= htmlspecialchars($d['hod_email'] ?? 'info@evelynhone.edu.zm') ?>
                </a>
              </td>
              <td style="font-size:0.85rem; color:var(--text-muted);">
                <?= htmlspecialchars($d['office_location'] ?? 'Main Campus') ?>
              </td>
              <td>
                <span class="badge badge-success"><?= $d['enrolled_staff_count'] ?> Enrolled Staff</span>
              </td>
              <td class="no-print">
                <button type="button" class="btn btn-secondary btn-sm" onclick='openEditDeptModal(<?= json_encode($d) ?>)' title="Edit HOD / Department">
                  <i class="fa-solid fa-pen-to-square"></i> Edit HOD
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

<!-- ================= ADD DEPARTMENT MODAL ================= -->
<div class="modal-backdrop" id="addDeptModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-plus-circle" style="color:var(--ehc-primary);"></i> Add Academic Department</h3>
      <button type="button" class="modal-close" onclick="closeModal('addDeptModal')">&times;</button>
    </div>
    <form action="<?= base_url('frontend/views/admin/departments.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="create_department">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">School / Faculty <span class="required">*</span></label>
          <select name="school_id" class="form-select" required>
            <?php foreach ($schools as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Department Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Department of Biomedical Engineering" required>
          </div>

          <div class="form-group">
            <label class="form-label">Department Code <span class="required">*</span></label>
            <input type="text" name="code" class="form-control" placeholder="e.g. DBME" required style="text-transform:uppercase;">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Head of Department (HOD) Full Name <span class="required">*</span></label>
            <input type="text" name="hod_name" class="form-control" placeholder="e.g. Dr. Mwila Chanda" required>
          </div>

          <div class="form-group">
            <label class="form-label">HOD Official Email</label>
            <input type="email" name="hod_email" class="form-control" placeholder="m.chanda@evelynhone.edu.zm">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">HOD Phone Contact</label>
            <input type="text" name="hod_phone" class="form-control" placeholder="+260 977 123456">
          </div>

          <div class="form-group">
            <label class="form-label">Office Location / Room</label>
            <input type="text" name="office_location" class="form-control" placeholder="e.g. Science Block Room 14">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add Department</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= EDIT HOD MODAL ================= -->
<div class="modal-backdrop" id="editDeptModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-pen-to-square" style="color:var(--ehc-primary);"></i> Update Head of Department</h3>
      <button type="button" class="modal-close" onclick="closeModal('editDeptModal')">&times;</button>
    </div>
    <form action="<?= base_url('frontend/views/admin/departments.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="update_department">
      <input type="hidden" name="dept_id" id="editDeptId">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Department</label>
          <input type="text" id="editDeptNameDisplay" class="form-control" readonly style="background:var(--bg-subtle); font-weight:700;">
        </div>

        <div class="form-group">
          <label class="form-label">Head of Department (HOD) Full Name <span class="required">*</span></label>
          <input type="text" name="hod_name" id="editDeptHodName" class="form-control" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">HOD Email</label>
            <input type="email" name="hod_email" id="editDeptHodEmail" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">HOD Phone Contact</label>
            <input type="text" name="hod_phone" id="editDeptHodPhone" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Office Location</label>
          <input type="text" name="office_location" id="editDeptOffice" class="form-control">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Leadership Details</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openEditDeptModal(d) {
    document.getElementById('editDeptId').value = d.id;
    document.getElementById('editDeptNameDisplay').value = d.name + ' (' + d.code + ')';
    document.getElementById('editDeptHodName').value = d.hod_name;
    document.getElementById('editDeptHodEmail').value = d.hod_email || '';
    document.getElementById('editDeptHodPhone').value = d.hod_phone || '';
    document.getElementById('editDeptOffice').value = d.office_location || '';
    openModal('editDeptModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
