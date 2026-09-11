<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";


// ---------------------------------------------------------
// Validate ID
// ---------------------------------------------------------

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: faculty.php");
    exit;
}


$faculty_id = (int) $_GET['id'];


// ---------------------------------------------------------
// Validate Status
// ---------------------------------------------------------

$status = $_GET['status'] ?? '';

if (!in_array($status, ['ACTIVE', 'INACTIVE'])) {

    header("Location: faculty.php");
    exit;
}


// ---------------------------------------------------------
// Get User ID
// ---------------------------------------------------------

$stmt = $conn->prepare("
    SELECT user_id
    FROM faculty
    WHERE faculty_id = ?
");

$stmt->bind_param(
    "i",
    $faculty_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    header("Location: faculty.php");
    exit;
}


$row = $result->fetch_assoc();

$user_id = $row['user_id'];


// ---------------------------------------------------------
// Update Status
// ---------------------------------------------------------

$stmt = $conn->prepare("
    UPDATE users
    SET status = ?
    WHERE user_id = ?
");

$stmt->bind_param(
    "si",
    $status,
    $user_id
);

$stmt->execute();


header("Location: faculty.php");
exit;

?>