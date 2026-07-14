<?php
require_once 'db_connect.php';

// Secure access: Only Super Admins can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    $_SESSION['error'] = "Access denied. Only Super Administrators can manage staff accounts.";
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];
$admin_identifier = $_SESSION['matric_no_staff_id'];

$error_msg = $_SESSION['error'] ?? null;
$success_msg = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Handle registration of new staff (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_staff') {
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: manage_staff.php");
        exit();
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $staff_id = strtoupper(trim($_POST['staff_id'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $role = trim($_POST['role'] ?? 'admin');

    // Validation
    if (empty($full_name) || empty($staff_id) || empty($email) || empty($password) || empty($department)) {
        $_SESSION['error'] = "All fields are required to register staff.";
        header("Location: manage_staff.php");
        exit();
    }

    // Check staff ID format (YY/AU/MMMM)
    if (!preg_match('/^\d{2}\/AU\/\d{4}$/', $staff_id)) {
        $_SESSION['error'] = "Staff ID must match the Adeleke format (e.g. 24/AU/0452).";
        header("Location: manage_staff.php");
        exit();
    }

    // Check password length
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: manage_staff.php");
        exit();
    }

    // Check role limits
    if (!in_array($role, ['admin', 'super_admin'])) {
        $_SESSION['error'] = "Invalid system role selected.";
        header("Location: manage_staff.php");
        exit();
    }

    try {
        // Check if email or staff ID already exists
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR matric_no_staff_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "ss", $email, $staff_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $_SESSION['error'] = "A staff member with this Email or Staff ID is already registered.";
            mysqli_stmt_close($check);
            header("Location: manage_staff.php");
            exit();
        }
        mysqli_stmt_close($check);

        // Hash password and insert
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $insert = mysqli_prepare($conn, "INSERT INTO users (full_name, matric_no_staff_id, email, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert, "ssssss", $full_name, $staff_id, $email, $hashed, $role, $department);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);

        $_SESSION['success'] = "Staff account for '{$full_name}' successfully created.";
        header("Location: manage_staff.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        error_log("Failed to register staff: " . $e->getMessage());
        $_SESSION['error'] = "A database error occurred. Please try again.";
        header("Location: manage_staff.php");
        exit();
    }
}

// Handle deletion of staff (POST to avoid GET request attacks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    // CSRF check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: manage_staff.php");
        exit();
    }

    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id === $admin_id) {
        $_SESSION['error'] = "You cannot delete your own Super Admin account!";
        header("Location: manage_staff.php");
        exit();
    }

    try {
        $delete = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role IN ('admin', 'super_admin')");
        mysqli_stmt_bind_param($delete, "i", $target_id);
        mysqli_stmt_execute($delete);
        mysqli_stmt_close($delete);

        $_SESSION['success'] = "Staff account deleted successfully.";
        header("Location: manage_staff.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to delete staff account: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete account due to system dependencies.";
        header("Location: manage_staff.php");
        exit();
    }
}

// Fetch all registered staff members
$staff_members = [];
try {
    $res = mysqli_query($conn, "SELECT id, full_name, matric_no_staff_id, email, role, department, created_at FROM users WHERE role IN ('admin', 'super_admin') ORDER BY role DESC, created_at ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $staff_members[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to fetch staff directory: " . $e->getMessage());
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff | Adeleke University CMS</title>
    <link rel="icon" type="image/jpeg" href="favicon.jpg">
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
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between hidden md:flex z-20">
        <div>
            <div class="h-16 flex items-center px-6 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-650 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="font-bold text-white tracking-wide">CMS Admin</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-6 px-4 space-y-1">
                <a href="admin_dashboard.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    Dashboard
                </a>
                <a href="generate_report.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m32-2v-2a4 4 0 00-4-4h-3.5a4 4 0 00-4 4v2m6.5-12a3 3 0 11-6 0 3 3 0 016 0zM14 8a3 3 0 11-6 0 3 3 0 016 0zm-3 8h3a4 4 0 014 4v2H5v-2a4 4 0 014-4z" />
                    </svg>
                    Report Center
                </a>
                <a href="manage_staff.php" class="bg-indigo-650 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Manage Staff
                </a>
                <a href="admin_notifications.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    Notifications
                </a>
                <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Security Settings
                </a>
            </nav>
        </div>

        <!-- User Profile Footer -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/50">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-xs text-slate-500 truncate uppercase"><?php echo htmlspecialchars($admin_identifier); ?></p>
                </div>
            </div>
            <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-450 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content Workspace -->
    <div class="flex-1 flex flex-col overflow-hidden relative bg-slate-950">
        <!-- Header -->
        <header class="h-16 border-b border-slate-800 bg-slate-950 flex items-center justify-between px-6 z-10">
            <h1 class="text-lg font-bold text-white">Staff Account Administration</h1>
            <div>
                <span class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 text-indigo-400 rounded-full font-semibold uppercase tracking-wider">
                    Super Admin Console
                </span>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Notifications alerts -->
            <?php if ($error_msg): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-4 py-3 rounded-xl text-xs font-semibold max-w-5xl">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl text-xs font-semibold max-w-5xl">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Add Staff Member Card Form -->
            <div class="max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h2 class="text-base font-bold text-white mb-2">Register New Staff / Admin</h2>
                <p class="text-xs text-slate-400 mb-6">Create authorized account parameters. Newly registered staff can login immediately using their email or Staff ID.</p>

                <form method="POST" action="manage_staff.php" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="register_staff">

                    <!-- Name -->
                    <div>
                        <label for="full_name" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required placeholder="e.g. Prof. James Coker"
                            class="appearance-none block w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Staff ID -->
                    <div>
                        <label for="staff_id" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Staff ID (YY/AU/MMMM)</label>
                        <input type="text" id="staff_id" name="staff_id" required placeholder="e.g. 24/AU/0142"
                            class="appearance-none block w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="e.g. staff@adeleke.edu.ng"
                            class="appearance-none block w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Min 8 characters"
                            class="appearance-none block w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Department -->
                    <div>
                        <label for="department" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Assigned Unit/Dept</label>
                        <div class="relative">
                            <select id="department" name="department" required
                                class="appearance-none block w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="">-- Choose Unit --</option>
                                <option value="Academic Unit">Academic Unit</option>
                                <option value="Bursary / Finance">Bursary / Finance</option>
                                <option value="Student Affairs / Hostel">Student Affairs / Hostel</option>
                                <option value="Works & Maintenance">Works & Maintenance</option>
                                <option value="ICT Unit">ICT Unit</option>
                                <option value="Health Services">Health Services</option>
                                <option value="Chaplaincy Unit">Chaplaincy Unit</option>
                                <option value="General Admin">General Admin</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-xs font-semibold text-slate-450 uppercase tracking-wider mb-2">Authorization Role</label>
                        <div class="relative">
                            <select id="role" name="role" required
                                class="appearance-none block w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="admin">Regular Admin</option>
                                <option value="super_admin">Super Administrator</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="sm:col-span-2 pt-2 flex justify-end">
                        <button type="submit" 
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
                            Register Staff Member
                        </button>
                    </div>
                </form>
            </div>

            <!-- Staff Directory List -->
            <div class="max-w-5xl">
                <h3 class="text-base font-bold text-white mb-4">Staff Directory</h3>
                
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-xl">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-850">
                            <thead class="bg-slate-900/90 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Staff ID</th>
                                    <th class="px-6 py-4">Email</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4">Role</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-sm text-slate-300">
                                <?php if (count($staff_members) > 0): ?>
                                    <?php foreach ($staff_members as $member): ?>
                                        <tr class="hover:bg-slate-850/40 transition-colors">
                                            <td class="px-6 py-4 font-semibold text-slate-200"><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td class="px-6 py-4 font-mono font-semibold text-indigo-400"><?php echo htmlspecialchars($member['matric_no_staff_id']); ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($member['department']); ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($member['role'] === 'super_admin'): ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-rose-500/10 text-rose-450 border-rose-500/20">
                                                        Super Admin
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-slate-800 text-slate-400 border-slate-700">
                                                        Admin
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <?php if ($member['id'] != $admin_id): ?>
                                                    <form method="POST" action="manage_staff.php" onsubmit="return confirm('Are you sure you want to permanently delete this staff member?');" class="inline-block">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="delete_staff">
                                                        <input type="hidden" name="target_id" value="<?php echo $member['id']; ?>">
                                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold bg-rose-500/10 text-rose-450 hover:bg-rose-500/20 rounded-lg border border-rose-500/10 transition-all duration-150">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-500 italic">Self (Active)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">No staff members found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
