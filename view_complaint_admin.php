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

// Validate GET parameters
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id <= 0) {
    $_SESSION['error'] = "Invalid complaint ID.";
    header("Location: admin_dashboard.php");
    exit();
}

// ----------------------------------------------------
// 1. Process Status/Priority Form Submission (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_priority') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    $status = trim($_POST['status'] ?? '');
    $priority = trim($_POST['priority'] ?? '');

    // Validate inputs
    $valid_statuses = ['pending', 'under_review', 'in_progress', 'resolved', 'closed', 'rejected'];
    $valid_priorities = ['low', 'medium', 'high'];

    if (!in_array($status, $valid_statuses) || !in_array($priority, $valid_priorities)) {
        $_SESSION['error'] = "Invalid status or priority level selected.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        // Fetch current student ID to trigger notification
        $c_res = mysqli_query($conn, "SELECT student_id, title FROM complaints WHERE id = {$complaint_id} LIMIT 1");
        $complaint_meta = mysqli_fetch_assoc($c_res);
        $student_id = $complaint_meta['student_id'] ?? 0;
        $complaint_title = $complaint_meta['title'] ?? '';

        // A. Update status and priority
        $update_query = "UPDATE complaints SET status = ?, priority = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $status, $priority, $complaint_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // B. Insert Notification for the student
        if ($student_id > 0) {
            $formatted_status = str_replace('_', ' ', strtoupper($status));
            $notif_message = "Your complaint #{$complaint_id} (\"{$complaint_title}\") status has been updated to {$formatted_status} (Priority: " . strtoupper($priority) . ").";
            
            $notif_query = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_query);
            mysqli_stmt_bind_param($notif_stmt, "is", $student_id, $notif_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Complaint status and priority successfully updated.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Failed to update status/priority: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update complaint parameters. Please try again.";
    }

    header("Location: view_complaint_admin.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 1b. Process Department Assignment Form Submission (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_assignment') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    // Role check: Only Super Admin can route
    if ($admin_role !== 'super_admin') {
        $_SESSION['error'] = "Access denied. Only Super Administrators can reassign or route complaints.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    $assigned_dept = trim($_POST['assigned_dept'] ?? '');

    // Validate inputs
    $valid_depts = ['Academic Unit', 'Bursary / Finance', 'Student Affairs / Hostel', 'Works & Maintenance', 'ICT Unit', 'Health Services', 'General Admin'];
    if (!empty($assigned_dept) && !in_array($assigned_dept, $valid_depts)) {
        $_SESSION['error'] = "Invalid department selected.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    // Set NULL if empty
    $db_assigned = empty($assigned_dept) ? null : $assigned_dept;

    mysqli_begin_transaction($conn);

    try {
        // A. Update assigned department
        $update_query = "UPDATE complaints SET assigned_dept = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $db_assigned, $complaint_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // B. Insert automated comment message to chat log
        $label = empty($assigned_dept) ? "Unassigned" : "the '{$assigned_dept}' Department";
        $log_message = "📢 System Note: Complaint assigned to {$label} by Admin {$admin_name}.";
        
        $msg_query = "INSERT INTO messages (complaint_id, sender_id, message_text) VALUES (?, ?, ?)";
        $msg_stmt = mysqli_prepare($conn, $msg_query);
        mysqli_stmt_bind_param($msg_stmt, "iis", $complaint_id, $admin_id, $log_message);
        mysqli_stmt_execute($msg_stmt);
        mysqli_stmt_close($msg_stmt);

        // C. Fetch student ID to trigger notification
        $c_res = mysqli_query($conn, "SELECT student_id, title FROM complaints WHERE id = {$complaint_id} LIMIT 1");
        $complaint_meta = mysqli_fetch_assoc($c_res);
        $student_id = $complaint_meta['student_id'] ?? 0;
        $complaint_title = $complaint_meta['title'] ?? '';

        // D. Insert Notification for the student
        if ($student_id > 0) {
            $notif_message = "Your complaint #{$complaint_id} (\"{$complaint_title}\") has been routed to: " . (empty($assigned_dept) ? "General Queue" : $assigned_dept) . ".";
            
            $notif_query = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_query);
            mysqli_stmt_bind_param($notif_stmt, "is", $student_id, $notif_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Complaint successfully assigned/routed.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Failed to update complaint assignment: " . $e->getMessage());
        $_SESSION['error'] = "Failed to route complaint. Please try again.";
    }

    header("Location: view_complaint_admin.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 2. Process Chat Reply Submission (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    $message_text = trim($_POST['message_text'] ?? '');
    if (empty($message_text)) {
        $_SESSION['error'] = "Reply cannot be empty.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        // Fetch current student ID
        $c_res = mysqli_query($conn, "SELECT student_id, title FROM complaints WHERE id = {$complaint_id} LIMIT 1");
        $complaint_meta = mysqli_fetch_assoc($c_res);
        $student_id = $complaint_meta['student_id'] ?? 0;
        $complaint_title = $complaint_meta['title'] ?? '';

        // A. Insert message
        $msg_query = "INSERT INTO messages (complaint_id, sender_id, message_text) VALUES (?, ?, ?)";
        $msg_stmt = mysqli_prepare($conn, $msg_query);
        mysqli_stmt_bind_param($msg_stmt, "iis", $complaint_id, $admin_id, $message_text);
        mysqli_stmt_execute($msg_stmt);
        mysqli_stmt_close($msg_stmt);

        // B. Update complaint's updated_at timestamp
        $update_query = "UPDATE complaints SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $up_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($up_stmt, "i", $complaint_id);
        mysqli_stmt_execute($up_stmt);
        mysqli_stmt_close($up_stmt);

        // C. Notify Student
        if ($student_id > 0) {
            $truncated_msg = strlen($message_text) > 60 ? substr($message_text, 0, 57) . '...' : $message_text;
            $notif_message = "New reply from Administration on your complaint #{$complaint_id} (\"{$complaint_title}\"): \"{$truncated_msg}\"";
            
            $notif_query = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_query);
            mysqli_stmt_bind_param($notif_stmt, "is", $student_id, $notif_message);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Reply successfully sent to the student.";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Admin message reply failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to send reply. Please try again.";
    }

    header("Location: view_complaint_admin.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 2b. Process Message Deletion (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    $message_id = intval($_POST['message_id'] ?? 0);
    try {
        // Fetch message to verify ownership
        $check_query = "SELECT sender_id, message_text FROM messages WHERE id = ? AND complaint_id = ? LIMIT 1";
        $c_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($c_stmt, "ii", $message_id, $complaint_id);
        mysqli_stmt_execute($c_stmt);
        $res = mysqli_stmt_get_result($c_stmt);
        $msg = mysqli_fetch_assoc($res);
        mysqli_stmt_close($c_stmt);

        if (!$msg) {
            throw new Exception("Reply not found.");
        }

        // Validate sender_id matches logged-in admin user
        if ($msg['sender_id'] != $admin_id) {
            throw new Exception("Unauthorized delete attempt.");
        }

        // Prevent deleting System Notes
        if (strpos($msg['message_text'], "📢 System Note:") === 0) {
            throw new Exception("System notes cannot be deleted.");
        }

        // Perform deletion
        $del_query = "DELETE FROM messages WHERE id = ?";
        $d_stmt = mysqli_prepare($conn, $del_query);
        mysqli_stmt_bind_param($d_stmt, "i", $message_id);
        mysqli_stmt_execute($d_stmt);
        mysqli_stmt_close($d_stmt);

        $_SESSION['success'] = "Reply deleted successfully.";

    } catch (Exception $e) {
        error_log("Failed to delete message: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: view_complaint_admin.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 2c. Process Message Editing (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_admin.php?id=" . $complaint_id);
        exit();
    }

    $message_id = intval($_POST['message_id'] ?? 0);
    $edited_text = trim($_POST['edited_text'] ?? '');

    try {
        if (empty($edited_text)) {
            throw new Exception("Reply content cannot be empty.");
        }

        // Fetch message to verify ownership
        $check_query = "SELECT sender_id, message_text FROM messages WHERE id = ? AND complaint_id = ? LIMIT 1";
        $c_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($c_stmt, "ii", $message_id, $complaint_id);
        mysqli_stmt_execute($c_stmt);
        $res = mysqli_stmt_get_result($c_stmt);
        $msg = mysqli_fetch_assoc($res);
        mysqli_stmt_close($c_stmt);

        if (!$msg) {
            throw new Exception("Reply not found.");
        }

        // Validate sender_id matches logged-in admin user
        if ($msg['sender_id'] != $admin_id) {
            throw new Exception("Unauthorized edit attempt.");
        }

        // Prevent editing System Notes
        if (strpos($msg['message_text'], "📢 System Note:") === 0) {
            throw new Exception("System notes cannot be modified.");
        }

        // Perform update
        $up_query = "UPDATE messages SET message_text = ? WHERE id = ?";
        $u_stmt = mysqli_prepare($conn, $up_query);
        mysqli_stmt_bind_param($u_stmt, "si", $edited_text, $message_id);
        mysqli_stmt_execute($u_stmt);
        mysqli_stmt_close($u_stmt);

        $_SESSION['success'] = "Reply updated successfully.";

    } catch (Exception $e) {
        error_log("Failed to edit message: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: view_complaint_admin.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 3. Fetch Complaint Details & Student Info (Joined)
// ----------------------------------------------------
$complaint = null;
try {
    $detail_query = "
        SELECT c.id, c.title, c.description, c.category, c.priority, c.status, c.attachment_path, c.created_at, c.updated_at, c.assigned_dept,
               u.full_name as student_name, u.matric_no_staff_id as student_matric, u.email as student_email, u.department as student_dept
        FROM complaints c 
        JOIN users u ON c.student_id = u.id 
        WHERE c.id = ? 
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $detail_query);
    mysqli_stmt_bind_param($stmt, "i", $complaint_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$complaint = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        $_SESSION['error'] = "Complaint not found.";
        header("Location: admin_dashboard.php");
        exit();
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("DB Error fetching admin complaint detail: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching details.";
    header("Location: admin_dashboard.php");
    exit();
}

// ----------------------------------------------------
// 4. Fetch Message History
// ----------------------------------------------------
$messages = [];
try {
    $msg_history_query = "
        SELECT m.id as message_id, m.message_text, m.created_at, u.full_name, u.role, u.id as sender_id 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.complaint_id = ? 
        ORDER BY m.created_at ASC
    ";
    $stmt = mysqli_prepare($conn, $msg_history_query);
    mysqli_stmt_bind_param($stmt, "i", $complaint_id);
    mysqli_stmt_execute($stmt);
    $msg_result = mysqli_stmt_get_result($stmt);
    while ($msg = mysqli_fetch_assoc($msg_result)) {
        $messages[] = $msg;
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("DB Error fetching messages: " . $e->getMessage());
}

$csrf_token = get_csrf_token();
$success_msg = $_SESSION['success'] ?? null;
$error_msg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaint #<?php echo $complaint['id']; ?> | SCMS Admin</title>
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
            
            <div class="flex items-center space-x-4">
                <a href="admin_dashboard.php" class="text-slate-400 hover:text-white flex items-center text-sm font-semibold transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Complaints
                </a>
            </div>

            <!-- Complaint ID Header badge -->
            <div>
                <span class="text-xs px-3 py-1.5 bg-slate-900 border border-slate-800 text-indigo-400 rounded-full font-mono font-semibold">
                    Complaint #<?php echo $complaint['id']; ?>
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

        <!-- Scrollable Split Content -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Alert Display -->
            <?php if ($success_msg): ?>
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3 max-w-7xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3 max-w-7xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Split Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl">
                
                <!-- Left 2 Columns: Complaint & Chat -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Complaint Details Card -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 backdrop-blur-xl">
                        <!-- Upper Detail Title -->
                        <div class="pb-6 border-b border-slate-800">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest block">Complaint Title</span>
                            <h2 class="text-xl font-bold text-white mt-1"><?php echo htmlspecialchars($complaint['title']); ?></h2>
                        </div>

                        <!-- Complaint Body & Student info -->
                        <div class="py-6 space-y-6">
                            <!-- Student Details Box -->
                            <div class="bg-slate-950/50 rounded-xl p-4 border border-slate-850">
                                <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-3">Student Demographics</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 uppercase">Full Name</span>
                                        <p class="text-slate-300 font-medium"><?php echo htmlspecialchars($complaint['student_name']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 uppercase">Matric Number</span>
                                        <p class="text-slate-300 font-mono font-medium"><?php echo htmlspecialchars($complaint['student_matric']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 uppercase">Email Address</span>
                                        <p class="text-slate-300 font-medium"><?php echo htmlspecialchars($complaint['student_email']); ?></p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-slate-500 uppercase">Department</span>
                                        <p class="text-slate-300 font-medium"><?php echo htmlspecialchars($complaint['student_dept']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Category, Assigned Dept and Date -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase">Category</span>
                                    <p class="text-slate-300 font-medium mt-0.5"><?php echo htmlspecialchars($complaint['category']); ?></p>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase">Handling Department</span>
                                    <p class="text-indigo-400 font-bold mt-0.5">
                                        <?php echo !empty($complaint['assigned_dept']) ? htmlspecialchars($complaint['assigned_dept']) : 'General Queue'; ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase">Date Filed</span>
                                    <p class="text-slate-300 font-medium mt-0.5"><?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?></p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase">Description Details</span>
                                <p class="text-slate-300 text-sm leading-relaxed whitespace-pre-wrap mt-1 border border-slate-800 bg-slate-950/40 rounded-xl p-4"><?php echo htmlspecialchars($complaint['description']); ?></p>
                            </div>

                            <!-- Attachment Link -->
                            <?php if ($complaint['attachment_path']): ?>
                                <div class="border-t border-slate-800 pt-4 mt-4">
                                    <span class="text-xs font-semibold text-slate-500 uppercase">Attachment</span>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($complaint['attachment_path']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-indigo-400 rounded-xl border border-slate-700/50 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            View Uploaded Document / Screenshot
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Threads -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl flex flex-col backdrop-blur-xl h-[500px]">
                        <!-- Conversation Header -->
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/40 flex justify-between items-center rounded-t-2xl">
                            <h3 class="text-sm font-bold text-white">Discussion with Student</h3>
                            <span class="text-xs text-slate-400 font-medium"><?php echo count($messages); ?> Messages</span>
                        </div>

                        <!-- Chat Messages Container -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
                            <?php if (count($messages) > 0): ?>
                                <?php foreach ($messages as $msg): ?>
                                    <?php 
                                    $is_system_note = (strpos($msg['message_text'], "📢 System Note:") === 0);
                                    
                                    if ($is_system_note): 
                                    ?>
                                        <!-- Centered System Note Timeline Badge -->
                                        <div class="flex justify-center my-3">
                                            <div class="px-4 py-2 bg-slate-900/50 border border-slate-800/80 rounded-full text-xs text-slate-400 font-medium flex items-center space-x-2 shadow-sm">
                                                <span><?php echo htmlspecialchars($msg['message_text']); ?></span>
                                                <span class="text-[9px] text-slate-500 font-mono">&bull; <?php echo date('M d, h:i A', strtotime($msg['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php 
                                        // Align logic: If the sender id matches the current admin ID, it aligns right (You)
                                        $is_me = ($msg['sender_id'] == $admin_id);
                                        
                                        if ($is_me) {
                                            $bubble_bg = 'bg-gradient-to-br from-indigo-500 to-indigo-650 text-white rounded-tr-none shadow-lg shadow-indigo-500/10';
                                            $align_class = 'justify-end';
                                            $sender_label = 'You';
                                        } else {
                                            $bubble_bg = 'bg-slate-900/60 backdrop-blur-md text-slate-100 border border-white/5 rounded-tl-none shadow-md';
                                            $align_class = 'justify-start';
                                            $sender_label = $msg['full_name'] . ' (' . ucwords($msg['role']) . ')';
                                        }
                                        ?>
                                        <div class="flex <?php echo $align_class; ?>">
                                            <div class="max-w-[85%] sm:max-w-[70%]">
                                                <!-- Meta info -->
                                                <p class="text-[10px] text-slate-500 font-semibold mb-1 px-1">
                                                    <?php echo htmlspecialchars($sender_label); ?> &bull; <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                                </p>
                                                <!-- Message text -->
                                                <div class="p-3.5 rounded-2xl text-sm leading-relaxed break-words shadow-md <?php echo $bubble_bg; ?> relative group">
                                                    <div id="msg-text-<?php echo $msg['message_id']; ?>">
                                                        <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                                                    </div>
                                                    
                                                    <?php if ($is_me): ?>
                                                        <!-- Edit Form -->
                                                        <form id="edit-form-<?php echo $msg['message_id']; ?>" action="" method="POST" class="hidden mt-2 space-y-2">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="edit_message">
                                                            <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                            <textarea name="edited_text" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500" rows="2" required><?php echo htmlspecialchars($msg['message_text']); ?></textarea>
                                                            <div class="flex justify-end space-x-2">
                                                                <button type="button" onclick="cancelEdit(<?php echo $msg['message_id']; ?>)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-750 text-[10px] text-slate-300 rounded font-semibold transition-colors">Cancel</button>
                                                                <button type="submit" class="px-2.5 py-1 bg-indigo-500 hover:bg-indigo-450 text-[10px] text-white rounded font-bold transition-colors">Save</button>
                                                            </div>
                                                        </form>

                                                         <!-- Edit/Delete Action Triggers -->
                                                         <div class="flex justify-end items-center space-x-3 mt-2 pt-1.5 border-t border-white/10 text-[11px] font-bold">
                                                             <button type="button" onclick="showEdit(<?php echo $msg['message_id']; ?>)" class="text-indigo-200 hover:text-white transition-colors">Edit</button>
                                                             <span class="text-white/20">|</span>
                                                             <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this reply?');">
                                                                 <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                 <input type="hidden" name="action" value="delete_message">
                                                                 <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                                 <button type="submit" class="text-rose-300 hover:text-rose-100 transition-colors">Delete</button>
                                                             </form>
                                                         </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="h-full flex flex-col items-center justify-center text-center p-6 text-slate-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <p class="text-sm font-semibold">No discussions yet</p>
                                    <p class="text-xs text-slate-600 mt-1">Submit your reply below to start a thread with the student.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Chat Input -->
                        <div class="p-4 border-t border-slate-800 bg-slate-900/30 rounded-b-2xl">
                            <!-- AI Reply Generator Button -->
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-xs text-slate-500 font-medium">Need assistance?</span>
                                <button type="button" id="ai-smart-reply-btn" class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-violet-650 hover:bg-violet-600 text-white border border-violet-500/30 shadow-md shadow-violet-500/10 transition-all active:scale-[0.98]">
                                    🪄 Generate AI Smart Reply
                                </button>
                            </div>
                            <form action="" method="POST" id="chat-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="send_message">
                                
                                <div class="flex items-center space-x-2">
                                    <textarea id="message_text" name="message_text" rows="1" required
                                        class="flex-1 resize-none bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all max-h-32"
                                        placeholder="Type your message to the student..."></textarea>
                                    
                                    <button type="submit"
                                        class="p-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 transition-all duration-150 active:scale-95 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right 1 Column: Manage Status/Priority Form & Logs -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Status / Priority Controls Box -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 backdrop-blur-xl">
                        <h3 class="text-sm font-bold text-white mb-6 pb-3 border-b border-slate-800 font-sans">Manage Complaint Settings</h3>
                        
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_status_priority">

                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Complaint Status</label>
                                <div class="relative">
                                    <select id="status" name="status" required
                                        class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                        <option value="pending" <?php echo $complaint['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="under_review" <?php echo $complaint['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                        <option value="in_progress" <?php echo $complaint['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $complaint['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $complaint['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        <option value="rejected" <?php echo $complaint['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Priority -->
                            <div>
                                <label for="priority" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Priority Level</label>
                                <div class="relative">
                                    <select id="priority" name="priority" required
                                        class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                        <option value="low" <?php echo $complaint['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $complaint['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $complaint['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Button -->
                            <button type="submit" 
                                class="mt-4 w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-semibold transition-all active:scale-[0.98]">
                                Update Settings
                            </button>
                        </form>
                    </div>

                    <?php if ($admin_role === 'super_admin'): ?>
                    <!-- Route/Assign Department Box (Central Escalation Desk) -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 backdrop-blur-xl">
                        <h3 class="text-sm font-bold text-white mb-6 pb-3 border-b border-slate-800 font-sans">Route Complaint Department</h3>
                        
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_assignment">

                            <!-- Department Dropdown -->
                            <div>
                                <label for="assigned_dept" class="block text-xs font-semibold text-slate-400 uppercase mb-2">Escalation Target</label>
                                <div class="relative">
                                    <select id="assigned_dept" name="assigned_dept"
                                        class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                        <option value="">-- General Queue (Unassigned) --</option>
                                        <option value="Academic Unit" <?php echo $complaint['assigned_dept'] === 'Academic Unit' ? 'selected' : ''; ?>>Academic Unit</option>
                                        <option value="Bursary / Finance" <?php echo $complaint['assigned_dept'] === 'Bursary / Finance' ? 'selected' : ''; ?>>Bursary / Finance</option>
                                        <option value="Student Affairs / Hostel" <?php echo $complaint['assigned_dept'] === 'Student Affairs / Hostel' ? 'selected' : ''; ?>>Student Affairs / Hostel</option>
                                        <option value="Works & Maintenance" <?php echo $complaint['assigned_dept'] === 'Works & Maintenance' ? 'selected' : ''; ?>>Works & Maintenance</option>
                                        <option value="ICT Unit" <?php echo $complaint['assigned_dept'] === 'ICT Unit' ? 'selected' : ''; ?>>ICT Unit</option>
                                        <option value="Health Services" <?php echo $complaint['assigned_dept'] === 'Health Services' ? 'selected' : ''; ?>>Health Services</option>
                                        <option value="Chaplaincy Unit" <?php echo $complaint['assigned_dept'] === 'Chaplaincy Unit' ? 'selected' : ''; ?>>Chaplaincy Unit</option>
                                        <option value="General Admin" <?php echo $complaint['assigned_dept'] === 'General Admin' ? 'selected' : ''; ?>>General Admin</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Route Button -->
                            <button type="submit" 
                                class="mt-4 w-full py-3 bg-indigo-650 hover:bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-600/10 text-sm font-semibold transition-all active:scale-[0.98]">
                                Route Complaint
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Meta information Box -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 backdrop-blur-xl text-sm">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">Timeline Timestamps</h3>
                        <div class="space-y-3">
                            <div>
                                <span class="text-xs text-slate-500 block">Submitted At</span>
                                <p class="text-slate-300 font-mono text-xs"><?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?></p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500 block">Last Activity / Update</span>
                                <p class="text-slate-300 font-mono text-xs"><?php echo date('M d, Y h:i A', strtotime($complaint['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script for menu toggle & auto-scroll chat -->
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

        // Auto-scroll chat area to bottom
        window.addEventListener('DOMContentLoaded', () => {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        // Auto resize chat textarea height
        const textarea = document.getElementById('message_text');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // AI Rule-Based Smart Reply Generation
        const aiReplyBtn = document.getElementById('ai-smart-reply-btn');
        const categoryVal = "<?php echo addslashes($complaint['category']); ?>";
        const statusVal = "<?php echo addslashes($complaint['status']); ?>";

        aiReplyBtn.addEventListener('click', function() {
            let replyText = "";

            // Check Status Specific overrides
            if (statusVal === 'resolved') {
                replyText = `Hello, thank you for your patience. We are pleased to inform you that your complaint regarding "${categoryVal}" has been successfully resolved. This complaint will now be marked as resolved/closed. Please let us know if there is anything else we can assist you with.`;
            } else if (statusVal === 'rejected') {
                replyText = `Hello, after careful review of your complaint regarding "${categoryVal}", we regret to inform you that the request has been rejected. This is typically due to insufficient supporting evidence or policy misalignment. Feel free to submit a new complaint with additional details if needed.`;
            } else if (statusVal === 'in_progress') {
                replyText = `Hello, we want to update you that we are actively working on your complaint regarding "${categoryVal}". The complaint is in progress and has been escalated to the appropriate department desk. We appreciate your patience and will notify you as soon as a resolution is confirmed.`;
            } else {
                // Category Specific defaults for pending / under review
                switch(categoryVal) {
                    case 'Academics':
                        replyText = "Hello, thank you for reaching out. Your academic complaint has been forwarded to the Academic Unit for verification and audit. We will update this complaint once the department's exams or registration officer provides feedback.";
                        break;
                    case 'Bursary / Fees':
                        replyText = "Hello, thank you for reaching out. Your payment or fee query has been routed to the Bursary department for verification. Kindly ensure you upload your payment receipt or Remita slip for faster clearance.";
                        break;
                    case 'Accommodation / Hostels':
                        replyText = "Hello, thank you for reaching out. The Student Affairs hostel management unit has been notified of your accommodation complaint. Please monitor your status for updates regarding room allocations.";
                        break;
                    case 'Maintenance':
                        replyText = "Hello, thank you for reaching out. Our physical planning and maintenance team is investigating this infrastructure/maintenance issue. An engineer will be assigned to resolve this shortly.";
                        break;
                    case 'Information Technology (ICT)':
                        replyText = "Hello, thank you for reaching out. We have forwarded your portal login or connection issue to the ICT department unit for immediate resolution. Please check back in 24 hours.";
                        break;
                    case 'Medical / Health Center':
                        replyText = "Hello, thank you for reaching out. Your health center complaint has been escalated to the Health Services medical director for review.";
                        break;
                    case 'Cafeteria':
                        replyText = "Hello, thank you for reaching out. Your cafeteria feedback has been logged with the Student Affairs food board for immediate inspection.";
                        break;
                    case 'Chapel / Spiritual Life':
                        replyText = "Hello, thank you for reaching out. Your chapel service or spiritual life complaint has been successfully routed to the chaplaincy unit desk.";
                        break;
                    case 'Security & Welfare':
                        replyText = "Hello, thank you for reaching out. Your security alert has been flagged with campus security patrols for immediate follow-up.";
                        break;
                    case 'Library Services':
                        replyText = "Hello, thank you for reaching out. Your library resources query has been forwarded to the chief librarian's helpdesk.";
                        break;
                    default:
                        replyText = "Hello, thank you for reaching out. We have received your complaint and have assigned it to the appropriate resolution officer. We will update you here as soon as we make progress.";
                }
            }

            textarea.value = replyText;
            
            // Trigger textarea auto-resize
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';

            // Flash highlight animation on textarea to notify the admin
            textarea.classList.add('ring-2', 'ring-indigo-500');
            setTimeout(() => {
                textarea.classList.remove('ring-2', 'ring-indigo-500');
            }, 1000);
        });

        // Inline Comment Editing JS triggers
        function showEdit(id) {
            document.getElementById('msg-text-' + id).classList.add('hidden');
            document.getElementById('edit-form-' + id).classList.remove('hidden');
        }

        function cancelEdit(id) {
            document.getElementById('msg-text-' + id).classList.remove('hidden');
            document.getElementById('edit-form-' + id).classList.add('hidden');
        }
    </script>
</body>
</html>
