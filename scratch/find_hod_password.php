<?php
require_once "config/database.php";

$passwordsToTest = ['admin123', 'admin@123', 'hod@123', 'hod123', 'password', 'password123', 'admin', 'cse@123', 'arun@123'];
$user = $conn->query("SELECT * FROM users WHERE username = 'hod.cse'")->fetch_assoc();

if ($user) {
    echo "User found: " . $user['username'] . "\n";
    foreach ($passwordsToTest as $pw) {
        if (password_verify($pw, $user['password'])) {
            echo "MATCH FOUND: password is '$pw'\n";
            break;
        }
    }
}
