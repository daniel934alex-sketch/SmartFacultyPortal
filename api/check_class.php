<?php

session_start();

header("Content-Type: application/json");

require_once "../config/database.php";


// ========================================
// 1. CHECK LOGIN
// ========================================

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" => "User session not found. Please login again."
    ]);

    exit;
}


// ========================================
// 2. CHECK FACULTY ROLE
// ========================================

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FACULTY') {

    echo json_encode([
        "success" => false,
        "message" => "Only faculty can use this API."
    ]);

    exit;
}


// ========================================
// 3. GET USER ID
// ========================================

$user_id = (int) $_SESSION['user_id'];


// ========================================
// 4. GET DATE AND PERIOD
// ========================================

$date = trim($_POST['date'] ?? '');
$period_id = (int) ($_POST['period_id'] ?? 0);


// ========================================
// 5. VALIDATE INPUT
// ========================================

if ($date === '' || $period_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Date and period are required."
    ]);

    exit;
}


// ========================================
// 6. GET FACULTY ID
// ========================================

$stmt = $conn->prepare("
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Faculty query failed: " . $conn->error
    ]);

    exit;
}

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Faculty record not found."
    ]);

    $stmt->close();
    exit;
}


$faculty = $result->fetch_assoc();

$faculty_id = (int) $faculty['faculty_id'];

$stmt->close();


// ========================================
// 7. CONVERT DATE TO DAY
// ========================================

$day = strtoupper(date("l", strtotime($date)));


// Example:
// 2026-09-21 → MONDAY
// 2026-09-22 → TUESDAY


// ========================================
// 8. CHECK TIMETABLE
// ========================================

$stmt = $conn->prepare("
    SELECT
        timetable_id,
        day,
        period_id,
        section_id,
        subject_id,
        faculty_id,
        classroom_id,
        status
    FROM timetable
    WHERE faculty_id = ?
      AND day = ?
      AND period_id = ?
    LIMIT 1
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Timetable query failed: " . $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "isi",
    $faculty_id,
    $day,
    $period_id
);


$stmt->execute();

$result = $stmt->get_result();


// ========================================
// 9. CLASS FOUND
// ========================================

if ($result->num_rows === 1) {

    $class = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "class_found" => true,
        "message" => "Class found for this faculty.",
        "day" => $day,
        "class" => $class
    ]);

} else {

    echo json_encode([
        "success" => true,
        "class_found" => false,
        "message" => "No class scheduled for this faculty in this period.",
        "day" => $day
    ]);
}


$stmt->close();

$conn->close();

?>