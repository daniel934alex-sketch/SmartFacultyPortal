<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("SELECT user_id, username, password, role FROM users LIMIT 10");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['user_id']} | User: {$r['username']} | Role: {$r['role']} | Pass len: " . strlen($r['password']) . " | Substr: " . substr($r['password'], 0, 15) . "\n";
}
