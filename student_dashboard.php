<?php
require_once 'db_connect.php';

// Secure access: student role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Fetch query stats
$total_count = 0;
$pending_count = 0;
$resolved_count = 0;

try {
    // 1. Get metrics
    $metric_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ('under_review', 'in_progress') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM complaints 
        WHERE student_id = ?
    ";
    $stmt = mysqli_prepare($conn, $metric_query);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($metrics = mysqli_fetch_assoc($result)) {
        $total_count = $metrics['total'] ?? 0;
        $pending_count = $metrics['pending'] ?? 0;
        $inprogress_count = $metrics['in_progress'] ?? 0;
        $resolved_count = $metrics['resolved'] ?? 0;
        $rejected_count = $metrics['rejected'] ?? 0;
    }
    mysqli_stmt_close($stmt);

    // 2. Get complaints history
    $history_query = "
        SELECT id, title, category, priority, status, created_at 
        FROM complaints 
        WHERE student_id = ? 
        ORDER BY created_at DESC
    ";
    $stmt = mysqli_prepare($conn, $history_query);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $history_result = mysqli_stmt_get_result($stmt);

} catch (mysqli_sql_exception $e) {
    error_log("Dashboard Data Fetch Error: " . $e->getMessage());
    $_SESSION['error'] = "Unable to retrieve dashboard metrics.";
    $history_result = false;
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
    <title>Student Dashboard | Student Complaint Management System</title>
    <link rel="icon" type="image/jpeg" href="favicon.jpg">
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
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between hidden md:flex z-20">
        <div>
            <!-- Header Brand -->
            <div class="h-16 flex items-center px-6 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="font-bold text-white tracking-wide">CMS Portal</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-6 px-4 space-y-1">
                <a href="student_dashboard.php" class="bg-indigo-600 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    Dashboard
                </a>
                <a href="submit_complaint.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    File a Complaint
                </a>
                <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Security Settings
                </a>
            </nav>
        </div>

        <!-- Student Profile Footer Info -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/50">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                    <?php echo strtoupper(substr($student_name, 0, 2)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($student_name); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($_SESSION['matric_no_staff_id']); ?></p>
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
            <button class="md:hidden text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <div class="hidden sm:block">
                <h1 class="text-lg font-bold text-white">Student Complaint Portal</h1>
            </div>

            <!-- Profile Info Mobile/Top -->
            <div class="flex items-center space-x-4">
                <span class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 text-indigo-400 rounded-full font-semibold uppercase tracking-wider">
                    Student Account
                </span>
            </div>
        </header>

        <!-- Mobile Drawer Navigation -->
        <div id="mobile-menu" class="fixed inset-0 z-30 bg-slate-950/80 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 flex md:hidden">
            <!-- Dismiss overlay -->
            <div class="absolute inset-0" onclick="toggleMobileMenu()"></div>
            
            <div id="mobile-drawer-panel" class="relative w-64 bg-slate-900 h-full p-6 border-r border-slate-800 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 ease-in-out z-10">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <span class="font-bold text-white">CMS Portal</span>
                        </div>
                        <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <nav class="space-y-2">
                        <a href="student_dashboard.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl shadow-lg shadow-indigo-600/10">
                            Dashboard
                        </a>
                        <a href="submit_complaint.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            File a Complaint
                        </a>
                        <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Security Settings
                        </a>
                    </nav>
                </div>
                <div class="border-t border-slate-800 pt-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                            <?php echo strtoupper(substr($student_name, 0, 2)); ?>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($student_name); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($_SESSION['matric_no_staff_id']); ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Scrollable Dashboard Content -->
        <main class="flex-1 overflow-y-auto p-6 relative">
            <!-- Alert Display -->
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3 max-w-4xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3 max-w-4xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Dashboard Welcome & Quick Info -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white">Welcome back, <?php echo htmlspecialchars($student_name); ?>!</h2>
                <p class="text-sm text-slate-400 mt-1">Here is a summary of your active complaints.</p>
            </div>

            <!-- Stats/Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
                <!-- Total Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-indigo-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-indigo-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-indigo-500/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Complaints</p>
                            <h3 class="text-3xl font-bold text-white mt-2"><?php echo $total_count; ?></h3>
                        </div>
                        <div class="p-3 bg-indigo-500/10 rounded-xl text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Pending Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-amber-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-amber-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-amber-500/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending (New)</p>
                            <h3 class="text-3xl font-bold text-amber-400 mt-2"><?php echo $pending_count; ?></h3>
                        </div>
                        <div class="p-3 bg-amber-500/10 rounded-xl text-amber-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- In Progress Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-blue-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-blue-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-blue-500/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">In Progress</p>
                            <h3 class="text-3xl font-bold text-blue-400 mt-2"><?php echo $inprogress_count; ?></h3>
                        </div>
                        <div class="p-3 bg-blue-500/10 rounded-xl text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Resolved Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-emerald-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-emerald-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-emerald-500/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Resolved / Closed</p>
                            <h3 class="text-3xl font-bold text-emerald-400 mt-2"><?php echo $resolved_count; ?></h3>
                        </div>
                        <div class="p-3 bg-emerald-500/10 rounded-xl text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Rejected Complaints -->
                <div class="bg-gradient-to-tr from-slate-900 to-slate-900/60 border border-slate-800 border-l-4 border-l-rose-500 rounded-2xl p-6 relative overflow-hidden shadow-lg shadow-black/30 transition-all duration-300 hover:scale-[1.02] hover:-translate-y-0.5 hover:shadow-rose-500/5">
                    <div class="absolute right-[-10px] bottom-[-10px] w-24 h-24 bg-rose-500/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rejected</p>
                            <h3 class="text-3xl font-bold text-rose-400 mt-2"><?php echo $rejected_count; ?></h3>
                        </div>
                        <div class="p-3 bg-rose-500/10 rounded-xl text-rose-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complaints History Table Section -->
            <div class="max-w-6xl">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-white">Complaint History</h3>
                    <a href="submit_complaint.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/10 active:scale-[0.98] transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Complaint
                    </a>
                </div>

                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-xl">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-850">
                            <thead class="bg-slate-900/90 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Title</th>
                                    <th class="px-6 py-4">Category</th>
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
                                            <td class="px-6 py-4 font-medium text-white max-w-[240px] truncate"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="px-6 py-4 text-slate-400"><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $priority = strtolower($row['priority']);
                                                $p_class = 'bg-slate-800 text-slate-400 border border-slate-700';
                                                if ($priority === 'low') $p_class = 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
                                                elseif ($priority === 'medium') $p_class = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                                elseif ($priority === 'high') $p_class = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider <?php echo $p_class; ?>">
                                                    <?php echo $priority; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                $status = strtolower($row['status']);
                                                $s_class = 'bg-slate-800 text-slate-400 border border-slate-700';
                                                if ($status === 'pending') $s_class = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                                elseif ($status === 'under_review') $s_class = 'bg-violet-500/10 text-violet-400 border border-violet-500/20';
                                                elseif ($status === 'in_progress') $s_class = 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
                                                elseif ($status === 'resolved') $s_class = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                                                elseif ($status === 'closed') $s_class = 'bg-slate-700/10 text-slate-400 border border-slate-700/20';
                                                elseif ($status === 'rejected') $s_class = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider <?php echo $s_class; ?>">
                                                    <?php echo str_replace('_', ' ', $status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-slate-400">
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="view_complaint_student.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-indigo-400 rounded-lg transition-colors border border-slate-700/50">
                                                    Track & Chat
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                                <div class="p-4 rounded-full bg-slate-800/50 text-slate-500 mb-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-base font-bold text-white">No complaints filed yet</h4>
                                                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                                                    You haven't submitted any complaints. Once you submit a complaint, you'll see it tracked here.
                                                </p>
                                                <a href="submit_complaint.php" class="mt-5 inline-flex items-center px-4 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/10 active:scale-[0.98] transition-all">
                                                    File Your First Complaint
                                                </a>
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
    </script>
</body>
</html>
