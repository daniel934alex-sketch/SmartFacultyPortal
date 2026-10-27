<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('SHOW TABLES');
while ($row = $r->fetch_array()) {
    echo $row[0] . PHP_EOL;
}
