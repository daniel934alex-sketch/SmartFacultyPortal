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
        "message" => "Please login again."
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
        "message" => "Only faculty can create handover requests."
    ]);

    exit;
}


/* =========================================================
   GET INPUT
   ========================================================= */

$date =
    trim($_POST['date'] ?? '');

$timetable_id =
    (int) ($_POST['timetable_id'] ?? 0);

$reason =
    trim($_POST['reason'] ?? '');


/* =========================================================
   VALIDATE INPUT
   ========================================================= */

if ($date === '') {

    echo json_encode([
        "success" => false,
        "message" => "Date is required."
    ]);

    exit;
}


/* Validate date format */

$date_object =
    DateTime::createFromFormat(
        'Y-m-d',
        $date
    );


if (
    !$date_object ||
    $date_object->format('Y-m-d') !== $date
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid date."
    ]);

    exit;
}


if ($timetable_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Timetable ID is required."
    ]);

    exit;
}


if ($reason === '') {

    echo json_encode([
        "success" => false,
        "message" => "Handover reason is required."
    ]);

    exit;
}


/* =========================================================
   GET CURRENT USER
   ========================================================= */

$user_id =
    (int) $_SESSION['user_id'];


/* =========================================================
   GET ORIGINAL FACULTY
   ========================================================= */

$stmt = $conn->prepare("
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
            "Faculty query failed: " .
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


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Original faculty record not found."
    ]);

    $stmt->close();

    exit;
}


$originalFaculty =
    $result->fetch_assoc();


$original_faculty_id =
    (int) $originalFaculty['faculty_id'];


$original_department_id =
    (int) $originalFaculty['department_id'];


$stmt->close();


/* =========================================================
   GET TIMETABLE CLASS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        timetable_id,
        day,
        period_id,
        section_id,
        subject_id,
        faculty_id,
        classroom_id,
        status
    FROM timetable
    WHERE timetable_id = ?
      AND faculty_id = ?
      AND status = 'PUBLISHED'
    LIMIT 1
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Timetable query failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $timetable_id,
    $original_faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Valid timetable class was not found."
    ]);

    $stmt->close();

    exit;
}


$class =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   GET CLASS INFORMATION
   ========================================================= */

$class_day =
    strtoupper(trim($class['day']));


$period_id =
    (int) $class['period_id'];


/* =========================================================
   CHECK SELECTED DATE DAY
   ========================================================= */

$selected_day =
    strtoupper(
        date(
            "l",
            strtotime($date)
        )
    );


if (
    $selected_day !== $class_day
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "The selected date does not match the class timetable day."
    ]);

    exit;
}


/* =========================================================
   CHECK EXISTING HANDOVER
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        handover_id
    FROM class_handover
    WHERE timetable_id = ?
      AND original_faculty_id = ?
      AND status IN ('PENDING', 'ASSIGNED')
    LIMIT 1
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Existing handover check failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $timetable_id,
    $original_faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows > 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "A handover already exists for this class."
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/* =========================================================
   FIND ALL ELIGIBLE FACULTIES
   =========================================================

   Conditions:

   1. Faculty must be ACTIVE
   2. Faculty must be from SAME DEPARTMENT
   3. Faculty must not be original faculty
   4. Faculty must be AVAILABLE for selected date/period
      OR have no availability record
   5. Faculty must not have a published class
      in the same period
   ========================================================= */


$stmt = $conn->prepare("
    SELECT
        f.faculty_id,
        f.faculty_name,
        f.email,
        f.user_id,
        f.department_id

    FROM faculty f

    INNER JOIN users u
        ON u.user_id = f.user_id

    LEFT JOIN faculty_availability fa
        ON fa.faculty_id = f.faculty_id
        AND fa.availability_date = ?
        AND fa.period_id = ?

    LEFT JOIN timetable tt
        ON tt.faculty_id = f.faculty_id
        AND tt.day = ?
        AND tt.period_id = ?
        AND tt.status = 'PUBLISHED'

    WHERE u.status = 'ACTIVE'

      /* SAME DEPARTMENT */
      AND f.department_id = ?

      /* DO NOT INCLUDE ORIGINAL FACULTY */
      AND f.faculty_id != ?

      /* AVAILABLE OR NO AVAILABILITY RECORD */
      AND (
          fa.availability_id IS NULL
          OR fa.status = 'AVAILABLE'
      )

      /* MUST NOT ALREADY HAVE A CLASS */
      AND tt.timetable_id IS NULL

    ORDER BY f.faculty_name ASC
");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Eligible faculty query failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "sisiii",
    $date,
    $period_id,
    $class_day,
    $period_id,
    $original_department_id,
    $original_faculty_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$eligible_faculties = [];


while (
    $row = $result->fetch_assoc()
) {

    $eligible_faculties[] =
        $row;
}


$stmt->close();


/* =========================================================
   NO FACULTY AVAILABLE
   ========================================================= */

if (
    count($eligible_faculties) === 0
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "No available faculty members were found in the same department for this period."
    ]);

    exit;
}


