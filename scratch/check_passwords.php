<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT user_id, username, password, role FROM users WHERE username = 'faculty01'");
$r = $res->fetch_assoc();
echo "User: {$r['username']} | Pass hash: {$r['password']}\n";
foreach (['Faculty@123', 'faculty@123', 'Faculty123', 'password', 'faculty01', 'CSE002', 'admin@123', 'Student@123', 'secret'] as $p) {
    if (password_verify($p, $r['password'])) {
        echo "   -> MATCH: '$p'\n";
    }
}
