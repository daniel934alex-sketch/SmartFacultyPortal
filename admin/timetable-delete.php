<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: timetable.php?error=invalid_request"
    );

    exit;
}

$timetable_id = (int)$_GET["id"];


/*
|--------------------------------------------------------------------------
| Get selected timetable entry
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.timetable_id,
        t.day,
        t.section_id,
        t.subject_id,
        t.faculty_id,
        t.classroom_id,
        s.subject_type,
        s.continuous_periods
    FROM timetable t
    INNER JOIN subjects s
        ON t.subject_id = s.subject_id
    WHERE t.timetable_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    header(
        "Location: timetable.php?error=delete_failed"
    );

    exit;
}

$stmt->bind_param(
    "i",
    $timetable_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: timetable.php?error=not_found"
    );

    exit;
}


$entry = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get details
|--------------------------------------------------------------------------
*/

$day = $entry["day"];

$section_id =
    (int)$entry["section_id"];

$subject_id =
    (int)$entry["subject_id"];

$faculty_id =
    (int)$entry["faculty_id"];

$classroom_id =
    (int)$entry["classroom_id"];

$subject_type =
    $entry["subject_type"];

$continuous_periods =
    (int)$entry["continuous_periods"];


/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | LAB / CONTINUOUS SUBJECT
    |--------------------------------------------------------------------------
    |
    | For example:
    |
    | Monday P7 → OS-LAB
    | Monday P8 → OS-LAB
    | Monday P9 → OS-LAB
    |
    | Delete all rows belonging to this block.
    |
    */

    if ($continuous_periods > 1) {

        $sql = "
            DELETE FROM timetable
            WHERE day = ?
            AND section_id = ?
            AND subject_id = ?
            AND faculty_id = ?
            AND classroom_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Failed to prepare block deletion."
            );
        }

        $stmt->bind_param(
            "siiii",
            $day,
            $section_id,
            $subject_id,
            $faculty_id,
            $classroom_id
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to delete timetable block."
            );
        }

        $stmt->close();


    } else {

        /*
        |--------------------------------------------------------------------------
        | NORMAL SINGLE PERIOD SUBJECT
        |--------------------------------------------------------------------------
        */

        $sql = "
            DELETE FROM timetable
            WHERE timetable_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Failed to prepare deletion."
            );
        }

        $stmt->bind_param(
            "i",
            $timetable_id
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Failed to delete timetable entry."
            );
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    header(
        "Location: timetable.php?success=deleted"
    );

    exit;


} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    header(
        "Location: timetable.php?error=delete_failed"
    );

    exit;
}

?>