/* =========================================================
   START TRANSACTION
   ========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       CREATE CLASS HANDOVER
       ===================================================== */

    $stmt =
        $conn->prepare("
            INSERT INTO class_handover
            (
                timetable_id,
                original_faculty_id,
                reason,
                status
            )
            VALUES
            (?, ?, ?, 'PENDING')
        ");


    if (!$stmt) {

        throw new Exception(
            "Handover creation failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "iis",
        $timetable_id,
        $original_faculty_id,
        $reason
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to create handover: " .
            $stmt->error
        );
    }


    $handover_id =
        $stmt->insert_id;


    $stmt->close();


    /* =====================================================
       PREPARE REQUEST INSERT
       ===================================================== */

    $request_stmt =
        $conn->prepare("
            INSERT INTO handover_requests
            (
                handover_id,
                alternate_faculty_id,
                status
            )
            VALUES
            (?, ?, 'PENDING')
        ");


    if (!$request_stmt) {

        throw new Exception(
            "Request preparation failed: " .
            $conn->error
        );
    }


    /* =====================================================
       PREPARE NOTIFICATION INSERT
       ===================================================== */

    $notification_stmt =
        $conn->prepare("
            INSERT INTO notifications
            (
                user_id,
                title,
                message,
                notification_type,
                is_read
            )
            VALUES
            (?, ?, ?, ?, 0)
        ");


    if (!$notification_stmt) {

        throw new Exception(
            "Notification preparation failed: " .
            $conn->error
        );
    }


    /* =====================================================
       SEND REQUEST TO ALL ELIGIBLE FACULTIES
       ===================================================== */

    $request_count =
        0;


    $faculty_names =
        [];


    foreach (
        $eligible_faculties as $faculty
    ) {


        $alternate_faculty_id =
            (int) $faculty['faculty_id'];


        $alternate_user_id =
            (int) $faculty['user_id'];


        $faculty_name =
            $faculty['faculty_name'];


        /* ---------------------------------------------
           CREATE HANDOVER REQUEST
           --------------------------------------------- */

        $request_stmt->bind_param(
            "ii",
            $handover_id,
            $alternate_faculty_id
        );


        if (!$request_stmt->execute()) {

            throw new Exception(
                "Unable to create request for " .
                $faculty_name .
                ": " .
                $request_stmt->error
            );
        }


        $request_count++;


        $faculty_names[] =
            $faculty_name;


        /* ---------------------------------------------
           CREATE NOTIFICATION
           --------------------------------------------- */

        $title =
            "New Handover Request";


        $message =
            $originalFaculty['faculty_name'] .
            " has sent you a class handover request. " .
            "You can accept or reject it.";


        $notification_type =
            "HANDOVER";


        $notification_stmt->bind_param(
            "isss",
            $alternate_user_id,
            $title,
            $message,
            $notification_type
        );


        if (
            !$notification_stmt->execute()
        ) {

            throw new Exception(
                "Notification creation failed for " .
                $faculty_name .
                ": " .
                $notification_stmt->error
            );
        }

    }


    $request_stmt->close();

    $notification_stmt->close();


    /* =====================================================
       COMMIT
       ===================================================== */

    $conn->commit();


    /* =====================================================
       SUCCESS RESPONSE
       ===================================================== */

    echo json_encode([

        "success" => true,

        "message" =>
            "Handover request sent to all available faculty members in the same department.",

        "handover_id" =>
            $handover_id,

        "request_count" =>
            $request_count,

        "faculty_names" =>
            $faculty_names

    ]);


} catch (
    Exception $e
) {


    /* =====================================================
       ROLLBACK
       ===================================================== */

    $conn->rollback();


    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);

}


$conn->close();

?>