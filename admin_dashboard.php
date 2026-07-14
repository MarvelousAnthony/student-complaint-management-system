<?php
require_once 'db_connect.php';

// Secure access: admin or super_admin role check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];

// 1. Fetch Metrics counts
$total_count = 0;
$today_count = 0;
$pending_count = 0;
$inprogress_count = 0;
$resolved_count = 0;

$metric_where = "";
$metric_params = [];
$metric_types = "";

if ($admin_role === 'admin') {
    $admin_dept = $_SESSION['department'] ?? '';
    if (!empty($admin_dept)) {
        $metric_where = " WHERE (assigned_dept = ? OR assigned_dept IS NULL)";
        $metric_params = [$admin_dept];
        $metric_types = "s";
    }
}

try {
    // Total Complaints
    $query = "SELECT COUNT(*) as cnt FROM complaints" . $metric_where;
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $total_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Complaints Today
    $query = "SELECT COUNT(*) as cnt FROM complaints WHERE DATE(created_at) = CURDATE()" . (!empty($metric_where) ? " AND (assigned_dept = ? OR assigned_dept IS NULL)" : "");
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $today_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Pending Complaints (pending only)
    $query = "SELECT COUNT(*) as cnt FROM complaints WHERE status = 'pending'" . (!empty($metric_where) ? " AND (assigned_dept = ? OR assigned_dept IS NULL)" : "");
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $pending_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // In Progress Complaints (under_review, in_progress)
    $query = "SELECT COUNT(*) as cnt FROM complaints WHERE status IN ('under_review', 'in_progress')" . (!empty($metric_where) ? " AND (assigned_dept = ? OR assigned_dept IS NULL)" : "");
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $inprogress_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Resolved Complaints (resolved, closed)
    $query = "SELECT COUNT(*) as cnt FROM complaints WHERE status IN ('resolved', 'closed')" . (!empty($metric_where) ? " AND (assigned_dept = ? OR assigned_dept IS NULL)" : "");
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $resolved_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Rejected Complaints (rejected)
    $query = "SELECT COUNT(*) as cnt FROM complaints WHERE status = 'rejected'" . (!empty($metric_where) ? " AND (assigned_dept = ? OR assigned_dept IS NULL)" : "");
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($metric_where)) {
        mysqli_stmt_bind_param($stmt, $metric_types, ...$metric_params);
    }
    mysqli_stmt_execute($stmt);
    $rejected_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

} catch (mysqli_sql_exception $e) {
    error_log("Admin Dashboard metrics fetch failed: " . $e->getMessage());
}

// 2. Dynamic Filtering setup
$selected_dept = isset($_GET['department']) ? trim($_GET['department']) : '';
$selected_cat = isset($_GET['category']) ? trim($_GET['category']) : '';
$selected_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_clauses = [];
$params = [];
$types = "";

// If the logged-in user is a regular admin (not super_admin), restrict to their department or unassigned complaints
if ($admin_role === 'admin') {
    // Get the logged in admin's department
    $admin_dept = $_SESSION['department'] ?? '';
    if (!empty($admin_dept)) {
        $where_clauses[] = "(c.assigned_dept = ? OR c.assigned_dept IS NULL)";
        $params[] = $admin_dept;
        $types .= "s";
    }
}

if ($selected_dept !== '') {
    $where_clauses[] = "u.department = ?";
    $params[] = $selected_dept;
    $types .= "s";
}
if ($selected_cat !== '') {
    $where_clauses[] = "c.category = ?";
    $params[] = $selected_cat;
    $types .= "s";
}
if ($selected_status !== '') {
    $where_clauses[] = "c.status = ?";
    $params[] = $selected_status;
    $types .= "s";
}

$history_query = "
    SELECT c.id, c.title, c.category, c.priority, c.status, c.created_at, c.assigned_dept,
           u.full_name, u.department, u.matric_no_staff_id 
    FROM complaints c 
    JOIN users u ON c.student_id = u.id
";

