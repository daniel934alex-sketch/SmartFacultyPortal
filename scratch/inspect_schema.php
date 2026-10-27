<?php
require_once __DIR__ . '/../config/database.php';

function inspectTable($conn, $table) {
    echo "\n================ TABLE: $table ================\n";
    $countRes = $conn->query("SELECT COUNT(*) FROM `$table`");
    $count = $countRes ? $countRes->fetch_row()[0] : 'ERR';
    echo "Row count: $count\n";

    $createRes = $conn->query("SHOW CREATE TABLE `$table`");
    if ($createRes) {
        $row = $createRes->fetch_row();
        echo $row[1] . "\n";
    }
}

$tables = ['timetable', 'sections', 'subjects', 'classrooms', 'periods', 'faculty', 'faculty_subjects', 'ee_projects'];
foreach ($tables as $t) {
    inspectTable($conn, $t);
}
