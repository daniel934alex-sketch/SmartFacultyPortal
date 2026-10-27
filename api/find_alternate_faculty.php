<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/database.php";


/* =========================================================
   CHECK LOGIN
   ========================================================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" =>
            "User session not found. Please login again."
    ]);

    exit;
}


/* =========================================================
   CHECK ROLE
   ========================================================= */

if (
    !isset($_SESSION['role']) ||
    strtoupper($_SESSION['role']) !== 'FACULTY'
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Only faculty can find alternate faculty."
    ]);

    exit;
}


/* =========================================================
   CURRENT USER ID
   ========================================================= */

$user_id =
    (int) $_SESSION['user_id'];


/* =========================================================
   INPUT
   ========================================================= */

$date =
    trim($_POST['date'] ?? '');

$period_id =
    (int) ($_POST['period_id'] ?? 0);


/* =========================================================
   VALIDATION
   ========================================================= */

if (
    $date === '' ||
    $period_id <= 0
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Date and period are required."
    ]);

    exit;
}


/* =========================================================
   VALIDATE DATE
   ========================================================= */

$dateObject =
    DateTime::createFromFormat(
        'Y-m-d',
        $date
    );


if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $date
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid date format."
    ]);

    exit;
}


/* =========================================================
   FIND CURRENT FACULTY
   ========================================================= */

$stmt =
    $conn->prepare("
        SELECT
            faculty_id,
            faculty_name,
            department_id
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
            "Faculty record not found."
    ]);

    $stmt->close();

    exit;
}


$currentFaculty =
    $result->fetch_assoc();


$original_faculty_id =
    (int) $currentFaculty['faculty_id'];

$department_id =
    (int) $currentFaculty['department_id'];


$stmt->close();


/* =========================================================
   FIND DAY
   ========================================================= */

$day =
    strtoupper(
        date(
            "l",
            strtotime($date)
        )
    );


/* =========================================================
   FIND ELIGIBLE FACULTY
   =========================================================

   Rules:

   1. Faculty must be active.
   2. Faculty must belong to SAME department.
   3. Faculty must not be the original faculty.
   4. Faculty must not be UNAVAILABLE.
   5. Faculty must not be on LEAVE.
   6. Faculty must not already have a class
      in the selected period.
   ========================================================= */


$sql = "

    SELECT

        f.faculty_id,

        f.faculty_code,

        f.faculty_name,

        f.email,

        f.department_id,

        f.designation

    FROM faculty f

    INNER JOIN users u

        ON u.user_id = f.user_id


    LEFT JOIN faculty_availability fa

        ON fa.faculty_id = f.faculty_id

        AND fa.availability_date = ?

        AND fa.period_id = ?


    LEFT JOIN timetable t

        ON t.faculty_id = f.faculty_id

        AND t.day = ?

        AND t.period_id = ?

        AND t.status = 'PUBLISHED'


    WHERE

        u.status = 'ACTIVE'


        /* SAME DEPARTMENT */

        AND f.department_id = ?


        /* DO NOT INCLUDE ORIGINAL FACULTY */

        AND f.faculty_id != ?


        /* FACULTY MUST BE AVAILABLE */

        AND (

            fa.availability_id IS NULL

            OR fa.status = 'AVAILABLE'

        )


        /* FACULTY MUST NOT HAVE A CLASS */

        AND t.timetable_id IS NULL


    ORDER BY

        f.faculty_name ASC

";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Alternate faculty query preparation failed: " .
            $conn->error
    ]);

    exit;
}


/* =========================================================
   BIND PARAMETERS
   =========================================================

   date             = s
   period_id        = i
   day              = s
   period_id        = i
   department_id    = i
   faculty_id       = i

   Therefore:

   s i s i i i
   ========================================================= */

$stmt->bind_param(
    "sisiii",
    $date,
    $period_id,
    $day,
    $period_id,
    $department_id,
    $original_faculty_id
);


/* =========================================================
   EXECUTE
   ========================================================= */

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Database query failed: " .
            $stmt->error
    ]);

    $stmt->close();

    $conn->close();

    exit;
}


/* =========================================================
   GET RESULT
   ========================================================= */

$result =
    $stmt->get_result();


$faculty_list = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $faculty_list[] =
        $row;
}


/* =========================================================
   RESPONSE
   ========================================================= */

if (
    count($faculty_list) === 0
) {

    echo json_encode([

        "success" => true,

        "count" => 0,

        "message" =>
            "No eligible faculty found in the same department.",

        "date" =>
            $date,

        "day" =>
            $day,

        "period_id" =>
            $period_id,

        "department_id" =>
            $department_id,

        "faculty" =>
            []

    ]);

} else {

    echo json_encode([

        "success" => true,

        "count" =>
            count($faculty_list),

        "message" =>
            "Eligible faculty from the same department found.",

        "date" =>
            $date,

        "day" =>
            $day,

        "period_id" =>
            $period_id,

        "department_id" =>
            $department_id,

        "faculty" =>
            $faculty_list

    ]);

}


/* =========================================================
   CLOSE
   ========================================================= */

$stmt->close();

$conn->close();

?>