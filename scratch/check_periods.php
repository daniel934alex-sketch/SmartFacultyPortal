<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('SELECT * FROM periods ORDER BY period_id ASC');
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
