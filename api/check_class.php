<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/database.php";

// ========================================
// 1. CHECK LOGIN
// ========================================

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "User session not found. Please login again."
    ]);
    exit;
}

// ========================================
// 2. CHECK FACULTY ROLE
// ========================================

if (!isset($_SESSION['role']) || strtoupper((string)$_SESSION['role']) !== 'FACULTY') {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Only faculty can use this API."
    ]);
    exit;
}

// ========================================
// 3. GET INPUT
// ========================================

$user_id = (int)$_SESSION['user_id'];
$date = trim($_POST['date'] ?? '');
$period_id = (int)($_POST['period_id'] ?? 0);

if ($date === '' || $period_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Date and period are required."
    ]);
    exit;
}

// ========================================
// 4. GET FACULTY ID
// ========================================

$stmt = $conn->prepare("
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
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
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Faculty record not found."
    ]);
    $stmt->close();
    exit;
}

$faculty = $result->fetch_assoc();
$faculty_id = (int)$faculty['faculty_id'];
$stmt->close();

// ========================================
// 5. CONVERT DATE TO DAY
// ========================================

$day = strtoupper(date("l", strtotime($date)));

// ========================================
// 6. CHECK TIMETABLE WITH JOINS
// ========================================

$stmt = $conn->prepare("
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.section_id,
        sec.section_name,
        t.subject_id,
        s.subject_code,
        s.subject_name,
        t.faculty_id,
        t.classroom_id,
        c.room_number,
        t.status
    FROM timetable t
    LEFT JOIN sections sec ON sec.section_id = t.section_id
    LEFT JOIN subjects s ON s.subject_id = t.subject_id
    LEFT JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.faculty_id = ?
      AND t.day = ?
      AND t.period_id = ?
      AND t.status = 'PUBLISHED'
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
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
// 7. CLASS FOUND OR NOT
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