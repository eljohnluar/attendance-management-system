<?php
// reset_passwords.php - Run this script to hash all passwords
// Access via browser: http://localhost/smart_attendance/reset_passwords.php

require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Tool</title>
    <style>
        body {
            font-family: monospace;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #ff69b4;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Password Reset Tool</h1>
    <div class='info'>
        <strong>Note:</strong> All users have auth_code = <strong>001690</strong>
    </div>";

// Define credentials (all passwords = 'password123' for demo)
$users = [
    'admin' => ['password' => 'password123', 'role' => 'admin'],
    'teacher_jreyes' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_mcruz' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_rsantos' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_agonzales' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_mramos' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_cdelacruz' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_mfernandez' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_rrivera' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_mvillar' => ['password' => 'password123', 'role' => 'teacher'],
    'teacher_jdizon' => ['password' => 'password123', 'role' => 'teacher']
];

echo "<h2>Updating Passwords...</h2>";

$updated = 0;
$errors = 0;

foreach($users as $username => $data) {
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $hashed_password, $username);
    
    if(mysqli_stmt_execute($stmt)) {
        echo "<div class='success'>✓ Updated: $username (" . $data['role'] . ") - Password: " . $data['password'] . " | Auth Code: 001690</div>";
        $updated++;
    } else {
        echo "<div class='error'>✗ Failed: $username - " . mysqli_error($conn) . "</div>";
        $errors++;
    }
    mysqli_stmt_close($stmt);
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<div class='success'>✅ Successfully updated: $updated users</div>";
if($errors > 0) {
    echo "<div class='error'>❌ Failed: $errors users</div>";
}

echo "<hr>";
echo "<h2>Login Credentials</h2>";
echo "<div class='info'><strong>Auth Code for ALL users: 001690</strong></div>";
echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Role</th><th>Auth Code</th></tr>";

foreach($users as $username => $data) {
    echo "<tr>";
    echo "<td><strong>$username</strong></td>";
    echo "<td>{$data['password']}</td>";
    echo "<td>{$data['role']}</td>";
    echo "<td><strong>001690</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Delete this file (reset_passwords.php) for security</strong></li>";
echo "<li>Go to <a href='index.php'>login page</a></li>";
echo "<li>Use the credentials above to login</li>";
echo "<li><strong>Auth code for ALL users is: 001690</strong></li>";
echo "</ol>";

// Verify all users have auth_code = 001690
$check = mysqli_query($conn, "SELECT username, auth_code FROM users WHERE auth_code != '001690'");
if(mysqli_num_rows($check) > 0) {
    echo "<div class='error'>⚠️ Warning: Some users have different auth codes. Run this query to fix: UPDATE users SET auth_code = '001690';</div>";
} else {
    echo "<div class='success'>✓ All users have auth_code = 001690</div>";
}

echo "</body></html>";
?>