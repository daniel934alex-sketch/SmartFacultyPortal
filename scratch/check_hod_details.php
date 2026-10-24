<?php
require_once "config/database.php";

$res = $conn->query("
    SELECT u.user_id, u.username, u.role, f.faculty_id, f.faculty_code, f.faculty_name, f.designation, d.department_name, d.department_code
    FROM users u
    LEFT JOIN faculty f ON f.user_id = u.user_id
    LEFT JOIN departments d ON d.department_id = f.department_id
    WHERE u.role = 'HOD'
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

// Let's also check all sections and classes in database
$res = $conn->query("
    SELECT sec.section_id, sec.section_name, ac.class_id, ac.year_number, ac.semester_number, ac.academic_year, d.department_name
    FROM sections sec
    JOIN academic_classes ac ON sec.class_id = ac.class_id
    LEFT JOIN departments d ON ac.department_id = d.department_id
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
