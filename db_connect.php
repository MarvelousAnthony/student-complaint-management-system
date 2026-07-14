<?php
/**
 * db_connect.php
 * 
 * Database connection file for the Student Complaint Management System.
 * Connects to MySQL using standard procedural mysqli and handles errors securely.
 */

// Enable strict error reporting for mysqli (default in PHP 8, but good practice to ensure)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Only set cookie params if headers are not sent
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie (expires when browser closes)
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

/**
 * Generate and store a CSRF token in the session if it doesn't exist.
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if the provided token matches the session CSRF token.
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}


// Database configuration constants (default XAMPP settings)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'student_complaint_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

try {
    // Establish connection using mysqli with SSL support for cloud providers
    $conn = mysqli_init();
    if (!$conn) {
        throw new mysqli_sql_exception("mysqli_init failed");
    }

    $ca_cert = __DIR__ . '/ca.pem';
    $use_ssl = file_exists($ca_cert) && getenv('DB_HOST');
    
    if ($use_ssl) {
        mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);
    }

    $flags = $use_ssl ? MYSQLI_CLIENT_SSL : 0;
    
    // Connect to the database server
    if (!@mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, $flags)) {
        throw new mysqli_sql_exception(mysqli_connect_error(), mysqli_connect_errno());
    }
    
    // Set the default client character set to utf8mb4 for unicode/emoji support
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Auto-migration: Check and add assigned_dept column if it does not exist
    try {
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM `complaints` LIKE 'assigned_dept'");
        if ($check_col && mysqli_num_rows($check_col) == 0) {
            mysqli_query($conn, "ALTER TABLE `complaints` ADD COLUMN `assigned_dept` VARCHAR(150) DEFAULT NULL AFTER `attachment_path`");
        }
    } catch (Exception $e) {
        error_log("Schema migration check failed: " . $e->getMessage());
    }
    
} catch (mysqli_sql_exception $e) {
    // Log the actual error to the server error log securely
    error_log("Database connection failed: " . $e->getMessage());
    
    // Render a user-friendly, secure error page that does not leak connection details
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error</title>
        <!-- Tailwind CSS via CDN for styling the error page -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
        </style>
    </head>
    <body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen p-6">
        <div class="max-w-md w-full bg-slate-800/50 backdrop-blur-md rounded-2xl border border-slate-700/50 p-8 shadow-2xl text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-rose-500/10 text-rose-500 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-50 mb-2">System Temporary Offline</h1>
            <p class="text-slate-400 text-sm leading-relaxed mb-6">
                We are experiencing technical difficulties connecting to our database. Please check back shortly.
            </p>
            <div class="text-xs text-slate-500 bg-slate-900/60 rounded-lg p-3 text-left font-mono break-words border border-slate-800">
                <span class="text-rose-400 font-semibold">Error Context:</span> Connection timed out or database server is offline.
            </div>
            <button onclick="window.location.reload();" class="mt-6 w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition-all duration-200 shadow-lg shadow-indigo-600/20 active:scale-[0.98]">
                Retry Connection
            </button>
        </div>
    </body>
    </html>
    <?php
    exit();
}
