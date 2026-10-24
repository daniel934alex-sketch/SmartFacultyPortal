<?php
require_once "config/database.php";

$res = $conn->query("SELECT user_id, username, password FROM users WHERE user_id IN (2, 3, 16)");
while ($r = $res->fetch_assoc()) {
    echo "{$r['username']} (hash: " . substr($r['password'], 0, 15) . "...)\n";
    if (password_verify('faculty@01', $r['password'])) echo "  verified faculty@01\n";
    if (password_verify('Faculty@123', $r['password'])) echo "  verified Faculty@123\n";
    if (password_verify('Admin@123', $r['password'])) echo "  verified Admin@123\n";
    if (password_verify('Student@123', $r['password'])) echo "  verified Student@123\n";
}
