<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Authentication & Session Management
 */

if (!defined('EHC_SYSTEM')) {
    define('EHC_SYSTEM', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

// Configure secure session parameters before starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_name('EHC_SESSID');
    session_start();
}

/**
 * Get base URL path for the project
 */
function base_url(string $path = ''): string {
    // Detect subfolder relative to webroot
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Normalize path
    $base = rtrim(str_replace('\\', '/', $scriptDir), '/');
    
    // Find where the project root evelyn-hone-clocking or similar is
    if (strpos($base, '/evelyn-hone-clocking') !== false) {
        $base = substr($base, 0, strpos($base, '/evelyn-hone-clocking') + strlen('/evelyn-hone-clocking'));
    } else {
        // If run with php -S localhost:8000 inside the project root
        $base = '';
    }
    
    $path = ltrim($path, '/');
    return $base ? $base . ($path ? '/' . $path : '') : '/' . $path;
}

/**
 * Check if a user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

/**
 * Get the currently logged-in user record
 */
function current_user(): ?array {
    static $cached_user = null;
    if ($cached_user !== null) {
        return $cached_user;
    }
    
    if (!is_logged_in()) {
        return null;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT u.*, d.name AS department_name, d.code AS department_code, d.hod_name,
                   s.name AS school_name, s.code AS school_code
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN schools s ON d.school_id = s.id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            logout_user('user_not_found');
            return null;
        }

        // Single Active Session Verification:
        // "it should be able to log out when someone logs in"
        if ($user['current_session_token'] !== $_SESSION['session_token']) {
            logout_user('logged_in_elsewhere');
            header('Location: ' . base_url('login.php?error=concurrent_session'));
            exit;
        }

        // Check if account was suspended
        if ($user['status'] !== 'active') {
            logout_user('account_' . $user['status']);
            header('Location: ' . base_url('login.php?error=account_' . $user['status']));
            exit;
        }

        $cached_user = $user;
        return $cached_user;
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Enforce that the user is logged in
 */
function require_login(): array {
    if (!is_logged_in()) {
        $target = base_url('login.php?error=login_required&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        if (!headers_sent()) {
            header('Location: ' . $target);
        } else {
            echo "<script>window.location.replace(" . json_encode($target) . ");</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target) . "'></noscript>";
        }
        exit;
    }

    $user = current_user();
    if (!$user) {
        $target = base_url('login.php?error=session_invalid');
        if (!headers_sent()) {
            header('Location: ' . $target);
        } else {
            echo "<script>window.location.replace(" . json_encode($target) . ");</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target) . "'></noscript>";
        }
        exit;
    }

    return $user;
}

/**
 * Enforce role permissions
 * @param array|string $allowed_roles
 */
function require_role($allowed_roles): array {
    $user = require_login();
    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];

    if (!in_array($user['role'], $allowed, true)) {
        // Forbidden - redirect to their proper dashboard with notice
        set_flash_message('danger', 'Unauthorized access! You do not have permission to view that page.');
        redirect_to_dashboard($user['role']);
        exit;
    }

    return $user;
}

/**
 * Redirect user to their corresponding role dashboard
 */
function redirect_to_dashboard(string $role): void {
    $target = base_url('index.php');
    switch ($role) {
        case 'admin':
            $target = base_url('frontend/views/admin/dashboard.php');
            break;
        case 'lecturer':
            $target = base_url('frontend/views/lecturer/dashboard.php');
            break;
        case 'staff':
            $target = base_url('frontend/views/staff/dashboard.php');
            break;
    }

    if (!headers_sent()) {
        header('Location: ' . $target);
    } else {
        echo "<script>window.location.replace(" . json_encode($target) . ");</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target) . "'></noscript>";
    }
    exit;
}

/**
 * Perform login for a user with single session enforcement
 */
function login_user(array $user): void {
    $db = get_db();
    
    // Generate new unique cryptographic session token
    $sessionToken = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // 1. Invalidate any existing active sessions for this user in user_sessions table
    $stmt = $db->prepare("
        UPDATE user_sessions 
        SET is_active = FALSE, logout_time = CURRENT_TIMESTAMP, logout_reason = 'logged_in_from_new_device'
        WHERE user_id = :user_id AND is_active = TRUE
    ");
    $stmt->execute(['user_id' => $user['id']]);

    // 2. Insert new active session
    $stmt = $db->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, is_active)
        VALUES (:user_id, :token, :ip, :ua, TRUE)
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'token'   => $sessionToken,
        'ip'      => $ip,
        'ua'      => $userAgent
    ]);

    // 3. Update users table with current session token and last login
    $stmt = $db->prepare("
        UPDATE users 
        SET current_session_token = :token, last_login = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = :user_id
    ");
    $stmt->execute([
        'token'   => $sessionToken,
        'user_id' => $user['id']
    ]);

    // 4. Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // 5. Store session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['staff_id'] = $user['staff_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['employment_type'] = $user['employment_type'];
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['login_time'] = time();

    // 6. Log in audit trail
    log_audit_trail(
        'USER_LOGIN',
        'users',
        $user['id'],
        "User logged in ({$user['full_name']} - {$user['role']}). Previous sessions invalidated.",
        $user['id']
    );
}

/**
 * Logout the user cleanly
 */
function logout_user(string $reason = 'manual'): void {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $token = $_SESSION['session_token'] ?? null;

        try {
            $db = get_db();
            if ($token) {
                $stmt = $db->prepare("
                    UPDATE user_sessions 
                    SET is_active = FALSE, logout_time = CURRENT_TIMESTAMP, logout_reason = :reason 
                    WHERE session_token = :token
                ");
                $stmt->execute(['reason' => $reason, 'token' => $token]);
            }

            // Clear current token on users table
            $stmt = $db->prepare("UPDATE users SET current_session_token = NULL WHERE id = :id AND current_session_token = :token");
            $stmt->execute(['id' => $userId, 'token' => $token]);

            log_audit_trail('USER_LOGOUT', 'users', $userId, "User logged out (Reason: {$reason})", $userId);
        } catch (Exception $e) {
            error_log("Error during logout: " . $e->getMessage());
        }
    }

    // Destroy session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
