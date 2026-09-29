<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";


/* ==========================================================
   CHECK PARAMETERS
   ========================================================== */

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"]) ||
    !isset($_GET["status"])
) {

    header("Location: students.php?error=invalid_request");
    exit;
}


$student_id = (int)$_GET["id"];

$status = strtoupper(
    trim($_GET["status"])
);


/* ==========================================================
   VALID STATUS
   ========================================================== */

if (
    $status !== "ACTIVE" &&
    $status !== "INACTIVE"
) {

    header("Location: students.php?error=invalid_status");
    exit;
}


/* ==========================================================
   GET USER ID
   ========================================================== */

$sql = "
    SELECT user_id
    FROM students
    WHERE student_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    header("Location: students.php?error=update_failed");
    exit;
}

$stmt->bind_param(
    "i",
    $student_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: students.php?error=student_not_found"
    );

    exit;
}

$row = $result->fetch_assoc();

$user_id = (int)$row["user_id"];

$stmt->close();


/* ==========================================================
   UPDATE USER STATUS
   ========================================================== */

$sql = "
    UPDATE users
    SET status = ?
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    header(
        "Location: students.php?error=update_failed"
    );

    exit;
}

$stmt->bind_param(
    "si",
    $status,
    $user_id
);


if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: students.php?success=status"
    );

    exit;

}


$stmt->close();

header(
    "Location: students.php?error=update_failed"
);

exit;

?>