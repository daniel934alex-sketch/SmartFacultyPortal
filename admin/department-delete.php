<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: departments.php?error=invalid_request");
    exit;
}

$department_id = (int)$_GET["id"];


// Check department exists
$sql = "
    SELECT department_name
    FROM departments
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: departments.php?error=not_found");
    exit;
}

$department = $result->fetch_assoc();

$stmt->close();


// Check faculty usage
$sql = "
    SELECT COUNT(*) AS total
    FROM faculty
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$faculty_count = (int)$row["total"];

$stmt->close();


// Check subjects usage
$sql = "
    SELECT COUNT(*) AS total
    FROM subjects
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$subject_count = (int)$row["total"];

$stmt->close();


// Check academic classes usage
$sql = "
    SELECT COUNT(*) AS total
    FROM academic_classes
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$class_count = (int)$row["total"];

$stmt->close();


// Do not delete if used
if (
    $faculty_count > 0 ||
    $subject_count > 0 ||
    $class_count > 0
) {

    header(
        "Location: departments.php?error=in_use"
    );

    exit;
}


// Delete department
$sql = "
    DELETE FROM departments
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);

if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: departments.php?success=deleted"
    );

    exit;
}

$stmt->close();

header(
    "Location: departments.php?error=delete_failed"
);

exit;

?>