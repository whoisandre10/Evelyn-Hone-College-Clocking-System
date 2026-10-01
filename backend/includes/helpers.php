<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Core Helper Functions & Calculation Engine
 */

if (!defined('EHC_SYSTEM')) {
    define('EHC_SYSTEM', true);
}

require_once __DIR__ . '/../config/database.php';

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Clean & sanitize user input
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash alert message
 */
function set_flash_message(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash alert messages
 */
function get_flash_messages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Log action into institutional audit trail
 */
function log_audit_trail(string $action, string $entity, ?int $entity_id, string $details, ?int $user_id = null): void {
    try {
        $db = get_db();
        $userName = 'System / Terminal';
        $staffId = 'SYSTEM';

        if ($user_id) {
            $stmt = $db->prepare("SELECT full_name, staff_id FROM users WHERE id = :id");
            $stmt->execute(['id' => $user_id]);
            $u = $stmt->fetch();
            if ($u) {
                $userName = $u['full_name'];
                $staffId = $u['staff_id'];
            }
        } elseif (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $userName = $_SESSION['full_name'] ?? 'Authenticated User';
            $staffId = $_SESSION['staff_id'] ?? 'USER';
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System';

        $stmt = $db->prepare("
            INSERT INTO audit_trail (user_id, user_name, staff_id, action, entity, entity_id, details, ip_address, user_agent)
            VALUES (:uid, :uname, :staff_id, :action, :entity, :eid, :details, :ip, :ua)
        ");
        $stmt->execute([
            'uid'      => $user_id,
            'uname'    => $userName,
            'staff_id' => $staffId,
            'action'   => $action,
            'entity'   => $entity,
            'eid'      => $entity_id,
            'details'  => $details,
            'ip'       => $ip,
            'ua'       => $ua
        ]);
    } catch (Exception $e) {
        error_log("Failed to log audit trail: " . $e->getMessage());
    }
}

/**
 * Send in-app notification to a user
 */
function send_notification(int $user_id, string $title, string $message, string $type = 'info', ?string $link = null): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (:uid, :title, :message, :type, :link)
        ");
        return $stmt->execute([
            'uid'     => $user_id,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link
        ]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system setting by key
 */
function get_system_setting(string $key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :k");
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $cache[$key] = $val;
            return $val;
        }
    } catch (Exception $e) {
        error_log("Error retrieving setting {$key}: " . $e->getMessage());
    }
    return $default;
}

/**
 * Format currency in Zambian Kwacha (ZMW)
 */
function format_currency($amount): string {
    $symbol = get_system_setting('currency_symbol', 'ZMW');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date nicely
 */
function format_date($date): string {
    if (empty($date)) return '-';
    return date('d M Y', strtotime($date));
}

/**
 * Format time nicely
 */
function format_time($time): string {
    if (empty($time)) return '-';
    return date('H:i', strtotime($time));
}

/**
 * Format timestamp nicely
 */
function format_datetime($datetime): string {
    if (empty($datetime)) return '-';
    return date('d M Y, H:i', strtotime($datetime));
}

/**
 * Get active clocking status for a user
 */
function get_user_current_clocking_status(int $user_id): array {
    $db = get_db();
    
    // Check if there is an active session (clock_out IS NULL)
    $stmt = $db->prepare("
        SELECT * FROM clocking_records 
        WHERE user_id = :uid AND clock_out IS NULL 
        ORDER BY clock_in DESC 
        LIMIT 1
    ");
    $stmt->execute(['uid' => $user_id]);
    $active = $stmt->fetch();

    // Calculate total hours worked today
    $stmtToday = $db->prepare("
        SELECT COALESCE(SUM(total_hours), 0) AS hours_today
        FROM clocking_records
        WHERE user_id = :uid AND clock_date = CURRENT_DATE
    ");
    $stmtToday->execute(['uid' => $user_id]);
    $hoursToday = (float)$stmtToday->fetchColumn();

    // Check if currently on premises
    $isClockedIn = !empty($active);
    $elapsedMinutes = 0;
    if ($isClockedIn && !empty($active['clock_in'])) {
        $clockInTime = strtotime($active['clock_in']);
        $elapsedMinutes = max(0, round((time() - $clockInTime) / 60));
    }

    return [
        'is_clocked_in'    => $isClockedIn,
        'current_record'   => $active ?: null,
        'clock_in_time'    => $active['clock_in'] ?? null,
        'entry_gate'       => $active['entry_gate'] ?? null,
        'premise_verified' => $active['premise_verified'] ?? false,
        'elapsed_minutes'  => $elapsedMinutes,
        'elapsed_hours'    => round($elapsedMinutes / 60, 2),
        'hours_today'      => $hoursToday
    ];
}

/**
 * Haversine formula to compute distance in meters from Evelyn Hone College campus center
 */
function verify_premise_coordinates(float $lat, float $lng): array {
    $campusLat = (float)get_system_setting('premise_latitude', -15.421528);
    $campusLng = (float)get_system_setting('premise_longitude', 28.293319);
    $radius = (float)get_system_setting('premise_radius_meters', 1000);

    $earthRadius = 6371000; // in meters
    $dLat = deg2rad($lat - $campusLat);
    $dLng = deg2rad($lng - $campusLng);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($campusLat)) * cos(deg2rad($lat)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    $onPremise = $distance <= $radius;

    return [
        'is_on_premise' => $onPremise,
        'distance_m'    => round($distance),
        'radius_m'      => $radius,
        'campus_name'   => get_system_setting('premise_name', 'Evelyn Hone College')
    ];
}

/**
 * AUTOMATED BILLING CALCULATION ENGINE
 * Computes exact staff earnings, regular hours, overtime, and claims totals
 */
function calculate_staff_billing(int $user_id, string $start_date, string $end_date): array {
    $db = get_db();

    // 1. Fetch user details and rates
    $stmt = $db->prepare("
        SELECT u.*, d.name AS department_name, s.name AS school_name 
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN schools s ON d.school_id = s.id
        WHERE u.id = :uid
    ");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new InvalidArgumentException("User not found for billing calculation.");
    }

    $hourlyRate = (float)$user['hourly_rate'];
    $standardDaily = (float)get_system_setting('standard_daily_hours', 8.0);
    $overtimeMult = (float)get_system_setting('overtime_rate_multiplier', 1.5);

    // 2. Fetch all completed clocking records in the period
    $stmtClock = $db->prepare("
        SELECT clock_date, SUM(total_hours) as daily_total
        FROM clocking_records
        WHERE user_id = :uid 
          AND clock_date >= :sdate 
          AND clock_date <= :edate
          AND status IN ('completed', 'adjusted')
        GROUP BY clock_date
        ORDER BY clock_date ASC
    ");
    $stmtClock->execute([
        'uid'   => $user_id,
        'sdate' => $start_date,
        'edate' => $end_date
    ]);
    $records = $stmtClock->fetchAll();

    $totalClockedHours = 0.0;
    $regularHours = 0.0;
    $overtimeHours = 0.0;
    $daysWorked = count($records);

    foreach ($records as $rec) {
        $daily = (float)$rec['daily_total'];
        $totalClockedHours += $daily;

        if ($user['employment_type'] === 'full_time') {
            if ($daily > $standardDaily) {
                $regularHours += $standardDaily;
                $overtimeHours += ($daily - $standardDaily);
            } else {
                $regularHours += $daily;
            }
        } else {
            // For part-time, all clocked hours are payable at their contracted hourly rate
            $regularHours += $daily;
        }
    }

    // 3. Compute Base Pay & Overtime Pay
    if ($user['employment_type'] === 'part_time') {
        $basePay = $regularHours * $hourlyRate;
        $overtimePay = $overtimeHours * ($hourlyRate * $overtimeMult);
    } else {
        // Full time base pay calculation (regular hours * base rate)
        $basePay = $regularHours * $hourlyRate;
        $overtimePay = $overtimeHours * ($hourlyRate * $overtimeMult);
    }

    // 4. Fetch Approved Claims in Period
    $stmtClaims = $db->prepare("
        SELECT 
            claim_type,
            COUNT(*) as claim_count,
            SUM(quantity) as total_units,
            SUM(total_amount) as sum_amount
        FROM claims
        WHERE user_id = :uid 
          AND claim_date >= :sdate 
          AND claim_date <= :edate
          AND status IN ('approved', 'paid')
        GROUP BY claim_type
    ");
    $stmtClaims->execute([
        'uid'   => $user_id,
        'sdate' => $start_date,
        'edate' => $end_date
    ]);
    $claimsBreakdown = $stmtClaims->fetchAll();

    $claimsTotalAmount = 0.0;
    foreach ($claimsBreakdown as $c) {
        $claimsTotalAmount += (float)$c['sum_amount'];
    }

    $grossBilling = $basePay + $overtimePay + $claimsTotalAmount;

    return [
        'user'                 => $user,
        'period_start'         => $start_date,
        'period_end'           => $end_date,
        'days_worked'          => $daysWorked,
        'total_clocked_hours'  => round($totalClockedHours, 2),
        'regular_hours'        => round($regularHours, 2),
        'overtime_hours'       => round($overtimeHours, 2),
        'hourly_rate'          => $hourlyRate,
        'overtime_multiplier'  => $overtimeMult,
        'base_pay'             => round($basePay, 2),
        'overtime_pay'         => round($overtimePay, 2),
        'claims_breakdown'     => $claimsBreakdown,
        'claims_total'         => round($claimsTotalAmount, 2),
        'gross_billing_amount' => round($grossBilling, 2)
    ];
}
