<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('SELECT u.user_id, u.username, u.role, u.status, f.faculty_id, f.faculty_name, f.is_hod, f.department_id FROM users u LEFT JOIN faculty f ON f.user_id = u.user_id');
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
