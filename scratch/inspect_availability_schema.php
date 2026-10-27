<?php
require_once __DIR__ . '/../config/database.php';

function showCreate($conn, $table) {
    echo "\n=== $table ===\n";
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res) {
        echo $res->fetch_row()[1] . "\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    $res2 = $conn->query("SELECT * FROM `$table` LIMIT 10");
    if ($res2) {
        echo "Rows: " . $res2->num_rows . "\n";
        while ($r = $res2->fetch_assoc()) {
            print_r($r);
        }
    }
}

showCreate($conn, 'faculty_availability');
showCreate($conn, 'notifications');
showCreate($conn, 'handover_requests');
showCreate($conn, 'class_handover');
