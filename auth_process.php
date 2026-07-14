<?php
require_once 'db_connect.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// 1. Verify CSRF Token
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
    header("Location: login.php");
    exit();
}

$action = $_POST['action'] ?? '';

// --- REGISTRATION PROCESSING ---
if ($action === 'register') {
    $full_name = trim($_POST['full_name'] ?? '');
    $matric_no = trim($_POST['matric_no'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Backend Validation
    if (empty($full_name) || empty($matric_no) || empty($department) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: register.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: register.php");
        exit();
    }

    // Validate Matric Number format (Adeleke University YY/XXXX)
    if (!preg_match('/^\d{2}\/\d{4}$/', $matric_no)) {
        $_SESSION['error'] = "Matric Number must be in the format YY/XXXX (e.g., 22/1314).";
        header("Location: register.php");
        exit();
    }

    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: register.php");
        exit();
    }

    // Check if email or matric number already exists
    try {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR matric_no_staff_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $email, $matric_no);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $_SESSION['error'] = "An account with this email or matric number already exists.";
            mysqli_stmt_close($stmt);
            header("Location: register.php");
            exit();
        }
        mysqli_stmt_close($stmt);

        // Hash the password securely using standard bcrypt (default in PHP 8)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student';

        // Insert into the database
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, matric_no_staff_id, email, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssss", $full_name, $matric_no, $email, $hashed_password, $role, $department);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Registration successful! You can now log in.";
            mysqli_stmt_close($stmt);
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration failed. Please try again later.";
            mysqli_stmt_close($stmt);
            header("Location: register.php");
            exit();
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Registration DB Error: " . $e->getMessage());
        $_SESSION['error'] = "A database error occurred. Please try again later.";
        header("Location: register.php");
        exit();
    }
}

// --- LOGIN PROCESSING ---
if ($action === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Backend Validation
    if (empty($identifier) || empty($password)) {
        $_SESSION['error'] = "Please enter both identifier and password.";
        header("Location: login.php");
        exit();
    }

    try {
        // Find user by email OR matric_no/staff_id
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, matric_no_staff_id, email, password, role, department FROM users WHERE email = ? OR matric_no_staff_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // Verify Password
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent Session Fixation attacks
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['matric_no_staff_id'] = $user['matric_no_staff_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];

                $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';
                mysqli_stmt_close($stmt);

                // Redirect based on role or explicit secure local redirect target
                if (!empty($redirect) && (strpos($redirect, 'http://') === false && strpos($redirect, 'https://') === false && strpos($redirect, '//') !== 0)) {
                    header("Location: " . $redirect);
                } else if ($user['role'] === 'student') {
                    header("Location: student_dashboard.php");
                } else {
                    header("Location: admin_dashboard.php");
                }
                exit();
            }
        }
        
        // Invalid credentials fallback (same generic error message to prevent account harvesting)
        $_SESSION['error'] = "Invalid credentials. Please verify your details.";
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
        header("Location: login.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        error_log("Login DB Error: " . $e->getMessage());
        $_SESSION['error'] = "A database error occurred. Please try again later.";
        header("Location: login.php");
        exit();
    }
}

// Redirect back to login if action was not recognized
header("Location: login.php");
exit();
