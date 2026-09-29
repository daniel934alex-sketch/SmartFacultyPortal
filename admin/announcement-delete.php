<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";


if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: announcements.php?error=invalid_request"
    );

    exit;
}


$announcement_id = (int)$_GET["id"];


/*
|--------------------------------------------------------------------------
| Check Announcement
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT announcement_id
    FROM announcements
    WHERE announcement_id = ?
";

$stmt = $conn->prepare($sql);


if (!$stmt) {

    header(
        "Location: announcements.php?error=delete_failed"
    );

    exit;
}


$stmt->bind_param(
    "i",
    $announcement_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: announcements.php?error=not_found"
    );

    exit;
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Delete Announcement
|--------------------------------------------------------------------------
*/

$sql = "
    DELETE FROM announcements
    WHERE announcement_id = ?
";

$stmt = $conn->prepare($sql);


if (!$stmt) {

    header(
        "Location: announcements.php?error=delete_failed"
    );

    exit;
}


$stmt->bind_param(
    "i",
    $announcement_id
);


if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: announcements.php?success=deleted"
    );

    exit;
}


$stmt->close();


header(
    "Location: announcements.php?error=delete_failed"
);

exit;

?>