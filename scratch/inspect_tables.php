<?php
require_once "config/database.php";

echo "--- DESCRIBE USERS ---\n";
$res = $conn->query("DESCRIBE users");
while ($r = $res->fetch_assoc()) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- DESCRIBE FACULTY ---\n";
$res = $conn->query("DESCRIBE faculty");
while ($r = $res->fetch_assoc()) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- DESCRIBE TIMETABLE ---\n";
$res = $conn->query("DESCRIBE timetable");
while ($r = $res->fetch_assoc()) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- DESCRIBE SECTIONS ---\n";
$res = $conn->query("DESCRIBE sections");
while ($r = $res->fetch_assoc()) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- HOD / ADMIN USERS ---\n";
$res = $conn->query("SELECT * FROM users WHERE role IN ('HOD', 'ADMIN')");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- FACULTY RECORDS ---\n";
$res = $conn->query("SELECT * FROM faculty");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
