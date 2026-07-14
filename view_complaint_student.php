<?php
require_once 'db_connect.php';

// Secure access: student role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Validate GET parameters
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id <= 0) {
    $_SESSION['error'] = "Invalid complaint ID.";
    header("Location: student_dashboard.php");
    exit();
}

// ----------------------------------------------------
// 1. Process New Message Submission (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_student.php?id=" . $complaint_id);
        exit();
    }

    $message_text = trim($_POST['message_text'] ?? '');
    if (empty($message_text)) {
        $_SESSION['error'] = "Message cannot be empty.";
        header("Location: view_complaint_student.php?id=" . $complaint_id);
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        // Double check IDOR: Verify complaint belongs to the student before letting them post a message
        $verify_query = "SELECT id, title FROM complaints WHERE id = ? AND student_id = ? LIMIT 1";
        $v_stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($v_stmt, "ii", $complaint_id, $student_id);
        mysqli_stmt_execute($v_stmt);
        mysqli_stmt_store_result($v_stmt);
        
        if (mysqli_stmt_num_rows($v_stmt) === 0) {
            mysqli_stmt_close($v_stmt);
            throw new Exception("Unauthorized message insertion attempt.");
        }
        
        mysqli_stmt_bind_result($v_stmt, $temp_id, $complaint_title);
        mysqli_stmt_fetch($v_stmt);
        mysqli_stmt_close($v_stmt);

        // A. Insert message
        $msg_query = "INSERT INTO messages (complaint_id, sender_id, message_text) VALUES (?, ?, ?)";
        $msg_stmt = mysqli_prepare($conn, $msg_query);
        mysqli_stmt_bind_param($msg_stmt, "iis", $complaint_id, $student_id, $message_text);
        mysqli_stmt_execute($msg_stmt);
        mysqli_stmt_close($msg_stmt);

        // B. Update complaint's updated_at timestamp
        $update_query = "UPDATE complaints SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $up_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($up_stmt, "i", $complaint_id);
        mysqli_stmt_execute($up_stmt);
        mysqli_stmt_close($up_stmt);

        // C. Notify Admin and Super Admin users
        $admins_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
        $admins_result = mysqli_query($conn, $admins_query);
        if ($admins_result) {
            $notif_query = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
            $notif_stmt = mysqli_prepare($conn, $notif_query);
            
            $truncated_msg = strlen($message_text) > 60 ? substr($message_text, 0, 57) . '...' : $message_text;
            $notif_message = "New message on Complaint #{$complaint_id} by student {$student_name}: \"{$truncated_msg}\"";
            
            while ($admin = mysqli_fetch_assoc($admins_result)) {
                $admin_id = $admin['id'];
                mysqli_stmt_bind_param($notif_stmt, "is", $admin_id, $notif_message);
                mysqli_stmt_execute($notif_stmt);
            }
            mysqli_stmt_close($notif_stmt);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Reply successfully sent.";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Failed to process message submission: " . $e->getMessage());
        $_SESSION['error'] = "Failed to send message. Please try again.";
    }

    header("Location: view_complaint_student.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 1b. Process Message Deletion (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_student.php?id=" . $complaint_id);
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

        // Validate sender_id matches logged-in user
        if ($msg['sender_id'] != $student_id) {
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

    header("Location: view_complaint_student.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 1c. Process Message Editing (POST Request)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_message') {
    // Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
        header("Location: view_complaint_student.php?id=" . $complaint_id);
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

        // Validate sender_id matches logged-in user
        if ($msg['sender_id'] != $student_id) {
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

    header("Location: view_complaint_student.php?id=" . $complaint_id);
    exit();
}

// ----------------------------------------------------
// 2. Fetch Complaint Details (with IDOR protection)
// ----------------------------------------------------
$complaint = null;
try {
    $detail_query = "
        SELECT id, title, description, category, priority, status, attachment_path, created_at, updated_at, assigned_dept 
        FROM complaints 
        WHERE id = ? AND student_id = ? 
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $detail_query);
    mysqli_stmt_bind_param($stmt, "ii", $complaint_id, $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$complaint = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        $_SESSION['error'] = "Complaint not found or access denied.";
        header("Location: student_dashboard.php");
        exit();
    }
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log("DB Error fetching complaint detail: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching details.";
    header("Location: student_dashboard.php");
    exit();
}

// ----------------------------------------------------
// 3. Fetch Message History
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
    <title>Complaint #<?php echo $complaint['id']; ?> | Student Complaint Management System</title>
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
            
            <div class="flex items-center space-x-4">
                <a href="student_dashboard.php" class="text-slate-400 hover:text-white flex items-center text-sm font-semibold transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <!-- Ticket ID Header badge -->
            <div>
                <span class="text-xs px-3 py-1.5 bg-slate-900 border border-slate-800 text-indigo-400 rounded-full font-mono font-semibold">
                    Complaint #<?php echo $complaint['id']; ?>
                </span>
            </div>
        </header>

        <!-- Mobile Drawer Navigation -->
        <div id="mobile-menu" class="fixed inset-0 z-30 bg-slate-950/80 backdrop-blur-sm hidden flex md:hidden">
            <div class="w-64 bg-slate-900 h-full p-6 border-r border-slate-800 flex flex-col justify-between">
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
                        <a href="student_dashboard.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
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

        <!-- Scrollable content split into Complaint Details & Discussion -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Alert Display -->
            <?php if ($success_msg): ?>
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3 max-w-5xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3 max-w-5xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Split Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl">
                
                <!-- Left 2 Columns: Complaint Details & Chat -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Complaint Details Card -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 relative overflow-hidden backdrop-blur-xl">
                        <!-- Upper Detail Info -->
                        <div class="flex flex-wrap justify-between items-start gap-4 pb-6 border-b border-slate-800">
                            <div>
                                <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest block">Complaint Title</span>
                                <h2 class="text-xl font-bold text-white mt-1"><?php echo htmlspecialchars($complaint['title']); ?></h2>
                            </div>
                            <div class="flex items-center space-x-2">
                                <!-- Status Badge -->
                                <?php 
                                $status = strtolower($complaint['status']);
                                $s_class = 'bg-slate-800 text-slate-400 border-slate-700';
                                if ($status === 'pending') $s_class = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                elseif ($status === 'under_review') $s_class = 'bg-violet-500/10 text-violet-400 border-violet-500/20';
                                elseif ($status === 'in_progress') $s_class = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                elseif ($status === 'resolved') $s_class = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                                elseif ($status === 'closed') $s_class = 'bg-slate-700/10 text-slate-400 border-slate-700/20';
                                elseif ($status === 'rejected') $s_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?php echo $s_class; ?>">
                                    <?php echo str_replace('_', ' ', $status); ?>
                                </span>

                                <!-- Priority Badge -->
                                <?php 
                                $priority = strtolower($complaint['priority']);
                                $p_class = 'bg-slate-800 text-slate-400 border-slate-700';
                                if ($priority === 'low') $p_class = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                elseif ($priority === 'medium') $p_class = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                elseif ($priority === 'high') $p_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?php echo $p_class; ?>">
                                    <?php echo $priority; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Complaint Body -->
                        <div class="py-6 space-y-6">
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
                                <span class="text-xs font-semibold text-slate-500 uppercase">Description</span>
                                <p class="text-slate-300 text-sm leading-relaxed whitespace-pre-wrap mt-1 border border-slate-800 bg-slate-950/40 rounded-xl p-4"><?php echo htmlspecialchars($complaint['description']); ?></p>
                            </div>

                            <!-- Attachment link -->
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

                    <!-- Chat / Conversation Discussion Area -->
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl flex flex-col backdrop-blur-xl h-[500px]">
                        <!-- Conversation Header -->
                        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/40 flex justify-between items-center rounded-t-2xl">
                            <h3 class="text-sm font-bold text-white">Complaint Activity & Replies</h3>
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
                                        // Identify sender type
                                        $is_student = ($msg['role'] === 'student');
                                        // Verify if the sender is actually this current student
                                        $is_me = ($is_student && $msg['sender_id'] == $student_id);
                                        
                                        // Chat bubble styling
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

                                                        <!-- Subtle edit/delete triggers -->
                                                        <div class="flex justify-end space-x-2 mt-2 pt-1.5 border-t border-white/10 text-[10px] text-white/50 opacity-60 group-hover:opacity-100 transition-opacity">
                                                            <button type="button" onclick="showEdit(<?php echo $msg['message_id']; ?>)" class="hover:text-white transition-colors">Edit</button>
                                                            <span>&bull;</span>
                                                            <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this reply?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                <input type="hidden" name="action" value="delete_message">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                                <button type="submit" class="hover:text-rose-300 transition-colors">Delete</button>
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
                                    <p class="text-xs text-slate-600 mt-1">Submit your reply below to start direct communication with the administrators.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Chat input footer -->
                        <div class="p-4 border-t border-slate-800 bg-slate-900/30 rounded-b-2xl">
                            <form action="" method="POST" id="chat-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="send_message">
                                
                                <div class="flex items-center space-x-2">
                                    <textarea id="message_text" name="message_text" rows="1" required
                                        class="flex-1 resize-none bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all max-h-32"
                                        placeholder="Type your message here..."></textarea>
                                    
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

                <!-- Right 1 Column: Vertical Progression Timeline -->
                <div class="lg:col-span-1">
                    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-xl p-6 backdrop-blur-xl">
                        <h3 class="text-sm font-bold text-white mb-6 pb-3 border-b border-slate-800">Processing Progress</h3>
                        
                        <!-- Progression steps list -->
                        <div class="relative pl-6 space-y-8 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-800">
                            <?php 
                            // Determine step completion levels based on status
                            // Statuses: pending, under_review, in_progress, resolved, closed, rejected
                            
                            $is_pending = ($status === 'pending');
                            $is_under_review = ($status === 'under_review');
                            $is_in_progress = ($status === 'in_progress');
                            $is_resolved = ($status === 'resolved');
                            $is_closed = ($status === 'closed');
                            $is_rejected = ($status === 'rejected');

                            // Timeline steps status configurations:
                            // Step 1: Filed
                            $step1_active = true;
                            $step1_label = "Complaint Submitted";
                            $step1_date = date('M d, Y', strtotime($complaint['created_at']));
                            
                            // Step 2: Under Review / Rejected
                            $step2_active = ($is_under_review || $is_in_progress || $is_resolved || $is_closed || $is_rejected);
                            if ($is_rejected) {
                                $step2_label = "Complaint Rejected";
                                $step2_desc = "The complaint has been rejected by administrators.";
                                $step2_color = "bg-rose-500 shadow-rose-500/20";
                                $step2_border = "border-rose-500";
                            } else {
                                $step2_label = "Under Investigation";
                                $step2_desc = "Admin has reviewed and assigned this complaint.";
                                $step2_color = $step2_active ? "bg-indigo-500 shadow-indigo-500/20" : "bg-slate-800";
                                $step2_border = $step2_active ? "border-indigo-500" : "border-slate-800";
                            }

                            // Step 3: In Progress (skip if rejected)
                            $step3_active = ($is_in_progress || $is_resolved || $is_closed) && !$is_rejected;
                            $step3_color = $step3_active ? "bg-indigo-500/50" : "bg-slate-800";
                            $step3_border = $step3_active ? "border-indigo-500" : "border-slate-800";

                            // Step 4: Resolved / Closed
                            $step4_active = ($is_resolved || $is_closed) && !$is_rejected;
                            $step4_label = $is_closed ? "Complaint Closed" : "Complaint Resolved";
                            $step4_color = $step4_active ? "bg-emerald-500 shadow-emerald-500/20" : "bg-slate-800";
                            $step4_border = $step4_active ? "border-emerald-500" : "border-slate-800";
                            $step4_date = $step4_active ? date('M d, Y', strtotime($complaint['updated_at'])) : null;
                            ?>

                            <!-- Step 1: Filed -->
                            <div class="relative group">
                                <span class="absolute -left-6 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-500 shadow-lg shadow-indigo-500/20 border border-indigo-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                </span>
                                <h4 class="text-sm font-semibold text-white"><?php echo $step1_label; ?></h4>
                                <p class="text-xs text-slate-400 mt-0.5">Your complaint was logged into the system.</p>
                                <p class="text-[10px] text-slate-500 font-semibold mt-1 font-mono"><?php echo $step1_date; ?></p>
                            </div>

                            <!-- Step 2: Under Review -->
                            <div class="relative group">
                                <span class="absolute -left-6 top-1.5 flex h-4 w-4 items-center justify-center rounded-full border transition-all <?php echo $step2_color; ?> <?php echo $step2_border; ?>">
                                    <?php if ($step2_active): ?>
                                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    <?php endif; ?>
                                </span>
                                <h4 class="text-sm font-semibold <?php echo $step2_active ? 'text-white' : 'text-slate-500'; ?>">
                                    <?php echo $step2_label; ?>
                                </h4>
                                <p class="text-xs <?php echo $step2_active ? 'text-slate-400' : 'text-slate-600'; ?> mt-0.5">
                                    <?php echo isset($step2_desc) ? $step2_desc : 'Administrators are currently reviewing details.'; ?>
                                </p>
                            </div>

                            <!-- Step 3: In Progress -->
                            <?php if (!$is_rejected): ?>
                                <div class="relative group">
                                    <span class="absolute -left-6 top-1.5 flex h-4 w-4 items-center justify-center rounded-full border transition-all <?php echo $step3_color; ?> <?php echo $step3_border; ?>">
                                        <?php if ($step3_active): ?>
                                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                        <?php endif; ?>
                                    </span>
                                    <h4 class="text-sm font-semibold <?php echo $step3_active ? 'text-white' : 'text-slate-500'; ?>">Action Initiated</h4>
                                    <p class="text-xs <?php echo $step3_active ? 'text-slate-400' : 'text-slate-600'; ?> mt-0.5">Resolution workflows are active.</p>
                                </div>
                            <?php endif; ?>

                            <!-- Step 4: Resolved / Closed -->
                            <div class="relative group">
                                <span class="absolute -left-6 top-1.5 flex h-4 w-4 items-center justify-center rounded-full border transition-all <?php echo $step4_color; ?> <?php echo $step4_border; ?>">
                                    <?php if ($step4_active): ?>
                                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    <?php endif; ?>
                                </span>
                                <h4 class="text-sm font-semibold <?php echo $step4_active ? ($is_closed ? 'text-slate-400' : 'text-emerald-400') : 'text-slate-500'; ?>">
                                    <?php echo $step4_label; ?>
                                </h4>
                                <p class="text-xs <?php echo $step4_active ? 'text-slate-400' : 'text-slate-600'; ?> mt-0.5">The issue is successfully closed.</p>
                                <?php if ($step4_date): ?>
                                    <p class="text-[10px] text-slate-500 font-semibold mt-1 font-mono"><?php echo $step4_date; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script for mobile menu toggle & auto-scroll chat -->
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
        }

        // Auto-scroll chat area to bottom on load
        window.addEventListener('DOMContentLoaded', () => {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        // Auto resize message textarea height
        const textarea = document.getElementById('message_text');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

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
