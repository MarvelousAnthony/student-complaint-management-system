<?php
/**
 * create_admin.php
 * 
 * One-time setup utility script to create default Administrator accounts for testing.
 * IMPORTANT: Delete this file after running it in your XAMPP server!
 */

require_once 'db_connect.php';

echo "<h2 style='font-family: sans-serif; color: #333;'>SCMS Admin Accounts Initializer</h2>";

// Setup default credentials
$admins = [
    [
        'full_name' => 'System Admin',
        'matric_no_staff_id' => '26/AU/0001',
        'email' => 'admin@university.edu',
        'password' => 'AdelekeAdmin#2026!',
        'role' => 'admin',
        'department' => 'General Admin'
    ],
    [
        'full_name' => 'Super Administrator',
        'matric_no_staff_id' => '26/AU/0002',
        'email' => 'superadmin@university.edu',
        'password' => 'AdelekeSuperAdmin#2026!',
        'role' => 'super_admin',
        'department' => 'Management'
    ]
];

foreach ($admins as $admin) {
    try {
        // Check if exists
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $admin['email']);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        
        if (mysqli_stmt_num_rows($check) > 0) {
            echo "<p style='font-family: sans-serif; color: #e59866;'>Account already exists for: <strong>{$admin['email']}</strong></p>";
            mysqli_stmt_close($check);
            continue;
        }
        mysqli_stmt_close($check);

        // Create user
        $hashed = password_hash($admin['password'], PASSWORD_DEFAULT);
        $insert = mysqli_prepare($conn, "INSERT INTO users (full_name, matric_no_staff_id, email, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert, "ssssss", $admin['full_name'], $admin['matric_no_staff_id'], $admin['email'], $hashed, $admin['role'], $admin['department']);
        
        if (mysqli_stmt_execute($insert)) {
            echo "<p style='font-family: sans-serif; color: #27ae60;'>Admin account successfully created:<br>";
            echo "Email: <strong>{$admin['email']}</strong><br>";
            echo "Password: <strong>{$admin['password']}</strong><br>";
            echo "Role: <strong>{$admin['role']}</strong></p><hr>";
        } else {
            echo "<p style='font-family: sans-serif; color: #c0392b;'>Failed to create account: {$admin['email']}</p>";
        }
        mysqli_stmt_close($insert);

    } catch (mysqli_sql_exception $e) {
        echo "<p style='font-family: sans-serif; color: #c0392b;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p style='font-family: sans-serif; color: #7f8c8d; font-size: 12px;'>Note: Please delete this file (<code>create_admin.php</code>) from your project folder now to secure your server.</p>";
