<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GET INPUT
|--------------------------------------------------------------------------
*/

$day = strtoupper(
    trim($_GET["day"] ?? "")
);

$period_id = (int)(
    $_GET["period_id"] ?? 0
);

$section_id = (int)(
    $_GET["section_id"] ?? 0
);

$faculty_id = (int)(
    $_GET["faculty_id"] ?? 0
);

$classroom_id = (int)(
    $_GET["classroom_id"] ?? 0
);

$exclude_id = (int)(
    $_GET["exclude_id"] ?? 0
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

$valid_days = [
    "MONDAY",
    "TUESDAY",
    "WEDNESDAY",
    "THURSDAY",
    "FRIDAY",
    "SATURDAY"
];


if (!in_array($day, $valid_days, true)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid day."
    ]);

    exit;
}


if ($period_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid period."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

$response = [
    "success" => true,
    "conflict" => false,
    "section_conflict" => false,
    "faculty_conflict" => false,
    "classroom_conflict" => false,
    "message" => "No conflict found."
];


/*
|--------------------------------------------------------------------------
| SECTION CONFLICT
|--------------------------------------------------------------------------
*/

if ($section_id > 0) {

    if ($exclude_id > 0) {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND section_id = ?
            AND timetable_id != ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "siii",
            $day,
            $period_id,
            $section_id,
            $exclude_id
        );

    } else {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND section_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sii",
            $day,
            $period_id,
            $section_id
        );
    }


    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows > 0) {

        $response["conflict"] = true;

        $response["section_conflict"] = true;

        $response["message"] =
            "Section already has a class at this period.";
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| FACULTY CONFLICT
|--------------------------------------------------------------------------
*/

if ($faculty_id > 0) {

    if ($exclude_id > 0) {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND faculty_id = ?
            AND timetable_id != ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "siii",
            $day,
            $period_id,
            $faculty_id,
            $exclude_id
        );

    } else {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND faculty_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sii",
            $day,
            $period_id,
            $faculty_id
        );
    }


    $stmt->execute();

    $result =
        $stmt->get_result();


    if ($result->num_rows > 0) {

        $response["conflict"] = true;

        $response["faculty_conflict"] = true;

        if (
            $response["message"] ===
            "No conflict found."
        ) {

            $response["message"] =
                "Faculty already has a class at this period.";
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| CLASSROOM / LAB CONFLICT
|--------------------------------------------------------------------------
*/

if ($classroom_id > 0) {

    if ($exclude_id > 0) {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND classroom_id = ?
            AND timetable_id != ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "siii",
            $day,
            $period_id,
            $classroom_id,
            $exclude_id
        );

    } else {

        $sql = "
            SELECT timetable_id
            FROM timetable
            WHERE day = ?
            AND period_id = ?
            AND classroom_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sii",
            $day,
            $period_id,
            $classroom_id
        );
    }


    $stmt->execute();

    $result =
        $stmt->get_result();


    if ($result->num_rows > 0) {

        $response["conflict"] = true;

        $response["classroom_conflict"] = true;

        if (
            $response["message"] ===
            "No conflict found."
        ) {

            $response["message"] =
                "Classroom / Lab is already occupied at this period.";
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode(
    $response,
    JSON_PRETTY_PRINT
);

exit;

?>