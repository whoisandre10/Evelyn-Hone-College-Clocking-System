<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Logout Handler
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';

logout_user('manual');
header('Location: ' . base_url('login.php?msg=logged_out'));
exit;