if (count($where_clauses) > 0) {
    $history_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$history_query .= " ORDER BY c.created_at DESC";

try {
    $stmt = mysqli_prepare($conn, $history_query);
    if (count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $history_result = mysqli_stmt_get_result($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("Admin Dashboard table fetch failed: " . $e->getMessage());
    $history_result = false;
}

// Get list of distinct departments for filter dropdown dynamically
$departments_list = [];
$dep_res = mysqli_query($conn, "SELECT DISTINCT department FROM users WHERE role = 'student' AND department IS NOT NULL");
if ($dep_res) {
    while ($row = mysqli_fetch_assoc($dep_res)) {
        $departments_list[] = $row['department'];
    }
}

// 3. Fetch data for Chart.js
$dept_labels = [];
$dept_counts = [];
try {
    $dept_q = "SELECT u.department, COUNT(c.id) as cnt 
               FROM complaints c 
               JOIN users u ON c.student_id = u.id 
               WHERE u.department IS NOT NULL AND u.department != ''
               GROUP BY u.department";
    $dept_res = mysqli_query($conn, $dept_q);
    while ($row = mysqli_fetch_assoc($dept_res)) {
        $dept_labels[] = $row['department'];
        $dept_counts[] = intval($row['cnt']);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Dept chart query failed: " . $e->getMessage());
}

$cat_labels = [];
$cat_counts = [];
try {
    $cat_q = "SELECT category, COUNT(id) as cnt FROM complaints GROUP BY category";
    $cat_res = mysqli_query($conn, $cat_q);
    while ($row = mysqli_fetch_assoc($cat_res)) {
        $cat_labels[] = $row['category'];
        $cat_counts[] = intval($row['cnt']);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Cat chart query failed: " . $e->getMessage());
}

$success_msg = $_SESSION['success'] ?? null;
$error_msg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Student Complaint Management System</title>
    <link rel="icon" type="image/jpeg" href="favicon.jpg">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="hidden">
        <div>
            <!-- Header Brand -->
            <div class="h-16 flex items-center px-6 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-650 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="font-bold text-white tracking-wide">CMS Admin</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-6 px-4 space-y-1">
                <a href="admin_dashboard.php" class="bg-indigo-600 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    Dashboard
                </a>
                <?php if ($admin_role === 'super_admin'): ?>
                <a href="generate_report.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m32-2v-2a4 4 0 00-4-4h-3.5a4 4 0 00-4 4v2m6.5-12a3 3 0 11-6 0 3 3 0 016 0zM14 8a3 3 0 11-6 0 3 3 0 016 0zm-3 8h3a4 4 0 014 4v2H5v-2a4 4 0 014-4z" />
                    </svg>
                    Report Center
                </a>
                <a href="manage_staff.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Manage Staff
                </a>
                <?php endif; ?>
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

        <!-- Admin Profile Footer Info -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/50">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-xs text-slate-500 truncate uppercase"><?php echo htmlspecialchars($admin_role); ?></p>
                </div>
            </div>
            <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden relative">
        
        <!-- Header -->
        <header class="h-16 border-b border-slate-800 bg-slate-950 flex items-center justify-between px-6 z-10">
            <!-- Mobile Menu Toggle -->
            <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <div class="hidden sm:block">
                <h1 class="text-lg font-bold text-white"><?php echo $admin_role === 'super_admin' ? 'Administrative Control Center' : htmlspecialchars($_SESSION['department']) . ' Portal'; ?></h1>
            </div>

            <!-- Profile Info Mobile/Top -->
            <div class="flex items-center space-x-4">
                <span class="text-xs px-2.5 py-1 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-full font-semibold uppercase tracking-wider">
                    <?php echo str_replace('_', ' ', $admin_role); ?> | <?php echo htmlspecialchars($_SESSION['department']); ?>
                </span>
            </div>
        </header>

        <!-- Mobile Drawer Navigation -->
        <div id="mobile-menu" class="fixed inset-0 z-30 bg-slate-950/80 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 flex">
            <!-- Dismiss overlay -->
            <div class="absolute inset-0" onclick="toggleMobileMenu()"></div>
            
            <div id="mobile-drawer-panel" class="relative w-64 bg-slate-900 h-full p-6 border-r border-slate-800 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 ease-in-out z-10">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg bg-indigo-650 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <span class="font-bold text-white">CMS Admin</span>
                        </div>
                        <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <nav class="space-y-2">
                        <a href="admin_dashboard.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl shadow-lg shadow-indigo-600/10">
                            Dashboard
                        </a>
                        <?php if ($admin_role === 'super_admin'): ?>
                        <a href="generate_report.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Report Center
                        </a>
                        <a href="manage_staff.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Manage Staff
                        </a>
                        <?php endif; ?>
                        <a href="admin_notifications.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Notifications
                        </a>
                        <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Security Settings
                        </a>
                    </nav>
                </div>
                <div class="border-t border-slate-800 pt-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                            <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($admin_name); ?></p>
                            <p class="text-xs text-slate-500 uppercase"><?php echo htmlspecialchars($admin_role); ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Scrollable Admin Dashboard Content -->
        <main class="flex-1 overflow-y-auto p-6 relative">
            <!-- Alert Display -->
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3 max-w-7xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3 max-w-7xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Dashboard Welcome -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white">Welcome back, Admin <?php echo htmlspecialchars($admin_name); ?>!</h2>
                <p class="text-sm text-slate-400 mt-1">Here is the system-wide overview of student complaints.</p>
            </div>

            <!-- Stats/Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
                <!-- Total Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-indigo-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-indigo-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-indigo-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Complaints</p>
                    <h3 class="text-3xl font-bold text-white mt-2"><?php echo $total_count; ?></h3>
                </div>

                <!-- Complaints Today -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-blue-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-blue-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-blue-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Filed Today</p>
                    <h3 class="text-3xl font-bold text-blue-400 mt-2"><?php echo $today_count; ?></h3>
                </div>

                <!-- Pending Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-amber-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-amber-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-amber-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending (New)</p>
                    <h3 class="text-3xl font-bold text-amber-400 mt-2"><?php echo $pending_count; ?></h3>
                </div>

                <!-- In Progress Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-violet-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-violet-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-violet-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">In Progress</p>
                    <h3 class="text-3xl font-bold text-violet-400 mt-2"><?php echo $inprogress_count; ?></h3>
                </div>

                <!-- Resolved Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-emerald-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-emerald-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-emerald-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Resolved / Closed</p>
                    <h3 class="text-3xl font-bold text-emerald-400 mt-2"><?php echo $resolved_count; ?></h3>
                </div>

                <!-- Rejected Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-rose-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-rose-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-rose-500/5 rounded-full blur-xl"></div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rejected</p>
                    <h3 class="text-3xl font-bold text-rose-400 mt-2"><?php echo $rejected_count; ?></h3>
                </div>
            </div>
 
            <!-- Charts Grid Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-7xl mb-8">
                <!-- Bar Chart: Complaints by Department -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 backdrop-blur-xl shadow-lg">
                    <h3 class="text-sm font-semibold text-slate-300 mb-4">Complaints by Department</h3>
                    <div class="h-64 relative">
                        <canvas id="deptChart"></canvas>
                    </div>
                </div>
                
                <!-- Doughnut Chart: Complaints by Category -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 backdrop-blur-xl shadow-lg">
                    <h3 class="text-sm font-semibold text-slate-300 mb-4">Complaints by Category</h3>
                    <div class="h-64 relative flex justify-center items-center">
                        <div class="w-full h-full max-w-[240px]">
                            <canvas id="catChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="max-w-7xl bg-slate-900/40 border border-slate-850 p-6 rounded-2xl mb-8 backdrop-blur-md">
                <h3 class="text-sm font-semibold text-slate-300 mb-4">Filter Complaints</h3>
                <form method="GET" action="admin_dashboard.php" class="grid grid-cols-1 <?php echo $admin_role === 'super_admin' ? 'sm:grid-cols-3' : 'sm:grid-cols-2'; ?> gap-4 items-end">
                    
                    <!-- Filter by Department -->
                    <div>
                        <label for="department" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Student Department</label>
                        <div class="relative">
                            <select id="department" name="department" 
                                class="appearance-none block w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="">All Departments</option>
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $selected_dept === $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <?php if ($admin_role === 'super_admin'): ?>
                    <!-- Filter by Category -->
                    <div>
                        <label for="category" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Category</label>
                        <div class="relative">
                            <select id="category" name="category" 
                                class="appearance-none block w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="">All Categories</option>
                                <option value="Academics" <?php echo $selected_cat === 'Academics' ? 'selected' : ''; ?>>Academics</option>
                                <option value="Bursary / Fees" <?php echo $selected_cat === 'Bursary / Fees' ? 'selected' : ''; ?>>Bursary / Fees</option>
                                <option value="Accommodation / Hostels" <?php echo $selected_cat === 'Accommodation / Hostels' ? 'selected' : ''; ?>>Accommodation / Hostels</option>
                                <option value="Maintenance" <?php echo $selected_cat === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="Information Technology (ICT)" <?php echo $selected_cat === 'Information Technology (ICT)' ? 'selected' : ''; ?>>Information Technology (ICT)</option>
                                <option value="Medical / Health Center" <?php echo $selected_cat === 'Medical / Health Center' ? 'selected' : ''; ?>>Medical / Health Center</option>
                                <option value="Cafeteria" <?php echo $selected_cat === 'Cafeteria' ? 'selected' : ''; ?>>Cafeteria</option>
                                <option value="Chapel / Spiritual Life" <?php echo $selected_cat === 'Chapel / Spiritual Life' ? 'selected' : ''; ?>>Chapel / Spiritual Life</option>
                                <option value="Security & Welfare" <?php echo $selected_cat === 'Security & Welfare' ? 'selected' : ''; ?>>Security & Welfare</option>
                                <option value="Library Services" <?php echo $selected_cat === 'Library Services' ? 'selected' : ''; ?>>Library Services</option>
                                <option value="Other / General" <?php echo $selected_cat === 'Other / General' ? 'selected' : ''; ?>>Other / General</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Filter by Status -->
                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Status</label>
                        <div class="relative">
                            <select id="status" name="status" 
                                class="appearance-none block w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $selected_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="under_review" <?php echo $selected_status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="in_progress" <?php echo $selected_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $selected_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $selected_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="rejected" <?php echo $selected_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Action Buttons -->
                    <div class="sm:col-span-3 flex justify-end space-x-2 mt-4 sm:mt-0">
                        <a href="admin_dashboard.php" class="px-5 py-2.5 rounded-xl border border-slate-800 hover:bg-slate-800 text-xs font-semibold text-slate-400 transition-colors">
                            Reset Filters
                        </a>
                        <button type="submit" 
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/10 text-xs font-semibold transition-all">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Complaints Table -->
            <div class="max-w-7xl">
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-xl">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-850">
                            <thead class="bg-slate-900/90 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Student</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4">Title</th>
                                    <th class="px-6 py-4">Category</th>
                                    <th class="px-6 py-4">Assigned Dept</th>
                                    <th class="px-6 py-4">Priority</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Date Filed</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-sm text-slate-300">
                                <?php if ($history_result && mysqli_num_rows($history_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($history_result)): ?>
                                        <tr class="hover:bg-slate-850/40 transition-colors">
                                            <td class="px-6 py-4 font-mono font-semibold text-slate-400">#<?php echo $row['id']; ?></td>
                                            <td class="px-6 py-4 font-medium text-white">
                                                <div>
                                                    <p class="font-semibold text-slate-200"><?php echo htmlspecialchars($row['full_name']); ?></p>
                                                    <p class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($row['matric_no_staff_id']); ?></p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td class="px-6 py-4 font-medium text-white max-w-[200px] truncate"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td class="px-6 py-4 text-xs font-semibold">
                                                <?php if (!empty($row['assigned_dept'])): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-indigo-500/10 text-indigo-400 border-indigo-500/20">
                                                        <?php echo htmlspecialchars($row['assigned_dept']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-slate-800 text-slate-500 border-slate-700">
                                                        Unassigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $priority = strtolower($row['priority']);
                                                $p_class = 'bg-slate-800 text-slate-400 border-slate-700';
                                                if ($priority === 'low') $p_class = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                                elseif ($priority === 'medium') $p_class = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                                elseif ($priority === 'high') $p_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border <?php echo $p_class; ?>">
                                                    <?php echo $priority; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $status = strtolower($row['status']);
                                                $s_class = 'bg-slate-800 text-slate-400 border-slate-700';
                                                if ($status === 'pending') $s_class = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                                elseif ($status === 'under_review') $s_class = 'bg-violet-500/10 text-violet-400 border-violet-500/20';
                                                elseif ($status === 'in_progress') $s_class = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                                elseif ($status === 'resolved') $s_class = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                                                elseif ($status === 'closed') $s_class = 'bg-slate-700/10 text-slate-400 border-slate-700/20';
                                                elseif ($status === 'rejected') $s_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border <?php echo $s_class; ?>">
                                                    <?php echo str_replace('_', ' ', $status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-slate-400">
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="view_complaint_admin.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-indigo-400 rounded-lg border border-slate-700/50 transition-colors">
                                                    Manage Complaint
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-16 text-center text-slate-500">
                                            <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                                <div class="p-4 rounded-full bg-slate-800/50 text-slate-600 mb-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-base font-bold text-white">No complaints found</h4>
                                                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                                                    There are no active complaints matching the selected filters.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Script for mobile menu toggle -->
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const panel = document.getElementById('mobile-drawer-panel');
            if (menu.classList.contains('pointer-events-none')) {
                menu.classList.remove('pointer-events-none', 'opacity-0');
                menu.classList.add('pointer-events-auto', 'opacity-100');
                panel.classList.remove('-translate-x-full');
                panel.classList.add('translate-x-0');
            } else {
                menu.classList.remove('pointer-events-auto', 'opacity-100');
                menu.classList.add('pointer-events-none', 'opacity-0');
                panel.classList.remove('translate-x-0');
                panel.classList.add('-translate-x-full');
            }
        }

        // --- Render Chart.js Visualizations ---
        const deptLabels = <?php echo json_encode($dept_labels); ?>;
        const deptCounts = <?php echo json_encode($dept_counts); ?>;
        const catLabels = <?php echo json_encode($cat_labels); ?>;
        const catCounts = <?php echo json_encode($cat_counts); ?>;

        let deptChart = null;
        let catChart = null;

        window.renderCharts = function() {
            const isLight = document.documentElement.classList.contains('light');
            
            // Adjust defaults based on active theme
            Chart.defaults.color = isLight ? '#475569' : '#94a3b8';
            Chart.defaults.borderColor = isLight ? '#e2e8f0' : '#1e293b';
            Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
            
            // Destroy existing charts to allow redrawing with new options
            if (deptChart) deptChart.destroy();
            if (catChart) catChart.destroy();

            const tooltipBg = isLight ? '#ffffff' : '#0f172a';
            const tooltipText = isLight ? '#0f172a' : '#f8fafc';
            const tooltipBorder = isLight ? '#cbd5e1' : '#334155';
            const doughnutBorder = isLight ? '#ffffff' : '#0f172a';

            // Department Bar Chart
            const deptCtx = document.getElementById('deptChart').getContext('2d');
            deptChart = new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Active Complaints',
                        data: deptCounts,
                        backgroundColor: 'rgba(99, 102, 241, 0.7)', // indigo-500
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 1.5,
                        borderRadius: 8,
                        hoverBackgroundColor: 'rgba(99, 102, 241, 0.9)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleColor: tooltipText,
                            bodyColor: isLight ? '#334155' : '#cbd5e1',
                            borderColor: tooltipBorder,
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: isLight ? '#e2e8f0' : '#1e293b'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Category Doughnut Chart
            const catCtx = document.getElementById('catChart').getContext('2d');
            catChart = new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catCounts,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.7)',  // emerald-500
                            'rgba(245, 158, 11, 0.7)',  // amber-500
                            'rgba(239, 68, 68, 0.7)',   // rose-500
                            'rgba(14, 165, 233, 0.7)',  // sky-500
                            'rgba(139, 92, 246, 0.7)',  // violet-500
                            'rgba(217, 70, 239, 0.7)',  // fuchsia-500
                            'rgba(100, 116, 139, 0.7)'  // slate-500
                        ],
                        borderColor: doughnutBorder,
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                padding: 12,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: tooltipBg,
                            titleColor: tooltipText,
                            bodyColor: isLight ? '#334155' : '#cbd5e1',
                            borderColor: tooltipBorder,
                            borderWidth: 1
                        }
                    },
                    cutout: '70%'
                }
            });
        };

        // Render initially
        window.renderCharts();
    </script>
</body>
</html>
