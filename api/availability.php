<?php

session_start();

header("Content-Type: application/json");

require_once "../config/database.php";


/* =========================================================
   CHECK LOGIN
   ========================================================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" => "User session not found. Please login again."
    ]);

    exit;
}


/* =========================================================
   CHECK ROLE
   ========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'FACULTY'
) {

    echo json_encode([
        "success" => false,
        "message" => "Only faculty can update availability."
    ]);

    exit;
}


/* =========================================================
   GET USER ID
   ========================================================= */

$user_id =
    (int) $_SESSION['user_id'];


/* =========================================================
   GET FORM DATA
   ========================================================= */

$date =
    trim($_POST['date'] ?? '');

$period_id =
    trim($_POST['period_id'] ?? '');

$status =
    trim($_POST['status'] ?? '');

$reason =
    trim($_POST['reason'] ?? '');


/* =========================================================
   VALIDATE INPUT
   ========================================================= */

if (
    $date === '' ||
    $period_id === '' ||
    $status === ''
) {

    echo json_encode([
        "success" => false,
        "message" => "Date, period and status are required."
    ]);

    exit;
}


/* =========================================================
   VALIDATE STATUS
   ========================================================= */

$allowed_status = [
    "AVAILABLE",
    "UNAVAILABLE",
    "LEAVE"
];


if (
    !in_array(
        $status,
        $allowed_status,
        true
    )
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid availability status."
    ]);

    exit;
}


/* =========================================================
   REASON REQUIRED FOR UNAVAILABLE
   ========================================================= */

if (
    $status === "UNAVAILABLE" &&
    $reason === ''
) {

    echo json_encode([
        "success" => false,
        "message" => "Please provide a reason."
    ]);

    exit;
}


/* =========================================================
   FIND FACULTY ID
   ========================================================= */

$stmt = $conn->prepare("
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Faculty query preparation failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if (
    $result->num_rows === 0
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Faculty record not found for this login."
    ]);

    $stmt->close();

    exit;
}


$faculty =
    $result->fetch_assoc();


$faculty_id =
    (int) $faculty['faculty_id'];


$stmt->close();


/* =========================================================
   SAVE AVAILABILITY
   ========================================================= */

$stmt = $conn->prepare("
    INSERT INTO faculty_availability
    (
        faculty_id,
        availability_date,
        period_id,
        status,
        reason
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        reason = VALUES(reason),
        updated_at = CURRENT_TIMESTAMP
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Save query preparation failed: " .
            $conn->error
    ]);

    exit;
}


/*
 * i = faculty_id
 * s = date
 * i = period_id
 * s = status
 * s = reason
 */

$stmt->bind_param(
    "isiss",
    $faculty_id,
    $date,
    $period_id,
    $status,
    $reason
);


/* =========================================================
   EXECUTE
   ========================================================= */

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" =>
            "Period " .
            $period_id .
            " - " .
            $status .
            " saved successfully."
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Database save failed: " .
            $stmt->error
    ]);
}


$stmt->close();

$conn->close();

?>