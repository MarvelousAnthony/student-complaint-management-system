<?php
require_once 'db_connect.php';

// If user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$csrf_token = get_csrf_token();

// Fetch session messages
$error_msg = $_SESSION['error'] ?? null;
$success_msg = $_SESSION['success'] ?? null;
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';

// Clear session messages so they don't persist on refresh
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Student Complaint Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Theme overrides and dynamic theme switch -->
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
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 text-slate-100 relative overflow-x-hidden">
    
    <!-- Background Decorative Elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] rounded-full bg-violet-600/10 blur-[130px] pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md z-10">
        <div class="flex justify-center">
            <!-- Icon / Logo representation -->
            <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 shadow-lg shadow-indigo-500/25">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold tracking-tight text-white">Sign in to your portal</h2>
        <p class="mt-2 text-center text-sm text-slate-400">
            For Students, Admins, and Super Admins
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md z-10">
        <div class="bg-slate-900/60 backdrop-blur-xl py-8 px-4 border border-slate-800 rounded-2xl shadow-2xl sm:px-10">
            
            <!-- Alert Display -->
            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Client-side Validation Error Placeholder -->
            <div id="js-error-alert" class="hidden mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span id="js-error-message"></span>
            </div>

            <form id="login-form" action="auth_process.php" method="POST" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <!-- Action Parameter -->
                <input type="hidden" name="action" value="login">
                <!-- Redirect Target -->
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                <!-- Identifier -->
                <div>
                    <label for="identifier" class="block text-sm font-medium text-slate-300">
                        Email, Matric No, or Staff ID
                    </label>
                    <div class="mt-1">
                        <input id="identifier" name="identifier" type="text" autocomplete="username" required 
                            class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                            placeholder="Email, Matric No (YY/XXXX) or Staff ID (YY/AU/MMMM)">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center">
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                        <a href="forgot_password.php" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition-colors">
                            Forgot Password?
                        </a>
                    </div>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                            class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-150 active:scale-[0.99] shadow-indigo-600/20">
                        Sign In
                    </button>
                </div>
            </form>

            <div class="mt-6 border-t border-slate-800 pt-6 text-center">
                <p class="text-sm text-slate-400">
                    Are you a student and don't have an account? 
                    <a href="register.php" class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors block sm:inline mt-1 sm:mt-0">
                        Register here
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Client-side Validation Logic -->
    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const identifier = document.getElementById('identifier').value.trim();
            const password = document.getElementById('password').value;
            
            const errorAlert = document.getElementById('js-error-alert');
            const errorMessage = document.getElementById('js-error-message');
            
            let errors = [];

            if (!identifier) {
                errors.push('Please enter your Email, Matric No, or Staff ID.');
            }

            if (!password) {
                errors.push('Please enter your password.');
            }

            if (errors.length > 0) {
                e.preventDefault(); // Prevent form submission
                errorMessage.textContent = errors.join(' ');
                errorAlert.classList.remove('hidden');
            } else {
                errorAlert.classList.add('hidden');
            }
        });

        // Force browser password managers and autofill to clear on landing (e.g., after registration redirect)
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const idInput = document.getElementById('identifier');
                const passInput = document.getElementById('password');
                if (idInput) idInput.value = '';
                if (passInput) passInput.value = '';
            }, 100);
        });
    </script>
</body>
</html>
