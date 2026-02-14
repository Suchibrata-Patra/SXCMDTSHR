<?php
/**
 * ============================================================
 * SECURE LOGOUT HANDLER
 * ============================================================
 * Properly logs out user and records activity
 * ============================================================
 */
session_start();
require_once 'db_config.php';
require_once 'login_auth_helper.php';

// Record logout activity
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $sessionId = session_id();
    recordLogoutActivity($sessionId, 'user_logout');
    
    error_log("User logout: {$_SESSION['smtp_user']} from IP " . getClientIP());
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Start new session for logout message
session_start();
$_SESSION['logout_message'] = 'You have been logged out successfully.';

// Redirect to login
header("Location: login.php?logged_out=1");
exit();
?>