<?php
require_once 'db_connect.php';

// Secure access: super_admin role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    $_SESSION['error'] = "Access denied. Only Super Administrators can access the Report Center.";
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];

// 1. Get Date Range parameter
$range = isset($_GET['range']) ? trim($_GET['range']) : 'month';
$days = 30;
$range_label = 'Last 30 Days';

if ($range === 'week') {
    $days = 7;
    $range_label = 'Last 7 Days';
} elseif ($range === 'year') {
    $days = 365;
    $range_label = 'Last Year';
}

// 2. Fetch Report Metrics for the selected date range
$total_complaints = 0;
$resolved_complaints = 0;
$pending_complaints = 0;
$rejected_complaints = 0;

try {
    // Total Complaints
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM complaints WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $total_complaints = mysqli_fetch_assoc($res)['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Resolved Complaints
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM complaints WHERE status IN ('resolved', 'closed') AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $resolved_complaints = mysqli_fetch_assoc($res)['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Pending Complaints
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM complaints WHERE status IN ('pending', 'under_review', 'in_progress') AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pending_complaints = mysqli_fetch_assoc($res)['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

    // Rejected Complaints
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM complaints WHERE status = 'rejected' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rejected_complaints = mysqli_fetch_assoc($res)['cnt'] ?? 0;
    mysqli_stmt_close($stmt);

} catch (mysqli_sql_exception $e) {
    error_log("Report Metrics query failed: " . $e->getMessage());
}

// 3. Fetch Category distribution counts
$category_distribution = [];
try {
    $stmt = mysqli_prepare($conn, "
        SELECT category, COUNT(*) as cnt 
        FROM complaints 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY category 
        ORDER BY cnt DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $category_distribution[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("Report Category query failed: " . $e->getMessage());
}

// 4. Fetch Department distribution counts
$dept_distribution = [];
try {
    $stmt = mysqli_prepare($conn, "
        SELECT u.department, COUNT(c.id) as cnt 
        FROM complaints c
        JOIN users u ON c.student_id = u.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND u.department IS NOT NULL
        GROUP BY u.department 
        ORDER BY cnt DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $dept_distribution[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("Report Department query failed: " . $e->getMessage());
}

// 5. Fetch recent complaints summary list
$recent_complaints = [];
try {
    $stmt = mysqli_prepare($conn, "
        SELECT c.id, c.title, c.category, c.status, c.created_at, u.full_name, u.matric_no_staff_id
        FROM complaints c
        JOIN users u ON c.student_id = u.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY c.created_at DESC
        LIMIT 15
    ");
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_complaints[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("Report Recent complaints query failed: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Complaints Report | SCMS Admin</title>
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

        /* Clean Professional Print CSS overrides */
        @media print {
            /* Hide non-printable panels */
            aside, header, #print-controls, #mobile-menu {
                display: none !important;
            }
            /* Reset body limits */
            body, html {
                background-color: #ffffff !important;
                color: #000000 !important;
                height: auto !important;
                overflow: visible !important;
                font-size: 12pt !important;
            }
            main {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                overflow: visible !important;
            }
            /* Dark elements to light backgrounds */
            .bg-slate-900\/60, .bg-slate-950\/50, .bg-slate-900\/40, .bg-slate-900 {
                background: none !important;
                background-color: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                box-shadow: none !important;
            }
            .text-slate-100, .text-slate-200, .text-slate-300, .text-white {
                color: #000000 !important;
            }
            .text-slate-400, .text-slate-500 {
                color: #475569 !important;
            }
            .border-slate-800, .border-slate-850, .border-slate-700 {
                border-color: #cbd5e1 !important;
            }
            /* Table formatting */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 15px !important;
            }
            th, td {
                border: 1px solid #cbd5e1 !important;
                padding: 8px 12px !important;
                color: #000000 !important;
                background-color: #ffffff !important;
            }
            thead {
                background-color: #f1f5f9 !important;
            }
            /* Badges fallback */
            span.bg-amber-500\/10, span.bg-blue-500\/10, span.bg-emerald-500\/10, span.bg-rose-500\/10 {
                background: none !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
                padding: 2px 6px !important;
                border-radius: 4px !important;
            }
            /* Force break avoidance */
            .page-break-avoid {
                page-break-inside: avoid !important;
            }
            /* Show printable headers */
            #print-report-header {
                display: block !important;
            }
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- Sidebar (Screen Only) -->
    <aside class="hidden">
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
                <a href="generate_report.php" class="bg-indigo-600 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
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

        <!-- Admin Profile Info -->
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
            <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden relative bg-slate-950">
        
        <!-- Header (Screen Only) -->
        <header class="h-16 border-b border-slate-800 bg-slate-950 flex items-center justify-between px-6 z-10">
            <div class="flex items-center space-x-4">
                <!-- Hamburger menu toggle -->
                <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-lg font-bold text-white">System Reports</h1>
            </div>
            <div>
                <span class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 text-indigo-400 rounded-full font-semibold uppercase">
                    Analytical Ledger
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
                        <a href="admin_dashboard.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Dashboard
                        </a>
                        <a href="generate_report.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl shadow-lg shadow-indigo-600/10">
                            Report Center
                        </a>
                        <a href="manage_staff.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Manage Staff
                        </a>
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
                            <p class="text-xs text-slate-500 uppercase"><?php echo htmlspecialchars($admin_identifier); ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Scrollable Report Container -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Report Controls (Screen Only) -->
            <div id="print-controls" class="flex flex-wrap items-center justify-between gap-4 bg-slate-900/60 p-4 border border-slate-800 rounded-2xl max-w-5xl">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-semibold text-slate-400 uppercase">Select Period:</span>
                    <a href="generate_report.php?range=week" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold <?php echo $range === 'week' ? 'bg-indigo-600 text-white' : 'bg-slate-850 hover:bg-slate-800 text-slate-400'; ?> transition-all">Week</a>
                    <a href="generate_report.php?range=month" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold <?php echo $range === 'month' ? 'bg-indigo-600 text-white' : 'bg-slate-850 hover:bg-slate-800 text-slate-400'; ?> transition-all">Month</a>
                    <a href="generate_report.php?range=year" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold <?php echo $range === 'year' ? 'bg-indigo-600 text-white' : 'bg-slate-850 hover:bg-slate-800 text-slate-400'; ?> transition-all">Year</a>
                </div>
                
                <button onclick="window.print();" class="inline-flex items-center px-4.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl transition-all shadow-md active:scale-95">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Report
                </button>
            </div>

            <!-- Printable Layout Container -->
            <div class="max-w-5xl space-y-6">
                
                <!-- Print Specific Header (Hidden on Screen, Visible on Paper) -->
                <div id="print-report-header" class="hidden text-center border-b pb-6 mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 uppercase tracking-wide">Student Complaint Management System</h1>
                    <h2 class="text-lg font-semibold text-gray-700 mt-1">Official Administrative Performance & Audit Report</h2>
                    <div class="flex justify-between items-center text-xs text-gray-600 mt-4 px-2">
                        <span>Report Period: <strong class="text-gray-900"><?php echo $range_label; ?></strong> (Generated on <?php echo date('M d, Y'); ?>)</span>
                        <span>Generated By: <strong class="text-gray-900">Admin <?php echo htmlspecialchars($admin_name); ?></strong></span>
                    </div>
                </div>

                <!-- Screen Title -->
                <div class="block @media-print:hidden print:hidden">
                    <h2 class="text-2xl font-bold text-white">System Performance Summary</h2>
                    <p class="text-xs text-slate-400 mt-1">Summary audit sheet for the <strong class="text-indigo-400 font-semibold"><?php echo $range_label; ?></strong> interval.</p>
                </div>

                <!-- Aggregated Metrics Cards/Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Total -->
                    <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl">
                        <span class="text-[10px] font-semibold text-slate-500 uppercase block">Total Logged</span>
                        <span class="text-2xl font-bold text-white mt-1 block"><?php echo $total_complaints; ?></span>
                    </div>
                    <!-- Pending -->
                    <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl">
                        <span class="text-[10px] font-semibold text-slate-500 uppercase block">Pending / In Progress</span>
                        <span class="text-2xl font-bold text-amber-400 mt-1 block"><?php echo $pending_complaints; ?></span>
                    </div>
                    <!-- Resolved -->
                    <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl">
                        <span class="text-[10px] font-semibold text-slate-500 uppercase block">Resolved / Closed</span>
                        <span class="text-2xl font-bold text-emerald-400 mt-1 block"><?php echo $resolved_complaints; ?></span>
                    </div>
                    <!-- Rejected -->
                    <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl">
                        <span class="text-[10px] font-semibold text-slate-500 uppercase block">Rejected Complaints</span>
                        <span class="text-2xl font-bold text-rose-400 mt-1 block"><?php echo $rejected_complaints; ?></span>
                    </div>
                </div>

                <!-- Grid distribution details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 page-break-avoid">
                    <!-- Category table -->
                    <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl">
                        <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider pb-2 border-b border-slate-800">Complaints by Category</h3>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs text-slate-500 font-semibold border-b border-slate-800">
                                    <th class="py-2">Category Name</th>
                                    <th class="py-2 text-right">Complaints Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-slate-300">
                                <?php if (count($category_distribution) > 0): ?>
                                    <?php foreach ($category_distribution as $row): ?>
                                        <tr>
                                            <td class="py-2.5 font-medium text-slate-200"><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td class="py-2.5 text-right font-mono"><?php echo $row['cnt']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="py-4 text-center text-slate-550 text-xs">No records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Department distribution table -->
                    <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl">
                        <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider pb-2 border-b border-slate-800">Complaints by Department</h3>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs text-slate-500 font-semibold border-b border-slate-800">
                                    <th class="py-2">Department</th>
                                    <th class="py-2 text-right">Complaints Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-slate-300">
                                <?php if (count($dept_distribution) > 0): ?>
                                    <?php foreach ($dept_distribution as $row): ?>
                                        <tr>
                                            <td class="py-2.5 font-medium text-slate-200"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td class="py-2.5 text-right font-mono"><?php echo $row['cnt']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="py-4 text-center text-slate-550 text-xs">No records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent complaints log ledger table -->
                <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl page-break-avoid">
                    <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider pb-2 border-b border-slate-800">Ledger Details (Last 15 Complaints)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs text-slate-500 font-semibold border-b border-slate-800 uppercase">
                                    <th class="py-2">ID</th>
                                    <th class="py-2">Student</th>
                                    <th class="py-2">Title</th>
                                    <th class="py-2">Category</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Date Filed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-slate-300">
                                <?php if (count($recent_complaints) > 0): ?>
                                    <?php foreach ($recent_complaints as $row): ?>
                                        <tr class="text-xs">
                                            <td class="py-3 font-mono font-semibold text-slate-500">#<?php echo $row['id']; ?></td>
                                            <td class="py-3 font-semibold text-slate-200">
                                                <?php echo htmlspecialchars($row['full_name']); ?><br>
                                                <span class="text-[9px] text-slate-500 font-mono"><?php echo htmlspecialchars($row['matric_no_staff_id']); ?></span>
                                            </td>
                                            <td class="py-3 font-medium text-slate-300 truncate max-w-[150px]"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="py-3 text-slate-400"><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td class="py-3">
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
                                                <span class="inline-flex px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider <?php echo $s_class; ?>">
                                                    <?php echo str_replace('_', ' ', $status); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 text-slate-500 font-mono"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-500 text-xs">No records available for the current date scope.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Signature Verification Row (Only Visible on Print) -->
                <div class="hidden print:block pt-16 mt-16 page-break-avoid">
                    <div class="flex justify-between items-center text-xs">
                        <div class="text-center w-48">
                            <div class="border-b border-gray-400 h-10 w-full mb-2"></div>
                            <span class="text-gray-600 block">System Auditor Signature</span>
                        </div>
                        <div class="text-center w-48">
                            <div class="border-b border-gray-400 h-10 w-full mb-2"></div>
                            <span class="text-gray-600 block">Dean / Approver Signature</span>
                        </div>
                    </div>
                    <div class="text-center text-[10px] text-gray-500 mt-12">
                        System Integrity Confirmed &bull; Student Complaint Management System Audit Log &copy; <?php echo date('Y'); ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script for Mobile Drawer -->
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
