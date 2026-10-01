<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Master Layout Header & Sidebar
 */

if (!defined('EHC_SYSTEM')) {
    define('EHC_SYSTEM', true);
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Ensure user is authenticated before rendering dashboard pages
$currentUser = require_login();

// Current page identifier for active nav link
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Fetch unread notifications count
$db = get_db();
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
$stmtNotif->execute(['uid' => $currentUser['id']]);
$unreadNotifCount = (int)$stmtNotif->fetchColumn();

// Fetch live clocking status for current user
$clockStatus = get_user_current_clocking_status($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Evelyn Hone College of Applied Arts and Commerce - Institutional Clocking, Timesheet, Claims, and Billing Automation System">
  <meta name="csrf-token" content="<?= htmlspecialchars(generate_csrf_token()) ?>">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Evelyn Hone College Clocking System</title>

  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- College Brand Stylesheet -->
  <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">
</head>
<body>
<div class="app-container">

  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="<?= base_url('frontend/assets/images/ehc_logo.png') ?>" alt="Evelyn Hone College Logo" class="logo-img">
      <div class="logo-text">
        <span class="college-name">EVELYN HONE</span>
        <span class="system-title">Clocking System</span>
      </div>
    </div>

    <!-- Active User Card -->
    <div class="sidebar-user">
      <div class="user-avatar-badge">
        <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
      </div>
      <div class="user-info-text">
        <div class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
        <span class="user-role-badge">
          <?= htmlspecialchars($currentUser['role']) ?> • <?= str_replace('_', ' ', htmlspecialchars($currentUser['employment_type'])) ?>
        </span>
      </div>
    </div>

    <!-- Role-Based Navigation Items -->
    <nav class="sidebar-nav">
      
      <?php if ($currentUser['role'] === 'admin'): ?>
        <!-- ================= ADMIN NAVIGATION ================= -->
        <div class="nav-section-title">Institutional Overview</div>
        <a href="<?= base_url('frontend/views/admin/dashboard.php') ?>" class="nav-item <?= $currentScript === 'dashboard.php' && $currentDir === 'admin' ? 'active' : '' ?>">
          <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/clocking_records.php') ?>" class="nav-item <?= $currentScript === 'clocking_records.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-clock"></i> <span>Clocking Records</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/timesheets_approval.php') ?>" class="nav-item <?= $currentScript === 'timesheets_approval.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-file-signature"></i> <span>Timesheets Approval</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/claims_management.php') ?>" class="nav-item <?= $currentScript === 'claims_management.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-file-invoice-dollar"></i> <span>Claims Verification</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/billing_engine.php') ?>" class="nav-item <?= $currentScript === 'billing_engine.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-calculator"></i> <span>Billing Engine</span>
        </a>

        <div class="nav-section-title">Administration</div>
        <a href="<?= base_url('frontend/views/admin/users.php') ?>" class="nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i> <span>Manage Staff</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/departments.php') ?>" class="nav-item <?= $currentScript === 'departments.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-building-columns"></i> <span>Schools & HODs</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/feedback_collaboration.php') ?>" class="nav-item <?= $currentScript === 'feedback_collaboration.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-comments"></i> <span>Staff Feedback</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/reports.php') ?>" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-chart-pie"></i> <span>Executive Reports</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/audit_logs.php') ?>" class="nav-item <?= $currentScript === 'audit_logs.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-shield-halved"></i> <span>Audit Trail</span>
        </a>
        <a href="<?= base_url('frontend/views/admin/system_settings.php') ?>" class="nav-item <?= $currentScript === 'system_settings.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-sliders"></i> <span>System Settings</span>
        </a>

      <?php elseif ($currentUser['role'] === 'lecturer'): ?>
        <!-- ================= LECTURER NAVIGATION ================= -->
        <div class="nav-section-title">Faculty Portal</div>
        <a href="<?= base_url('frontend/views/lecturer/dashboard.php') ?>" class="nav-item <?= $currentScript === 'dashboard.php' && $currentDir === 'lecturer' ? 'active' : '' ?>">
          <i class="fa-solid fa-gauge-high"></i> <span>Lecturer Dashboard</span>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/clocking.php') ?>" class="nav-item <?= $currentScript === 'clocking.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-stopwatch"></i> <span>Live Clocking</span>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/claims.php') ?>" class="nav-item <?= $currentScript === 'claims.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-hand-holding-dollar"></i> <span>Teaching & Exam Claims</span>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/timesheets.php') ?>" class="nav-item <?= $currentScript === 'timesheets.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i> <span>Timesheets & Hours</span>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/feedback.php') ?>" class="nav-item <?= $currentScript === 'feedback.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-comments"></i> <span>Collaboration & Inquiries</span>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/notifications.php') ?>" class="nav-item <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-bell"></i> <span>Notifications</span>
          <?php if ($unreadNotifCount > 0): ?>
            <span class="badge-pill"><?= $unreadNotifCount ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= base_url('frontend/views/lecturer/profile.php') ?>" class="nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-id-card"></i> <span>My Profile</span>
        </a>

      <?php else: ?>
        <!-- ================= STAFF NAVIGATION ================= -->
        <div class="nav-section-title">Staff Portal</div>
        <a href="<?= base_url('frontend/views/staff/dashboard.php') ?>" class="nav-item <?= $currentScript === 'dashboard.php' && $currentDir === 'staff' ? 'active' : '' ?>">
          <i class="fa-solid fa-gauge-high"></i> <span>Staff Dashboard</span>
        </a>
        <a href="<?= base_url('frontend/views/staff/clocking.php') ?>" class="nav-item <?= $currentScript === 'clocking.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-stopwatch"></i> <span>Live Clocking</span>
        </a>
        <a href="<?= base_url('frontend/views/staff/claims.php') ?>" class="nav-item <?= $currentScript === 'claims.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-hand-holding-dollar"></i> <span>Overtime & Duty Claims</span>
        </a>
        <a href="<?= base_url('frontend/views/staff/timesheets.php') ?>" class="nav-item <?= $currentScript === 'timesheets.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i> <span>My Timesheets</span>
        </a>
        <a href="<?= base_url('frontend/views/staff/feedback.php') ?>" class="nav-item <?= $currentScript === 'feedback.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-comments"></i> <span>Staff Inquiries</span>
        </a>
        <a href="<?= base_url('frontend/views/staff/notifications.php') ?>" class="nav-item <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-bell"></i> <span>Notifications</span>
          <?php if ($unreadNotifCount > 0): ?>
            <span class="badge-pill"><?= $unreadNotifCount ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= base_url('frontend/views/staff/profile.php') ?>" class="nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
          <i class="fa-solid fa-id-card"></i> <span>My Profile</span>
        </a>
      <?php endif; ?>

      <!-- CAMPUS GATE ENTRY ID TAP TERMINAL QUICK ACCESS -->
      <div class="nav-section-title">Premises Hardware</div>
      <a href="<?= base_url('frontend/views/terminal/tap_terminal.php') ?>" class="nav-item" target="_blank">
        <i class="fa-solid fa-id-badge" style="color:var(--ehc-cyan);"></i> <span>Gate Tap-In Reader</span>
      </a>

      <!-- LOGOUT ACTION -->
      <div class="nav-section-title">Session</div>
      <a href="<?= base_url('logout.php') ?>" class="nav-item" onclick="return confirm('Are you sure you want to log out of Evelyn Hone College System?');">
        <i class="fa-solid fa-right-from-bracket" style="color:#EF4444;"></i> <span style="color:#EF4444;">Sign Out</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div style="font-size:0.75rem; color:#64748B; text-align:center;">
        Evelyn Hone College &copy; <?= date('Y') ?><br>
        <span style="color:var(--ehc-primary-light); font-weight:600;">Knowledge with Integrity</span>
      </div>
    </div>
  </aside>

  <!-- MAIN WRAPPER -->
  <div class="main-wrapper">
    
    <!-- MASTER TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="breadcrumb-trail">
          <a href="<?= base_url('index.php') ?>">EHC Portal</a>
          <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
          <span><?= ucfirst(htmlspecialchars($currentUser['role'])) ?></span>
          <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i>
          <span class="active"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></span>
        </div>
      </div>

      <div class="topbar-right">
        <!-- Live Campus Premise Status Indicator -->
        <div class="premise-status-indicator <?= $clockStatus['is_clocked_in'] ? 'on-premise' : 'off-premise' ?>">
          <span class="status-pulse-dot <?= $clockStatus['is_clocked_in'] ? 'pulse' : '' ?>"></span>
          <span>
            <?= $clockStatus['is_clocked_in'] ? 'On Premise (' . htmlspecialchars($clockStatus['entry_gate'] ?? 'Main Campus') . ')' : 'Clocked Out' ?>
          </span>
        </div>

        <div class="topbar-actions">
          <!-- Notification Bell -->
          <?php
            $notifLink = base_url('frontend/views/' . $currentUser['role'] . '/notifications.php');
            if ($currentUser['role'] === 'admin') {
                $notifLink = base_url('frontend/views/admin/feedback_collaboration.php');
            }
          ?>
          <a href="<?= $notifLink ?>" class="topbar-icon-btn" title="View Notifications">
            <i class="fa-regular fa-bell"></i>
            <?php if ($unreadNotifCount > 0): ?>
              <span class="badge-dot"></span>
            <?php endif; ?>
          </a>

          <!-- Profile Link -->
          <?php
            $profLink = base_url('frontend/views/' . $currentUser['role'] . '/profile.php');
            if ($currentUser['role'] === 'admin') {
                $profLink = base_url('frontend/views/admin/profile.php');
            }
          ?>
          <a href="<?= $profLink ?>" class="topbar-icon-btn" title="Manage Profile">
            <i class="fa-regular fa-user"></i>
          </a>

          <!-- Fast Logout -->
          <a href="<?= base_url('logout.php') ?>" class="topbar-icon-btn" title="Sign Out" onclick="return confirm('Sign out of Evelyn Hone College Clocking System?');">
            <i class="fa-solid fa-power-off" style="color:var(--status-danger);"></i>
          </a>
        </div>
      </div>
    </header>

    <!-- CONTENT BODY -->
    <main class="content-body">
      
      <!-- RENDER FLASH MESSAGES -->
      <?php foreach (get_flash_messages() as $flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
          <div>
            <?php if ($flash['type'] === 'success'): ?>
              <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i>
            <?php elseif ($flash['type'] === 'danger'): ?>
              <i class="fa-solid fa-circle-exclamation" style="margin-right:8px;"></i>
            <?php elseif ($flash['type'] === 'warning'): ?>
              <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i>
            <?php else: ?>
              <i class="fa-solid fa-circle-info" style="margin-right:8px;"></i>
            <?php endif; ?>
            <?= htmlspecialchars($flash['message']) ?>
          </div>
          <button type="button" class="alert-close-btn" title="Dismiss">&times;</button>
        </div>
      <?php endforeach; ?>
