/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Frontend Application Logic & AJAX Handlers
 */

document.addEventListener('DOMContentLoaded', function () {
  initLiveClock();
  initSidebarToggle();
  initPasswordToggles();
  initClaimsCalculator();
  initTableSearch();
  initAlertDismissals();
});

/**
 * 1. Live Ticking Clock (Lusaka CAT Time)
 */
function initLiveClock() {
  const clockElement = document.getElementById('liveClockDisplay');
  const dateElement = document.getElementById('liveDateDisplay');

  if (!clockElement) return;

  function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    clockElement.textContent = `${hours}:${minutes}:${seconds}`;

    if (dateElement) {
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      dateElement.textContent = now.toLocaleDateString('en-GB', options);
    }
  }

  updateClock();
  setInterval(updateClock, 1000);
}

/**
 * 2. Mobile Sidebar Toggle
 */
function initSidebarToggle() {
  const toggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.querySelector('.sidebar');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    // Close when clicking outside on mobile
    document.addEventListener('click', function (e) {
      if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }
}

/**
 * 3. Password Visibility Toggle
 */
function initPasswordToggles() {
  const toggleButtons = document.querySelectorAll('.toggle-password');
  toggleButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (input) {
        if (input.type === 'password') {
          input.type = 'text';
          this.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          input.type = 'password';
          this.classList.replace('fa-eye-slash', 'fa-eye');
        }
      }
    });
  });
}

/**
 * 4. Claims Auto Rate & Total Amount Calculator
 */
function initClaimsCalculator() {
  const typeSelect = document.getElementById('claimTypeSelect');
  const qtyInput = document.getElementById('claimQuantityInput');
  const rateInput = document.getElementById('claimRateInput');
  const totalDisplay = document.getElementById('claimTotalDisplay');
  const totalHidden = document.getElementById('claimTotalAmount');
  const unitLabel = document.getElementById('claimUnitLabel');

  if (!typeSelect || !qtyInput || !rateInput) return;

  // Standard predefined rates from Evelyn Hone College settings
  const ratesMap = {
    'exam_invigilation': { rate: 120.00, unit: 'Hours' },
    'exam_marking': { rate: 35.00, unit: 'Exam Scripts' },
    'overtime': { rate: 150.00, unit: 'Hours' },
    'extra_lecture': { rate: 180.00, unit: 'Hours' },
    'weekend_duty': { rate: 250.00, unit: 'Duty Sessions' },
    'special_assignment': { rate: 200.00, unit: 'Units / Days' }
  };

  function updateRates() {
    const selected = typeSelect.value;
    if (ratesMap[selected]) {
      rateInput.value = ratesMap[selected].rate.toFixed(2);
      if (unitLabel) unitLabel.textContent = ratesMap[selected].unit;
    }
    computeTotal();
  }

  function computeTotal() {
    const qty = parseFloat(qtyInput.value) || 0;
    const rate = parseFloat(rateInput.value) || 0;
    const total = qty * rate;
    if (totalDisplay) totalDisplay.textContent = 'ZMW ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (totalHidden) totalHidden.value = total.toFixed(2);
  }

  typeSelect.addEventListener('change', updateRates);
  qtyInput.addEventListener('input', computeTotal);
  rateInput.addEventListener('input', computeTotal);

  // Initial calculation
  updateRates();
}

/**
 * 5. Instant Table Search & Filter
 */
function initTableSearch() {
  const searchInput = document.getElementById('tableSearchInput');
  const filterSelect = document.getElementById('tableFilterSelect');
  const table = document.querySelector('.table-searchable');

  if (!searchInput || !table) return;

  function filterTable() {
    const query = searchInput.value.toLowerCase().trim();
    const filterVal = filterSelect ? filterSelect.value.toLowerCase().trim() : '';
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
      // Don't hide empty placeholder rows
      if (row.classList.contains('empty-row')) return;

      const text = row.textContent.toLowerCase();
      const statusCell = row.querySelector('[data-filter-value]');
      const rowStatus = statusCell ? statusCell.getAttribute('data-filter-value').toLowerCase() : '';

      const matchesQuery = query === '' || text.includes(query);
      const matchesFilter = filterVal === '' || filterVal === 'all' || rowStatus === filterVal;

      if (matchesQuery && matchesFilter) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  searchInput.addEventListener('input', filterTable);
  if (filterSelect) {
    filterSelect.addEventListener('change', filterTable);
  }
}

/**
 * 6. Alert Box Dismissal
 */
function initAlertDismissals() {
  document.querySelectorAll('.alert-close-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const alert = this.closest('.alert');
      if (alert) {
        alert.style.transition = 'opacity 0.25s, transform 0.25s';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 250);
      }
    });
  });
}

/**
 * 7. Modal Open & Close Utilities
 */
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
}

// Close modal when clicking backdrop outside modal dialog
window.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});

/**
 * 8. Confirmation Dialog Helper
 */
function confirmAction(message, onConfirm) {
  if (window.confirm(message)) {
    if (typeof onConfirm === 'function') {
      onConfirm();
    }
    return true;
  }
  return false;
}
