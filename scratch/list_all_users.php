<?php
require_once "config/database.php";

$res = $conn->query("SELECT user_id, username, role FROM users");
while ($r = $res->fetch_assoc()) {
    echo "{$r['user_id']} | {$r['username']} | {$r['role']}\n";
}
