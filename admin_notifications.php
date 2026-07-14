<?php
require_once 'db_connect.php';

// Secure access: admin or super_admin check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$admin_role = $_SESSION['role'];

// 1. Process Actions (Mark read, Mark all read, Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'mark_read') {
        $notif_id = isset($_GET['notif_id']) ? intval($_GET['notif_id']) : 0;
        if ($notif_id > 0) {
            try {
                $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $notif_id, $admin_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success'] = "Notification marked as read.";
            } catch (mysqli_sql_exception $e) {
                error_log("Failed to mark notification read: " . $e->getMessage());
            }
        }
    }
    
    if ($action === 'mark_all_read') {
        try {
            $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['success'] = "All notifications marked as read.";
        } catch (mysqli_sql_exception $e) {
            error_log("Failed to mark all read: " . $e->getMessage());
        }
    }
    
    if ($action === 'delete') {
        $notif_id = isset($_GET['notif_id']) ? intval($_GET['notif_id']) : 0;
        if ($notif_id > 0) {
            try {
                $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $notif_id, $admin_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success'] = "Notification deleted.";
            } catch (mysqli_sql_exception $e) {
                error_log("Failed to delete notification: " . $e->getMessage());
            }
        }
    }
    
    header("Location: admin_notifications.php");
    exit();
}

// 2. Fetch notifications for current admin
$notifications = [];
try {
    $stmt = mysqli_prepare($conn, "SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("Failed to fetch admin notifications: " . $e->getMessage());
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
    <title>Admin Notifications | Student Complaint Management System</title>
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
                <a href="admin_dashboard.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
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
                <a href="admin_notifications.php" class="bg-indigo-600 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
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
            <!-- Mobile Toggle -->
            <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <div>
                <h1 class="text-lg font-bold text-white">Notifications</h1>
            </div>

            <div>
                <?php if (count($notifications) > 0): ?>
                    <a href="admin_notifications.php?action=mark_all_read" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold transition-colors">
                        Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Mobile Menu Drawer -->
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
                        <?php if ($admin_role === 'super_admin'): ?>
                        <a href="generate_report.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Report Center
                        </a>
                        <a href="manage_staff.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Manage Staff
                        </a>
                        <?php endif; ?>
                        <a href="admin_notifications.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
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

        <!-- Scrollable notifications container -->
        <main class="flex-1 overflow-y-auto p-6 relative max-w-4xl w-full">
            
            <!-- Alert Display -->
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (count($notifications) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notif): ?>
                        <?php 
                        // Extract complaint ID if present in the message
                        $complaint_id_link = '#';
                        if (preg_match('/#(\d+)/', $notif['message'], $matches)) {
                            $complaint_id_link = "view_complaint_admin.php?id=" . $matches[1];
                        }
                        
                        $is_read = $notif['is_read'] == 1;
                        ?>
                        <div class="p-4 rounded-2xl border transition-all flex items-start justify-between space-x-4 bg-slate-900/60 border-slate-800/80 hover:bg-slate-900 <?php echo !$is_read ? 'ring-1 ring-indigo-500/25 border-indigo-500/30' : ''; ?>">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <!-- Read indicator dot -->
                                    <?php if (!$is_read): ?>
                                        <span class="h-2 w-2 rounded-full bg-indigo-500 block flex-shrink-0"></span>
                                    <?php endif; ?>
                                    <span class="text-xs text-slate-500 font-mono"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></span>
                                </div>
                                <p class="text-sm text-slate-200 mt-2 leading-relaxed">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </p>
                                
                                <div class="mt-4 flex items-center space-x-3 text-xs font-semibold">
                                    <?php if ($complaint_id_link !== '#'): ?>
                                        <a href="<?php echo $complaint_id_link; ?>" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                            View Complaint
                                        </a>
                                        <span class="text-slate-700">&bull;</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!$is_read): ?>
                                        <a href="admin_notifications.php?action=mark_read&notif_id=<?php echo $notif['id']; ?>" class="text-slate-400 hover:text-white transition-colors">
                                            Mark as Read
                                        </a>
                                        <span class="text-slate-700">&bull;</span>
                                    <?php endif; ?>
                                    
                                    <a href="admin_notifications.php?action=delete&notif_id=<?php echo $notif['id']; ?>" class="text-rose-400 hover:text-rose-350 transition-colors">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="h-[60vh] flex flex-col items-center justify-center text-center p-6 text-slate-500">
                    <div class="p-4 rounded-full bg-slate-900 border border-slate-800 text-slate-650 mb-4 shadow-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h4 class="text-base font-bold text-white">No notifications</h4>
                    <p class="text-xs text-slate-600 mt-1 max-w-xs">You're all caught up! You will receive system notifications here when new complaints are created or replies are sent.</p>
                </div>
            <?php endif; ?>
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
