<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";


/*
 * Validate ID
 */

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: classrooms.php?error=invalid_request"
    );

    exit;
}

$classroom_id = (int)$_GET["id"];


/*
 * Check resource exists
 */

$sql = "
    SELECT classroom_id
    FROM classrooms
    WHERE classroom_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $classroom_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: classrooms.php?error=not_found"
    );

    exit;
}

$stmt->close();


/*
 * Check timetable usage
 */

$sql = "
    SELECT COUNT(*) AS total
    FROM timetable
    WHERE classroom_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $classroom_id
);

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$timetable_count =
    (int)$row["total"];

$stmt->close();


/*
 * Do not delete if used
 */

if ($timetable_count > 0) {

    header(
        "Location: classrooms.php?error=in_use"
    );

    exit;
}


/*
 * Delete resource
 */

$sql = "
    DELETE FROM classrooms
    WHERE classroom_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $classroom_id
);


if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: classrooms.php?success=deleted"
    );

    exit;
}


$stmt->close();

header(
    "Location: classrooms.php?error=delete_failed"
);

exit;

?>