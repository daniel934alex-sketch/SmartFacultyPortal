<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = 'smart_faculty' 
      AND REFERENCED_TABLE_NAME = 'faculty_availability'
");
$cnt = 0;
while ($r = $res->fetch_assoc()) {
    print_r($r);
    $cnt++;
}
if ($cnt === 0) {
    echo "No tables reference faculty_availability.\n";
}
