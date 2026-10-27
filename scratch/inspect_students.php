<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT s.student_id, s.register_number, s.student_name, s.section_id, sec.section_name, u.username, u.role, u.status
    FROM students s
    JOIN sections sec ON sec.section_id = s.section_id
    JOIN users u ON u.user_id = s.user_id
    ORDER BY s.section_id, s.student_id
");

echo "=== STUDENTS IN DATABASE ===\n";
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['student_id']} | {$r['register_number']} | {$r['student_name']} | Section: {$r['section_name']} (ID {$r['section_id']}) | Username: {$r['username']} | Role: {$r['role']}\n";
}
