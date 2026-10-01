<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Campus Premise Gate Tap-In Terminal Simulator
 * Main Gate (Church Rd) & Dushambe Gate Entry Hardware Simulator
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/helpers.php';

$db = get_db();

// Fetch sample active staff for quick tap simulator buttons
$sampleStaff = $db->query("
    SELECT u.id, u.staff_id, u.full_name, u.role, u.employment_type, u.rfid_card_id, d.name AS dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.status = 'active'
    ORDER BY u.role, u.full_name
    LIMIT 8
")->fetchAll();

// Fetch last 6 gate clocking swipes
$recentSwipes = $db->query("
    SELECT c.*, u.full_name, u.staff_id, u.role, u.employment_type, d.name AS dept_name
    FROM clocking_records c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE c.clock_in_method = 'id_tap_gate' OR c.clock_out_method = 'id_tap_gate'
    ORDER BY COALESCE(c.clock_out, c.clock_in) DESC
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campus Entry ID Tap Terminal | Evelyn Hone College</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">

  <style>
    body {
      background: #080A0D;
      color: #E2E8F0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .terminal-container {
      max-width: 1050px;
      margin: 30px auto;
      padding: 0 20px;
      width: 100%;
    }
    .scanner-active-border {
      border: 2px dashed #00B0FF;
      border-radius: var(--radius-xl);
      padding: 30px;
      background: rgba(0, 176, 255, 0.03);
      position: relative;
      overflow: hidden;
    }
    .scanner-laser {
      position: absolute;
      top: 0;
      left: 2%;
      width: 96%;
      height: 3px;
      background: linear-gradient(90deg, transparent, #00B0FF, #38BDF8, #00B0FF, transparent);
      box-shadow: 0 0 15px #00B0FF;
      animation: scanLaserAnim 2.2s infinite ease-in-out;
    }
    @keyframes scanLaserAnim {
      0% { top: 5%; opacity: 0.3; }
      50% { top: 92%; opacity: 1; }
      100% { top: 5%; opacity: 0.3; }
    }
    .badge-chip {
      font-family: 'JetBrains Mono', monospace;
      padding: 4px 10px;
      border-radius: 6px;
      background: #1E293B;
      color: #38BDF8;
      font-size: 0.8rem;
      border: 1px solid #334155;
    }
  </style>
</head>
<body>

  <!-- Top Terminal Bar -->
  <div style="background:#0F1318; border-bottom:1px solid #1E2733; padding:16px 30px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
    <div style="display:flex; align-items:center; gap:16px;">
      <img src="<?= base_url('frontend/assets/images/ehc_logo.png') ?>" alt="Evelyn Hone College" style="width:40px; height:48px;">
      <div>
        <div style="font-weight:800; font-size:1.05rem; color:#fff; letter-spacing:0.5px;">EVELYN HONE COLLEGE OF APPLIED ARTS & COMMERCE</div>
        <div style="font-size:0.75rem; color:var(--ehc-primary-light); font-weight:700;">PREMISE ENTRY HARDWARE &bull; ID TAP TERMINAL</div>
      </div>
    </div>

    <div style="display:flex; align-items:center; gap:20px;">
      <div style="text-align:right;">
        <div id="liveClockDisplay" style="font-size:1.3rem; font-weight:800; color:#fff; font-family:'JetBrains Mono', monospace; line-height:1;">00:00:00</div>
        <div id="liveDateDisplay" style="font-size:0.75rem; color:#94A3B8;">Thursday, 1 October 2026</div>
      </div>
      <a href="<?= base_url('login.php') ?>" class="btn btn-secondary btn-sm" style="background:#1E2733; color:#fff; border-color:#334155;">
        <i class="fa-solid fa-arrow-left"></i> Staff Portal
      </a>
    </div>
  </div>

  <div class="terminal-container">

    <!-- Terminal Main Box -->
    <div class="terminal-screen" style="margin-bottom:28px;">
      
      <div class="terminal-header">
        <div>
          <span class="badge-chip"><i class="fa-solid fa-satellite-dish"></i> GATEWAY TERMINAL ONLINE</span>
          <h2 style="font-size:1.6rem; color:#FFFFFF; margin-top:8px;">Optical & RFID Campus Tap Reader</h2>
          <p style="font-size:0.86rem; color:#94A3B8;">Tap Staff ID Card, Barcode or RFID token upon entering or leaving college grounds.</p>
        </div>

        <div style="text-align:right;">
          <label style="font-size:0.75rem; color:#94A3B8; text-transform:uppercase; font-weight:700; display:block; margin-bottom:4px;">Entry / Exit Gate Point</label>
          <select id="terminalGateSelect" class="form-select" style="background:#161C24; color:#fff; border-color:#2A3644; font-size:0.85rem; padding:6px 12px; width:auto;">
            <option value="Main Gate - Church Rd" selected>Main Gate - Church Road (Primary Entry)</option>
            <option value="Dushambe Rd Gate">Dushambe Road Gate (Secondary)</option>
            <option value="Library & Media Pedestrian Turnstile">Library & Media Pedestrian Turnstile</option>
            <option value="Health Sciences Wing Gate">Health Sciences Wing Gate</option>
          </select>
        </div>
      </div>

      <div class="scanner-active-border" id="scannerBorder">
        <div class="scanner-laser"></div>

        <div style="text-align:center; padding:20px 0;">
          <div style="width:80px; height:80px; margin:0 auto 16px; border-radius:50%; background:rgba(0,176,255,0.1); border:2px solid #00B0FF; display:flex; align-items:center; justify-content:center; color:#38BDF8; font-size:2rem;">
            <i class="fa-solid fa-id-card"></i>
          </div>
          <h3 style="font-size:1.3rem; color:#FFFFFF;">Swipe Staff RFID Card or Enter Staff ID</h3>
          <p style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Supports Barcode Scanners, RFID Badges (13.56MHz), and manual keypad entry</p>

          <form id="tapTerminalForm" style="max-width:480px; margin:24px auto 0;" onsubmit="handleTapSubmit(event);">
            <div style="display:flex; gap:10px;">
              <input type="text" id="terminalInput" class="form-control" placeholder="Tap or enter e.g. EHC-LEC-101" autofocus required style="background:#131820; color:#fff; border-color:#2A3644; font-family:'JetBrains Mono', monospace; font-size:1.1rem; text-transform:uppercase; padding:12px 18px;">
              <button type="submit" class="btn btn-primary" style="padding:0 24px; font-weight:700;">
                <i class="fa-solid fa-bolt"></i> TAP
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Live Result Display Banner -->
      <div id="tapResultAlert" style="display:none; margin-top:24px; padding:20px; border-radius:var(--radius-lg); animation:modalSlideUp 0.3s ease;">
        <!-- Filled dynamically by JavaScript -->
      </div>

    </div>

    <!-- Quick Simulation Roster for Pair Testing -->
    <div class="card" style="background:#0F1318; border-color:#1E2733; color:#E2E8F0;">
      <div class="card-header-flex">
        <h4 style="color:#FFFFFF; font-size:1rem;">
          <i class="fa-solid fa-users" style="color:var(--ehc-primary);"></i> Quick Test Staff Cards (Click to Instant Tap In/Out)
        </h4>
        <span style="font-size:0.78rem; color:#94A3B8;">Simulates physical card swipe at Main Gate</span>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
        <?php foreach ($sampleStaff as $s): ?>
          <button type="button" onclick="simulateTap('<?= htmlspecialchars($s['staff_id']) ?>')" style="background:#161C24; border:1px solid #232D3B; border-radius:var(--radius-md); padding:12px 14px; text-align:left; cursor:pointer; transition:var(--transition); color:inherit;" onmouseover="this.style.borderColor='var(--ehc-cyan)';" onmouseout="this.style.borderColor='#232D3B';">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="badge-chip" style="font-size:0.7rem;"><?= htmlspecialchars($s['staff_id']) ?></span>
              <span style="font-size:0.68rem; font-weight:700; color:<?= $s['role'] === 'lecturer' ? 'var(--ehc-primary-light)' : 'var(--ehc-cyan)' ?>;">
                <?= ucfirst($s['role']) ?> (<?= str_replace('_', ' ', $s['employment_type']) ?>)
              </span>
            </div>
            <div style="font-weight:700; font-size:0.88rem; color:#FFFFFF; margin-top:6px;"><?= htmlspecialchars($s['full_name']) ?></div>
            <div style="font-size:0.75rem; color:#94A3B8;"><?= htmlspecialchars($s['dept_name'] ?? 'General') ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Recent Swipes at Gate -->
    <div class="card" style="background:#0F1318; border-color:#1E2733; color:#E2E8F0; margin-top:24px;">
      <div class="card-header-flex">
        <h4 style="color:#FFFFFF; font-size:1rem;">
          <i class="fa-solid fa-clock-rotate-left" style="color:var(--ehc-cyan);"></i> Live Campus Gate Activity Log
        </h4>
        <span style="font-size:0.78rem; color:#94A3B8;">Premises verification timestamped</span>
      </div>

      <div class="table-responsive" style="border-color:#1E2733;">
        <table class="table-custom" style="color:#E2E8F0;" id="recentSwipesTable">
          <thead>
            <tr style="background:#161C24;">
              <th style="color:#94A3B8; border-color:#1E2733;">Staff Member</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Role & Category</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Gate Location</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Clock In</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Clock Out</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Duration</th>
              <th style="color:#94A3B8; border-color:#1E2733;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSwipes as $sw): ?>
              <tr>
                <td style="border-color:#1E2733;">
                  <strong><?= htmlspecialchars($sw['full_name']) ?></strong><br>
                  <span style="font-size:0.75rem; color:#94A3B8; font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($sw['staff_id']) ?></span>
                </td>
                <td style="border-color:#1E2733;">
                  <span class="badge <?= $sw['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>">
                    <?= ucfirst($sw['role']) ?> (<?= str_replace('_', ' ', $sw['employment_type']) ?>)
                  </span>
                </td>
                <td style="border-color:#1E2733; font-size:0.85rem; color:#CBD5E1;">
                  <?= htmlspecialchars($sw['entry_gate'] ?? 'Main Gate - Church Rd') ?>
                </td>
                <td style="border-color:#1E2733; font-size:0.85rem; color:#10B981; font-family:'JetBrains Mono', monospace;">
                  <?= date('H:i:s', strtotime($sw['clock_in'])) ?>
                </td>
                <td style="border-color:#1E2733; font-size:0.85rem; color:#F59E0B; font-family:'JetBrains Mono', monospace;">
                  <?= !empty($sw['clock_out']) ? date('H:i:s', strtotime($sw['clock_out'])) : '<span style="color:#10B981;">(On Premise)</span>' ?>
                </td>
                <td style="border-color:#1E2733; font-weight:700;">
                  <?= !empty($sw['clock_out']) ? number_format($sw['total_hours'], 2) . ' hrs' : '-' ?>
                </td>
                <td style="border-color:#1E2733;">
                  <span class="badge <?= $sw['status'] === 'in_progress' ? 'badge-success' : 'badge-secondary' ?>">
                    <?= $sw['status'] === 'in_progress' ? 'ON CAMPUS' : 'COMPLETED' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
  <script>
    function simulateTap(staffId) {
      document.getElementById('terminalInput').value = staffId;
      document.getElementById('tapTerminalForm').dispatchEvent(new Event('submit'));
    }

    function handleTapSubmit(e) {
      e.preventDefault();
      const identifier = document.getElementById('terminalInput').value.trim();
      const gate = document.getElementById('terminalGateSelect').value;
      const alertBox = document.getElementById('tapResultAlert');
      const scannerBox = document.getElementById('scannerBorder');

      if (!identifier) return;

      scannerBox.classList.add('scanning');

      const formData = new FormData();
      formData.append('action', 'terminal_tap');
      formData.append('identifier', identifier);
      formData.append('gate', gate);

      fetch('<?= base_url('backend/actions/clock_action.php') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        scannerBox.classList.remove('scanning');
        alertBox.style.display = 'block';

        if (data.success) {
          if (data.type === 'clock_in') {
            alertBox.style.background = 'rgba(16, 185, 129, 0.15)';
            alertBox.style.border = '1px solid #10B981';
            alertBox.style.color = '#A7F3D0';
            alertBox.innerHTML = `
              <div style="display:flex; align-items:center; gap:20px;">
                <div style="width:60px; height:60px; border-radius:50%; background:#10B981; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.8rem;">
                  <i class="fa-solid fa-circle-check"></i>
                </div>
                <div style="flex:1;">
                  <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#34D399; letter-spacing:1px;">ACCESS GRANTED &bull; CLOCKED IN</div>
                  <h3 style="font-size:1.3rem; color:#FFFFFF; margin:2px 0;">${data.user.name} (${data.user.staff_id})</h3>
                  <div style="font-size:0.85rem; color:#E2E8F0;">
                    ${data.user.role} &bull; ${data.user.category} &bull; <strong>${data.user.department}</strong>
                  </div>
                  <div style="font-size:0.78rem; color:#94A3B8; margin-top:4px;">
                    <i class="fa-solid fa-location-dot"></i> Verified on College Premises (${data.gate}) at ${data.time}
                  </div>
                </div>
              </div>
            `;
          } else {
            alertBox.style.background = 'rgba(245, 158, 11, 0.15)';
            alertBox.style.border = '1px solid #F59E0B';
            alertBox.style.color = '#FDE68A';
            alertBox.innerHTML = `
              <div style="display:flex; align-items:center; gap:20px;">
                <div style="width:60px; height:60px; border-radius:50%; background:#F59E0B; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.8rem;">
                  <i class="fa-solid fa-door-open"></i>
                </div>
                <div style="flex:1;">
                  <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#FBBF24; letter-spacing:1px;">DEPARTURE RECORDED &bull; CLOCKED OUT</div>
                  <h3 style="font-size:1.3rem; color:#FFFFFF; margin:2px 0;">${data.user.name} (${data.user.staff_id})</h3>
                  <div style="font-size:0.85rem; color:#E2E8F0;">
                    ${data.user.role} &bull; ${data.user.category} &bull; Shift Duration: <strong>${data.hours} hours</strong>
                  </div>
                  <div style="font-size:0.78rem; color:#94A3B8; margin-top:4px;">
                    <i class="fa-solid fa-clock"></i> Tapped out at ${data.gate} at ${data.time}
                  </div>
                </div>
              </div>
            `;
          }

          document.getElementById('terminalInput').value = '';
          // Optionally reload after 3 seconds to update the table
          setTimeout(() => location.reload(), 2500);
        } else {
          alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
          alertBox.style.border = '1px solid #EF4444';
          alertBox.style.color = '#FECACA';
          alertBox.innerHTML = `
            <div style="display:flex; align-items:center; gap:16px;">
              <i class="fa-solid fa-triangle-exclamation" style="font-size:1.8rem; color:#EF4444;"></i>
              <div>
                <strong style="font-size:1.05rem;">Access Denied</strong>
                <p style="margin:2px 0 0; font-size:0.85rem;">${data.message}</p>
              </div>
            </div>
          `;
        }
      })
      .catch(err => {
        scannerBox.classList.remove('scanning');
        alertBox.style.display = 'block';
        alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
        alertBox.style.border = '1px solid #EF4444';
        alertBox.style.color = '#FECACA';
        alertBox.textContent = 'Network communication error with terminal server.';
      });
    }
  </script>
</body>
</html>
