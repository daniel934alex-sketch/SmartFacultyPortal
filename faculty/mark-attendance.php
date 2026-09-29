<?php

require_once("../config/db.php");


/*
    Only POST request allowed
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: attendance.php");

    exit();

}


/*
    Get values from attendance.php
*/

$student_id = isset($_POST["student_id"])
    ? (int)$_POST["student_id"]
    : 0;

$timetable_id = isset($_POST["timetable_id"])
    ? (int)$_POST["timetable_id"]
    : 1;

$attendance_date = isset($_POST["attendance_date"])
    ? $_POST["attendance_date"]
    : date("Y-m-d");

$status = isset($_POST["status"])
    ? strtoupper(trim($_POST["status"]))
    : "";


/*
    Validate values
*/

if ($student_id <= 0) {

    header("Location: attendance.php?error=1");

    exit();

}


if ($timetable_id <= 0) {

    header("Location: attendance.php?error=1");

    exit();

}


if (!in_array($status, ["PRESENT", "ABSENT"])) {

    header("Location: attendance.php?error=1");

    exit();

}


/*
    For now faculty_id = 1

    Later we will connect this with
    logged-in faculty.
*/

$faculty_id = 1;


/*
    Check whether attendance already exists
*/

$check_sql = "
    SELECT attendance_id
    FROM attendance
    WHERE timetable_id = ?
    AND student_id = ?
    AND attendance_date = ?
    LIMIT 1
";

$check_stmt = $conn->prepare($check_sql);


if (!$check_stmt) {

    die("Check query failed: " . $conn->error);

}


$check_stmt->bind_param(
    "iis",
    $timetable_id,
    $student_id,
    $attendance_date
);


$check_stmt->execute();


$check_result = $check_stmt->get_result();


/*
    If attendance already exists
*/

if ($check_result->num_rows > 0) {

    $attendance_row = $check_result->fetch_assoc();

    $attendance_id = (int)$attendance_row["attendance_id"];


    /*
        UPDATE attendance
    */

    $update_sql = "
        UPDATE attendance
        SET
            faculty_id = ?,
            status = ?,
            marked_at = NOW()
        WHERE attendance_id = ?
    ";


    $update_stmt = $conn->prepare($update_sql);


    if (!$update_stmt) {

        die("Update query failed: " . $conn->error);

    }


    $update_stmt->bind_param(
        "isi",
        $faculty_id,
        $status,
        $attendance_id
    );


    if ($update_stmt->execute()) {

        $update_stmt->close();

        $check_stmt->close();

        $conn->close();


        header(
            "Location: attendance.php?success=1" .
            "&date=" . urlencode($attendance_date) .
            "&timetable_id=" . $timetable_id
        );

        exit();

    } else {

        $update_stmt->close();

        $check_stmt->close();

        $conn->close();


        header("Location: attendance.php?error=1");

        exit();

    }

}


/*
    Attendance does not exist
    So INSERT new record
*/

$insert_sql = "
    INSERT INTO attendance
    (
        timetable_id,
        student_id,
        faculty_id,
        attendance_date,
        status,
        marked_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        NOW()
    )
";


$insert_stmt = $conn->prepare($insert_sql);


if (!$insert_stmt) {

    die("Insert query failed: " . $conn->error);

}


$insert_stmt->bind_param(
    "iiiss",
    $timetable_id,
    $student_id,
    $faculty_id,
    $attendance_date,
    $status
);


if ($insert_stmt->execute()) {

    $insert_stmt->close();

    $check_stmt->close();

    $conn->close();


    header(
        "Location: attendance.php?success=1" .
        "&date=" . urlencode($attendance_date) .
        "&timetable_id=" . $timetable_id
    );

    exit();

} else {

    $insert_stmt->close();

    $check_stmt->close();

    $conn->close();


    header("Location: attendance.php?error=1");

    exit();

}

?>