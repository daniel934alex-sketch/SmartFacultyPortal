<?php
require_once "config/database.php";

$facUsers = ['faculty01', 'faculty02'];
foreach ($facUsers as $u) {
    $r = $conn->query("SELECT * FROM users WHERE username = '$u'")->fetch_assoc();
    if ($r) {
        foreach (['Faculty@123', 'faculty@123', 'Faculty@01', 'faculty@01', 'Faculty@02', 'faculty@02', 'password123', 'password'] as $pw) {
            if (password_verify($pw, $r['password'])) {
                echo "$u password is '$pw'\n";
                break;
            }
        }
    }
}
