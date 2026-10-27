<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query("SELECT * FROM timetable WHERE faculty_id = 2 LIMIT 5");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
