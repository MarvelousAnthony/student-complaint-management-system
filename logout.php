<?php
/**
 * logout.php
 * 
 * Securely terminates the session, clears all session variables, 
 * deletes session cookies, and redirects the user back to the login screen.
 */

require_once 'db_connect.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Delete the session cookie if it is set
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

// 3. Destroy the session
session_destroy();

// 4. Start a clean session to carry the success message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['success'] = "You have been logged out successfully.";

// 5. Redirect to the unified login page (preserving redirection context if active)
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
if (!empty($redirect) && (strpos($redirect, 'http://') === false && strpos($redirect, 'https://') === false && strpos($redirect, '//') !== 0)) {
    header("Location: login.php?redirect=" . urlencode($redirect));
} else {
    header("Location: login.php");
}
exit();
