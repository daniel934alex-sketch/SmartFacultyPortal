<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('SELECT notification_id, user_id, title, message, notification_type, is_read, created_at FROM notifications ORDER BY notification_id DESC LIMIT 5');
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;
}
