<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Master Layout Footer
 */

if (!defined('EHC_SYSTEM')) {
    define('EHC_SYSTEM', true);
}
?>
    </main>

    <!-- MASTER FOOTER -->
    <footer class="app-footer">
      <div>
        <strong>Evelyn Hone College of Applied Arts and Commerce</strong> &mdash; 
        <span>Clocking, Timesheets & Billing Automation System</span>
      </div>
      <div>
        <span style="margin-right:16px;"><i class="fa-solid fa-location-dot" style="color:var(--ehc-primary);"></i> Church Rd / Dushambe Rd, Lusaka</span>
        <span>Version 2.0 (PostgreSQL)</span>
      </div>
    </footer>

  </div><!-- /.main-wrapper -->
</div><!-- /.app-container -->

<!-- App Scripts -->
<script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
<?php if (isset($extraScripts)): ?>
  <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
