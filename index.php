<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Main Gateway & Welcome Portal
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

// If already authenticated, redirect to corresponding role dashboard
if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        redirect_to_dashboard($user['role']);
        exit;
    }
}

// Fetch general campus live statistics from PostgreSQL
$db = get_db();
$stats = [
    'total_staff'    => (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'clocked_today'  => (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM clocking_records WHERE clock_date = CURRENT_DATE")->fetchColumn(),
    'currently_on'   => (int)$db->query("SELECT COUNT(*) FROM clocking_records WHERE clock_out IS NULL AND clock_date = CURRENT_DATE")->fetchColumn(),
    'departments'    => (int)$db->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Evelyn Hone College | Staff Clocking & Billing System</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">

  <style>
    .hero-section {
      min-height: 100vh;
      background: radial-gradient(circle at 10% 20%, #1A1E24 0%, #0F1216 90%);
      color: #FFFFFF;
      display: flex;
      flex-direction: column;
    }
    .hero-nav {
      padding: 24px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .hero-brand {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .hero-brand img {
      width: 52px;
      height: 60px;
      object-fit: contain;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.5));
    }
    .hero-brand-name {
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      line-height: 1.1;
    }
    .hero-brand-tagline {
      font-size: 0.75rem;
      color: var(--ehc-primary-light);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      font-weight: 600;
    }
    .hero-main {
      flex: 1;
      max-width: 1200px;
      margin: 0 auto;
      padding: 60px 24px;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      align-items: center;
      gap: 60px;
    }
    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 16px;
      border-radius: 30px;
      background: rgba(230, 81, 0, 0.15);
      border: 1px solid rgba(245, 124, 0, 0.35);
      color: var(--ehc-primary-light);
      font-size: 0.82rem;
      font-weight: 700;
      margin-bottom: 20px;
    }
    .hero-heading {
      font-size: 3rem;
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -1px;
      margin-bottom: 20px;
      color: #FFFFFF;
    }
    .hero-heading span.accent {
      background: linear-gradient(135deg, var(--ehc-primary-light), var(--ehc-primary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-desc {
      font-size: 1.05rem;
      color: #94A3B8;
      line-height: 1.65;
      margin-bottom: 32px;
    }
    .hero-cta-group {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .hero-stats-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
      margin-top: 36px;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .hero-stat-box {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: var(--radius-md);
      padding: 16px;
    }
    .hero-stat-num {
      font-size: 1.75rem;
      font-weight: 800;
      color: #FFFFFF;
      line-height: 1;
    }
    .hero-stat-text {
      font-size: 0.78rem;
      color: #94A3B8;
      margin-top: 4px;
      text-transform: uppercase;
      font-weight: 600;
    }
    .hero-card-portal {
      background: #FFFFFF;
      color: var(--text-main);
      border-radius: var(--radius-xl);
      padding: 40px;
      box-shadow: 0 25px 60px rgba(0,0,0,0.5);
      position: relative;
    }
    @media (max-width: 992px) {
      .hero-main {
        grid-template-columns: 1fr;
        padding: 40px 20px;
        gap: 40px;
      }
      .hero-heading {
        font-size: 2.3rem;
      }
    }
  </style>
</head>
<body class="hero-section">

  <!-- Top Navigation -->
  <header class="hero-nav">
    <div class="hero-brand">
      <img src="<?= base_url('frontend/assets/images/ehc_logo.png') ?>" alt="Evelyn Hone College Emblem">
      <div>
        <div class="hero-brand-name">EVELYN HONE COLLEGE</div>
        <div class="hero-brand-tagline">Knowledge with Integrity &bull; Lusaka, Zambia</div>
      </div>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
      <a href="<?= base_url('frontend/views/terminal/tap_terminal.php') ?>" class="btn btn-secondary btn-sm" target="_blank">
        <i class="fa-solid fa-id-badge" style="color:var(--ehc-cyan);"></i> Campus Gate Reader
      </a>
      <a href="<?= base_url('login.php') ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </a>
    </div>
  </header>

  <!-- Hero Content -->
  <main class="hero-main">
    <div>
      <div class="hero-badge-pill">
        <i class="fa-solid fa-graduation-cap"></i> Official Academic & Administrative Clocking
      </div>
      <h1 class="hero-heading">
        Automated Staff Time Tracking & <span class="accent">Transparent Billing</span>
      </h1>
      <p class="hero-desc">
        Eliminating paper timesheets at Evelyn Hone College. Seamlessly manage on-premise attendance, exam marking & invigilation claims, multi-level department approvals, and automated Kwacha payroll calculation with institutional accountability.
      </p>

      <div class="hero-cta-group">
        <a href="<?= base_url('login.php') ?>" class="btn btn-primary btn-lg">
          <i class="fa-solid fa-arrow-right-to-bracket"></i> Access Staff Portal
        </a>
        <a href="<?= base_url('register.php') ?>" class="btn btn-secondary btn-lg" style="background:rgba(255,255,255,0.08); color:#fff; border-color:rgba(255,255,255,0.15);">
          <i class="fa-solid fa-user-plus"></i> Staff Enrollment
        </a>
        <a href="<?= base_url('frontend/views/terminal/tap_terminal.php') ?>" class="btn btn-secondary btn-lg" target="_blank" style="background:rgba(0,176,255,0.12); color:#38BDF8; border-color:rgba(0,176,255,0.3);">
          <i class="fa-solid fa-fingerprint"></i> Gate Tap-In Simulator
        </a>
      </div>

      <!-- Campus Live Statistics -->
      <div class="hero-stats-row">
        <div class="hero-stat-box">
          <div class="hero-stat-num"><?= number_format($stats['total_staff']) ?></div>
          <div class="hero-stat-text">Active Faculty & Staff</div>
        </div>
        <div class="hero-stat-box">
          <div class="hero-stat-num" style="color:#10B981;"><?= number_format($stats['currently_on']) ?></div>
          <div class="hero-stat-text">Live On Campus Today</div>
        </div>
        <div class="hero-stat-box">
          <div class="hero-stat-num" style="color:var(--ehc-primary-light);"><?= number_format($stats['clocked_today']) ?></div>
          <div class="hero-stat-text">Clocked Sessions Today</div>
        </div>
        <div class="hero-stat-box">
          <div class="hero-stat-num"><?= number_format($stats['departments']) ?></div>
          <div class="hero-stat-text">Academic & Admin Depts</div>
        </div>
      </div>
    </div>

    <!-- Quick Role Access Cards -->
    <div class="hero-card-portal">
      <div style="text-align:center; margin-bottom:24px;">
        <h3 style="font-size:1.35rem; color:var(--ehc-dark);">Select Your Staff Role</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">Secure role-based access for academic and institutional staff</p>
      </div>

      <div style="display:flex; flex-direction:column; gap:14px;">
        <!-- Lecturer Card -->
        <a href="<?= base_url('login.php?role=lecturer') ?>" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-lg); text-decoration:none; color:inherit; transition:var(--transition); background:var(--bg-subtle);" onmouseover="this.style.borderColor='var(--ehc-primary)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='none';">
          <div style="width:48px; height:48px; border-radius:12px; background:rgba(230,81,0,0.1); color:var(--ehc-primary); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-chalkboard-user"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--ehc-dark);">Academic Lecturers</div>
            <div style="font-size:0.78rem; color:var(--text-muted);">Full-Time & Part-Time &bull; Invigilation, Exam Marking & Overtime Claims</div>
          </div>
          <i class="fa-solid fa-chevron-right" style="color:var(--text-subtle);"></i>
        </a>

        <!-- Support Staff Card -->
        <a href="<?= base_url('login.php?role=staff') ?>" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-lg); text-decoration:none; color:inherit; transition:var(--transition); background:var(--bg-subtle);" onmouseover="this.style.borderColor='var(--ehc-cyan)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='none';">
          <div style="width:48px; height:48px; border-radius:12px; background:rgba(0,176,255,0.1); color:var(--ehc-cyan-dark); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-users-gear"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--ehc-dark);">Administrative & Support Staff</div>
            <div style="font-size:0.78rem; color:var(--text-muted);">Full-Time & Part-Time &bull; Duty Tracking & Overtime Claims</div>
          </div>
          <i class="fa-solid fa-chevron-right" style="color:var(--text-subtle);"></i>
        </a>

        <!-- Administration Card -->
        <a href="<?= base_url('login.php?role=admin') ?>" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-lg); text-decoration:none; color:inherit; transition:var(--transition); background:var(--bg-subtle);" onmouseover="this.style.borderColor='var(--ehc-dark)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='none';">
          <div style="width:48px; height:48px; border-radius:12px; background:rgba(18,20,23,0.08); color:var(--ehc-dark); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-user-shield"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700; font-size:0.95rem; color:var(--ehc-dark);">College Administration & HODs</div>
            <div style="font-size:0.78rem; color:var(--text-muted);">Principal, HR, Finance & Accounts, and HOD Approvals</div>
          </div>
          <i class="fa-solid fa-chevron-right" style="color:var(--text-subtle);"></i>
        </a>
      </div>

      <div style="margin-top:24px; text-align:center;">
        <span style="font-size:0.82rem; color:var(--text-muted);">Need help or forgot credentials? </span>
        <a href="<?= base_url('forgot_password.php') ?>" style="font-size:0.82rem; font-weight:700;">Reset Password</a>
      </div>
    </div>
  </main>

  <footer style="padding:20px; text-align:center; font-size:0.8rem; color:#64748B; border-top:1px solid rgba(255,255,255,0.08);">
    Evelyn Hone College of Applied Arts and Commerce &bull; Church Road / Dushambe Road, P.O. Box 30029, Lusaka, Zambia &bull; 
    <span style="color:var(--ehc-primary-light);">Knowledge with Integrity</span>
  </footer>

</body>
</html>
