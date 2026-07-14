<?php
require_once 'db_connect.php';

// Secure access: student role check and POST request validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// 1. Verify CSRF Token
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    $_SESSION['error'] = "Security validation failed. Invalid CSRF token.";
    header("Location: submit_complaint.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// 2. Fetch and Sanitize Inputs
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$priority = trim($_POST['priority'] ?? '');
$description = trim($_POST['description'] ?? '');

// Backend Validations
if (empty($title) || empty($category) || empty($priority) || empty($description)) {
    $_SESSION['error'] = "All standard fields (Title, Category, Priority, Description) are required.";
    header("Location: submit_complaint.php");
    exit();
}

if (strlen($title) < 5) {
    $_SESSION['error'] = "Complaint Title must be at least 5 characters.";
    header("Location: submit_complaint.php");
    exit();
}

if (strlen($description) < 15) {
    $_SESSION['error'] = "Description must detail the issue (at least 15 characters).";
    header("Location: submit_complaint.php");
    exit();
}

// Validate priorities and categories enum
$valid_priorities = ['low', 'medium', 'high'];
if (!in_array(strtolower($priority), $valid_priorities)) {
    $_SESSION['error'] = "Invalid priority level selected.";
    header("Location: submit_complaint.php");
    exit();
}

$valid_categories = [
    'Academics',
    'Bursary / Fees',
    'Accommodation / Hostels',
    'Maintenance',
    'Information Technology (ICT)',
    'Medical / Health Center',
    'Cafeteria',
    'Chapel / Spiritual Life',
    'Security & Welfare',
    'Library Services',
    'Other / General'
];
if (!in_array($category, $valid_categories)) {
    $_SESSION['error'] = "Invalid category selected.";
    header("Location: submit_complaint.php");
    exit();
}

// Auto-routing to department based on selected category
$routing_map = [
    'Academics' => 'Academic Unit',
    'Bursary / Fees' => 'Bursary / Finance',
    'Accommodation / Hostels' => 'Student Affairs / Hostel',
    'Maintenance' => 'Works & Maintenance',
    'Information Technology (ICT)' => 'ICT Unit',
    'Medical / Health Center' => 'Health Services',
    'Cafeteria' => 'Student Affairs / Hostel',
    'Chapel / Spiritual Life' => 'Chaplaincy Unit',
    'Security & Welfare' => 'General Admin',
    'Library Services' => 'General Admin',
    'Other / General' => 'General Admin'
];
$assigned_dept = $routing_map[$category] ?? null;

$attachment_path = null;

// 3. File Upload Processing
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];

    // Check for uploading errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "File upload failed with error code: " . $file['error'];
        header("Location: submit_complaint.php");
        exit();
    }

    // Check size limit (5MB = 5 * 1024 * 1024 bytes)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "Attachment size exceeds the 5MB limit.";
        header("Location: submit_complaint.php");
        exit();
    }

    // Check file extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_info = pathinfo($file['name']);
    $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';

    if (!in_array($extension, $allowed_extensions)) {
        $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and PDF files are allowed.";
        header("Location: submit_complaint.php");
        exit();
    }

    // Ensure uploads/ directory exists
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create uploads directory");
            $_SESSION['error'] = "Internal file system error. Please contact administration.";
            header("Location: submit_complaint.php");
            exit();
        }
    }

    // Generate unique safe name for file
    // strip out characters that aren't letters, numbers, dot or underscore
    $safe_name = preg_replace("/[^a-zA-Z0-9\._]/", "", $file_info['filename']);
    $unique_filename = uniqid('comp_', true) . '_' . $safe_name . '.' . $extension;
    $target_filepath = $upload_dir . $unique_filename;

    // Move uploaded file to destination
    if (move_uploaded_file($file['tmp_name'], $target_filepath)) {
        $attachment_path = $target_filepath;
    } else {
        error_log("Failed to move uploaded file to target location: " . $target_filepath);
        $_SESSION['error'] = "Failed to store the uploaded attachment. Please try again.";
        header("Location: submit_complaint.php");
        exit();
    }
}

// 4. Save Complaint to Database & Notify Admins
mysqli_begin_transaction($conn);

try {
    // A. Insert complaint record
    $insert_query = "
        INSERT INTO complaints (student_id, title, description, category, priority, status, attachment_path, assigned_dept) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
    ";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "issssss", $student_id, $title, $description, $category, $priority, $attachment_path, $assigned_dept);
    mysqli_stmt_execute($stmt);
    
    // Retrieve the ID of the new complaint
    $complaint_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // B. Trigger Notification for admin and super_admin users
    $admins_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
    $admins_result = mysqli_query($conn, $admins_query);

    if ($admins_result) {
        $notif_query = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
        $notif_stmt = mysqli_prepare($conn, $notif_query);
        
        $notif_message = "New [{$category}] Complaint #{$complaint_id} submitted by student {$student_name}: \"{$title}\"";
        
        while ($admin = mysqli_fetch_assoc($admins_result)) {
            $admin_id = $admin['id'];
            mysqli_stmt_bind_param($notif_stmt, "is", $admin_id, $notif_message);
            mysqli_stmt_execute($notif_stmt);
        }
        mysqli_stmt_close($notif_stmt);
    }

    // Commit Transaction
    mysqli_commit($conn);

    $_SESSION['success'] = "Complaint #{$complaint_id} has been successfully submitted and routed to administrators.";
    header("Location: student_dashboard.php");
    exit();

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    error_log("Database transaction failed in process_complaint.php: " . $e->getMessage());

    // Clean up uploaded file if database insert failed
    if ($attachment_path && file_exists($attachment_path)) {
        unlink($attachment_path);
    }

    $_SESSION['error'] = "A database error occurred. Your complaint could not be saved.";
    header("Location: submit_complaint.php");
    exit();
}
