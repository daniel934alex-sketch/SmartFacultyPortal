<?php

session_start();

header("Content-Type: application/json");

require_once "../config/database.php";


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
    $_SESSION['role'] !== 'FACULTY'
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Only faculty can create handover requests."
    ]);

    exit;
}


/* =========================================================
   GET INPUT
   ========================================================= */

$timetable_id =
    (int) ($_POST['timetable_id'] ?? 0);

$alternate_faculty_id =
    (int) ($_POST['alternate_faculty_id'] ?? 0);

$reason =
    trim($_POST['reason'] ?? '');


/* =========================================================
   VALIDATION
   ========================================================= */

if (
    $timetable_id <= 0 ||
    $alternate_faculty_id <= 0
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Timetable and alternate faculty are required."
    ]);

    exit;
}


if ($reason === '') {

    echo json_encode([
        "success" => false,
        "message" =>
            "Handover reason is required."
    ]);

    exit;
}


/* =========================================================
   CURRENT USER
   ========================================================= */

$user_id =
    (int) $_SESSION['user_id'];


/* =========================================================
   GET ORIGINAL FACULTY
   ========================================================= */

$stmt =
    $conn->prepare("
        SELECT
            faculty_id,
            faculty_name
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


if (
    $result->num_rows === 0
) {

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


$stmt->close();


/* =========================================================
   CHECK TIMETABLE
   ========================================================= */

$stmt =
    $conn->prepare("
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


if (
    $result->num_rows === 0
) {

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
   CHECK SELF SELECTION
   ========================================================= */

if (
    $alternate_faculty_id ===
    $original_faculty_id
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "You cannot select yourself."
    ]);

    exit;
}


/* =========================================================
   CHECK ALTERNATE FACULTY
   ========================================================= */

$stmt =
    $conn->prepare("
        SELECT
            f.faculty_id,
            f.faculty_name,
            f.email
        FROM faculty f
        INNER JOIN users u
            ON u.user_id = f.user_id
        WHERE f.faculty_id = ?
          AND u.status = 'ACTIVE'
        LIMIT 1
    ");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Alternate faculty query failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $alternate_faculty_id
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
            "Selected alternate faculty is not active."
    ]);

    $stmt->close();

    exit;
}


$alternateFaculty =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   CHECK EXISTING HANDOVER
   ========================================================= */

$stmt =
    $conn->prepare("
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


if (
    $result->num_rows > 0
) {

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


    if (
        !$stmt->execute()
    ) {

        throw new Exception(
            "Unable to create handover: " .
            $stmt->error
        );
    }


    $handover_id =
        $stmt->insert_id;


    $stmt->close();


    /* =====================================================
       CREATE HANDOVER REQUEST
       ===================================================== */

    $stmt =
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


    if (!$stmt) {

        throw new Exception(
            "Request creation failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "ii",
        $handover_id,
        $alternate_faculty_id
    );


    if (
        !$stmt->execute()
    ) {

        throw new Exception(
            "Unable to create handover request: " .
            $stmt->error
        );
    }


    $request_id =
        $stmt->insert_id;


    $stmt->close();


    /* =====================================================
       GET ALTERNATE USER ID
       ===================================================== */

    $stmt =
        $conn->prepare("
            SELECT
                user_id
            FROM faculty
            WHERE faculty_id = ?
            LIMIT 1
        ");


    if (!$stmt) {

        throw new Exception(
            "Alternate user query failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "i",
        $alternate_faculty_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $result->num_rows === 0
    ) {

        throw new Exception(
            "Alternate faculty user was not found."
        );
    }


    $alternateUser =
        $result->fetch_assoc();


    $alternate_user_id =
        (int) $alternateUser['user_id'];


    $stmt->close();


    /* =====================================================
       CREATE NOTIFICATION
       ===================================================== */

    $title =
        "New Handover Request";


    $message =
        $originalFaculty['faculty_name'] .
        " has sent you a class handover request.";


    $notification_type =
        "HANDOVER";


    $stmt =
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


    if (!$stmt) {

        throw new Exception(
            "Notification preparation failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "isss",
        $alternate_user_id,
        $title,
        $message,
        $notification_type
    );


    if (
        !$stmt->execute()
    ) {

        throw new Exception(
            "Notification creation failed: " .
            $stmt->error
        );
    }


    $stmt->close();


    /* =====================================================
       COMMIT
       ===================================================== */

    $conn->commit();


    echo json_encode([

        "success" => true,

        "message" =>
            "Handover request sent successfully.",

        "handover_id" =>
            $handover_id,

        "request_id" =>
            $request_id,

        "alternate_faculty" =>
            $alternateFaculty['faculty_name']

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