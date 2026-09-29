<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";


if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header("Location: subjects.php?error=invalid_request");
    exit;
}

$subject_id = (int)$_GET["id"];


// Check subject exists

$sql = "
    SELECT subject_name
    FROM subjects
    WHERE subject_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: subjects.php?error=not_found");
    exit;
}

$stmt->close();


// Check faculty-subject assignments

$sql = "
    SELECT COUNT(*) AS total
    FROM faculty_subjects
    WHERE subject_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$faculty_subject_count =
    (int)$row["total"];

$stmt->close();


// Check timetable usage

$sql = "
    SELECT COUNT(*) AS total
    FROM timetable
    WHERE subject_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$timetable_count =
    (int)$row["total"];

$stmt->close();


// Prevent deletion if used

if (
    $faculty_subject_count > 0 ||
    $timetable_count > 0
) {

    header(
        "Location: subjects.php?error=in_use"
    );

    exit;
}


// Delete subject

$sql = "
    DELETE FROM subjects
    WHERE subject_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $subject_id
);

if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: subjects.php?success=deleted"
    );

    exit;
}

$stmt->close();

header(
    "Location: subjects.php?error=delete_failed"
);

exit;

?>