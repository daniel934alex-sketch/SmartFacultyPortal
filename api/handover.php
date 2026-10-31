<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";

header("Content-Type: application/json");


// Check faculty login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FACULTY') {

    echo json_encode([
        "success" => false,
        "message" => "Faculty login required."
    ]);

    exit;
}


// Get logged-in user
$user_id = $_SESSION['user_id'];


// Get selected data
$date = $_POST['date'] ?? '';
$period_id = $_POST['period_id'] ?? '';


// Validate
if ($date === '' || $period_id === '') {

    echo json_encode([
        "success" => false,
        "message" => "Date and period are required."
    ]);

    exit;
}


// Find faculty ID
$stmt = $conn->prepare("
    SELECT faculty_id, faculty_name
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    echo json_encode([
        "success" => false,
        "message" => "Faculty record not found."
    ]);

    exit;
}


$faculty = $result->fetch_assoc();

$faculty_id = $faculty['faculty_id'];

$stmt->close();


// Convert date to weekday
$day = strtoupper(date('l', strtotime($date)));


// Find faculty timetable
$stmt = $conn->prepare("
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.section_id,
        t.subject_id,
        t.faculty_id,
        t.classroom_id,
        t.status
    FROM timetable t
    WHERE t.faculty_id = ?
      AND t.day = ?
      AND t.period_id = ?
      AND t.status = 'PUBLISHED'
    LIMIT 1
");


$stmt->bind_param(
    "isi",
    $faculty_id,
    $day,
    $period_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "No class found for this faculty in the selected period."
    ]);

    exit;
}


$class = $result->fetch_assoc();

$stmt->close();


// Return class information
echo json_encode([
    "success" => true,
    "message" => "Class found successfully.",
    "faculty_id" => $faculty_id,
    "faculty_name" => $faculty['faculty_name'],
    "date" => $date,
    "day" => $day,
    "class" => $class
]);


$conn->close();

?>