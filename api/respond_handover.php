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
   CHECK FACULTY ROLE
   ========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'FACULTY'
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Only faculty can respond to handover requests."
    ]);

    exit;
}


/* =========================================================
   INPUT
   ========================================================= */

$request_id =
    (int) ($_POST['request_id'] ?? 0);

$response_status =
    trim($_POST['status'] ?? '');


/* =========================================================
   VALIDATION
   ========================================================= */

if ($request_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid request ID."
    ]);

    exit;
}


if (
    !in_array(
        $response_status,
        ['ACCEPTED', 'REJECTED'],
        true
    )
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid response status."
    ]);

    exit;
}


/* =========================================================
   GET CURRENT FACULTY
   ========================================================= */

$user_id =
    (int) $_SESSION['user_id'];


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
            "Faculty record not found."
    ]);

    $stmt->close();

    exit;
}


$currentFaculty =
    $result->fetch_assoc();


$current_faculty_id =
    (int) $currentFaculty['faculty_id'];


$stmt->close();


/* =========================================================
   GET REQUEST
   ========================================================= */

$stmt =
    $conn->prepare("
        SELECT

            hr.request_id,

            hr.handover_id,

            hr.alternate_faculty_id,

            hr.status AS request_status,

            ch.timetable_id,

            ch.original_faculty_id,

            ch.status AS handover_status,

            ch.reason

        FROM handover_requests hr

        INNER JOIN class_handover ch
            ON ch.handover_id =
               hr.handover_id

        WHERE hr.request_id = ?

          AND hr.alternate_faculty_id = ?

        LIMIT 1
    ");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Request query failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $request_id,
    $current_faculty_id
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
            "Handover request not found."
    ]);

    $stmt->close();

    exit;
}


$request =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   CHECK REQUEST STATUS
   ========================================================= */

if (
    $request['request_status'] !== 'PENDING'
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "This request has already been processed."
    ]);

    exit;
}


/* =========================================================
   CHECK HANDOVER STATUS
   ========================================================= */

if (
    !in_array(
        $request['handover_status'],
        ['PENDING'],
        true
    )
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "This handover is no longer pending."
    ]);

    exit;
}


/* =========================================================
   GET ORIGINAL FACULTY USER
   ========================================================= */

$stmt =
    $conn->prepare("
        SELECT
            user_id,
            faculty_name
        FROM faculty
        WHERE faculty_id = ?
        LIMIT 1
    ");


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Original faculty query failed: " .
            $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $request['original_faculty_id']
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
            "Original faculty was not found."
    ]);

    $stmt->close();

    exit;
}


$originalFaculty =
    $result->fetch_assoc();


$original_user_id =
    (int) $originalFaculty['user_id'];


$stmt->close();


/* =========================================================
   START TRANSACTION
   ========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       UPDATE REQUEST
       ===================================================== */

    $stmt =
        $conn->prepare("
            UPDATE handover_requests

            SET
                status = ?,
                responded_at = CURRENT_TIMESTAMP

            WHERE request_id = ?
              AND alternate_faculty_id = ?
              AND status = 'PENDING'
        ");


    if (!$stmt) {

        throw new Exception(
            "Request update failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "sii",
        $response_status,
        $request_id,
        $current_faculty_id
    );


    if (
        !$stmt->execute()
    ) {

        throw new Exception(
            "Unable to update request: " .
            $stmt->error
        );
    }


    if (
        $stmt->affected_rows !== 1
    ) {

        throw new Exception(
            "Request was already processed."
        );
    }


    $stmt->close();


    /* =====================================================
       ACCEPT
       ===================================================== */

    if (
        $response_status === 'ACCEPTED'
    ) {


        $handover_status =
            'ASSIGNED';


        $stmt =
            $conn->prepare("
                UPDATE class_handover

                SET
                    status = 'ASSIGNED'

                WHERE handover_id = ?

                  AND status = 'PENDING'
            ");


        if (!$stmt) {

            throw new Exception(
                "Handover update failed: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "i",
            $request['handover_id']
        );


        if (
            !$stmt->execute()
        ) {

            throw new Exception(
                "Unable to assign handover: " .
                $stmt->error
            );
        }


        $stmt->close();


        /* =============================================
           NOTIFICATION TO ORIGINAL FACULTY
           ============================================= */

        $title =
            "Handover Request Accepted";


        $message =
            $currentFaculty['faculty_name'] .
            " has accepted your handover request.";


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
                VALUES (?, ?, ?, ?, 0)
            ");


        if (!$stmt) {

            throw new Exception(
                "Notification preparation failed: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "isss",
            $original_user_id,
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


        $conn->commit();


        echo json_encode([

            "success" => true,

            "message" =>
                "Handover request accepted successfully.",

            "status" =>
                "ACCEPTED"

        ]);


    }


    /* =====================================================
       REJECT
       ===================================================== */

    else {


        /*
         * Keep class_handover as PENDING.
         *
         * This allows the next eligible
         * faculty to be selected later.
         */


        $title =
            "Handover Request Rejected";


        $message =
            $currentFaculty['faculty_name'] .
            " has rejected your handover request.";


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
                VALUES (?, ?, ?, ?, 0)
            ");


        if (!$stmt) {

            throw new Exception(
                "Notification preparation failed: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "isss",
            $original_user_id,
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


        $conn->commit();


        echo json_encode([

            "success" => true,

            "message" =>
                "Handover request rejected.",

            "status" =>
                "REJECTED"

        ]);

    }


} catch (
    Exception $e
) {


    $conn->rollback();


    echo json_encode([

        "success" => false,

        "message" =>
            $e->getMessage()

    ]);

}


$conn->close();

?>