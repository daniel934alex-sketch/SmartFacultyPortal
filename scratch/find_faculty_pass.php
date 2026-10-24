<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT password FROM users WHERE username = 'faculty01'");
$hash = $res->fetch_assoc()['password'];

$candidates = [
    'faculty01', 'Faculty01', 'Faculty@01', 'faculty@01',
    'Faculty@123', 'faculty@123', 'Faculty123', 'faculty123',
    'Faculty#123', 'Faculty_123', 'Faculty.123',
    'Priya@123', 'priya@123', 'Priya123', 'priya123',
    'CSE002', 'cse002', 'CSE002@123',
    'admin', 'Admin@123', 'Student@123', 'password', '12345678', '123456',
    'smartfaculty', 'smart_faculty', 'portal', 'smart'
];

foreach ($candidates as $cand) {
    if (password_verify($cand, $hash)) {
        echo "FOUND MATCH: '$cand'\n";
        exit;
    }
}
echo "No match in candidates list\n";
