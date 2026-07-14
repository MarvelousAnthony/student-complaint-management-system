<?php
require_once 'db_connect.php';

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$error_msg = $_SESSION['error'] ?? null;
$success_msg = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$step = 1; // Default step (Verification)
$verified_user_id = $_SESSION['reset_verified_user_id'] ?? null;

if ($verified_user_id) {
    $step = 2; // Move to password reset if already verified
}

// ----------------------------------------------------
// 1. Process Step 1: Verification (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_identity') {
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: forgot_password.php");
        exit();
    }

    $email = trim($_POST['email'] ?? '');
    $matric_staff_id = strtoupper(trim($_POST['matric_staff_id'] ?? ''));

    if (empty($email) || empty($matric_staff_id)) {
        $_SESSION['error'] = "Both Email and Matric/Staff ID are required.";
        header("Location: forgot_password.php");
        exit();
    }

    try {
        // Query user with exact email AND matric_no_staff_id
        $stmt = mysqli_prepare($conn, "SELECT id, full_name FROM users WHERE email = ? AND matric_no_staff_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $email, $matric_staff_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($res)) {
            // Identity verified! Store in session and proceed to step 2
            $_SESSION['reset_verified_user_id'] = $user['id'];
            $_SESSION['success'] = "Identity verified! Please set your new password below.";
            mysqli_stmt_close($stmt);
            header("Location: forgot_password.php");
            exit();
        } else {
            $_SESSION['error'] = "No account found matching this Email and Matric Number/Staff ID combination.";
            mysqli_stmt_close($stmt);
            header("Location: forgot_password.php");
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Verification query failed: " . $e->getMessage());
        $_SESSION['error'] = "An internal error occurred. Please try again later.";
        header("Location: forgot_password.php");
        exit();
    }
}

// ----------------------------------------------------
// 2. Process Step 2: Password Reset (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: forgot_password.php");
        exit();
    }

    if (!$verified_user_id) {
        $_SESSION['error'] = "Unauthorized access attempt. Please verify your identity first.";
        header("Location: forgot_password.php");
        exit();
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all password fields.";
        header("Location: forgot_password.php");
        exit();
    }

    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: forgot_password.php");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match. Please enter them carefully.";
        header("Location: forgot_password.php");
        exit();
    }

    try {
        // Hash the new password
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user record
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed, $verified_user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Clear verification session data
        unset($_SESSION['reset_verified_user_id']);

        $_SESSION['success'] = "Password reset successful! You can now log in with your new password.";
        header("Location: login.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        error_log("Password update failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update password. Please try again.";
        header("Location: forgot_password.php");
        exit();
    }
}

// ----------------------------------------------------
// 3. Cancel / Restart Flow (GET Action)
// ----------------------------------------------------
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_verified_user_id']);
    header("Location: forgot_password.php");
    exit();
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Adeleke University CMS</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Theme & Logout Utility Styles/Scripts -->
    <link rel="stylesheet" href="theme.css?v=1.3">
    <script src="theme_logout.js" defer></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex items-center justify-center p-6 relative overflow-y-auto">

    <!-- Ambient glowing backgrounds for high aesthetics -->
    <div class="absolute top-10 left-10 w-72 h-72 bg-indigo-650/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-purple-650/5 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-8 backdrop-blur-xl relative z-10">
        
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600 mb-4 shadow-lg shadow-indigo-600/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m0 0a2 2 0 01-2 2m0-2a2 2 0 00-2 2m0-2a2 2 0 002-2m-2 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-white tracking-tight">Account Recovery</h2>
            <p class="text-xs text-slate-450 mt-1">Reset your Adeleke University CMS password securely</p>
        </div>

        <!-- Alert messages -->
        <?php if ($error_msg): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-450 text-xs font-semibold flex items-start space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-450 text-xs font-semibold flex items-start space-x-2">
                <svg xmlns="http://www.w3.org/2000/xl" class="h-4 w-4 mt-0.5 flex-shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- STEP 1: VERIFY IDENTITY -->
        <?php if ($step === 1): ?>
            <form method="POST" action="forgot_password.php" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="verify_identity">

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Registered Email Address</label>
                    <input id="email" name="email" type="email" required placeholder="e.g. name@university.edu"
                        class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>

                <!-- Matric No or Staff ID -->
                <div>
                    <label for="matric_staff_id" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Matric No or Staff ID</label>
                    <input id="matric_staff_id" name="matric_staff_id" type="text" required placeholder="e.g. 24/0452 or 24/AU/0142"
                        class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>

                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-3 px-4 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-650/15 focus:outline-none focus:ring-2 focus:ring-indigo-500 active:scale-[0.98] transition-all">
                        Verify Identity
                    </button>
                </div>
            </form>

        <!-- STEP 2: ENTER NEW PASSWORD -->
        <?php else: ?>
            <form method="POST" action="forgot_password.php" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="reset_password">

                <!-- New Password -->
                <div>
                    <label for="new_password" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">New Password</label>
                    <input id="new_password" name="new_password" type="password" required placeholder="Min 8 characters"
                        class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Confirm New Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required placeholder="Retype password"
                        class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                </div>

                <div class="flex flex-col space-y-3">
                    <button type="submit" 
                        class="w-full flex justify-center py-3 px-4 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-650/15 focus:outline-none focus:ring-2 focus:ring-indigo-500 active:scale-[0.98] transition-all">
                        Change Password
                    </button>
                    
                    <a href="forgot_password.php?cancel=1" 
                        class="w-full flex justify-center py-3 px-4 rounded-xl text-xs font-semibold text-slate-400 bg-slate-800 hover:bg-slate-750 hover:text-white transition-all text-center">
                        Cancel Reset
                    </a>
                </div>
            </form>
        <?php endif; ?>

        <!-- Link back to login -->
        <div class="mt-8 border-t border-slate-850 pt-6 text-center">
            <a href="login.php" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition-colors">
                Back to Sign In
            </a>
        </div>

    </div>

</body>
</html>
