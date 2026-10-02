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
    strtoupper($_SESSION['role']) !== 'FACULTY'
) {

    echo json_encode([
        "success" => false,
        "message" => "Only faculty can respond to handover requests."
    ]);

    exit;
}


/* =========================================================
   INPUT
   ========================================================= */

$request_id =
    (int) ($_POST['request_id'] ?? 0);

$response_status =
    strtoupper(trim($_POST['status'] ?? ''));


/* =========================================================
   VALIDATION
   ========================================================= */

if ($request_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request ID."
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
        "message" => "Invalid response status."
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
        "message" => "Faculty query failed: " . $conn->error
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
        "message" => "Faculty record not found."
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
   START TRANSACTION
   ========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       GET REQUEST + LOCK HANDOVER
       ===================================================== */

    /*
     * FOR UPDATE is important.
     *
     * If two faculty members click ACCEPT
     * at almost the same time, only one transaction
     * can process the handover first.
     */

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
                ON ch.handover_id = hr.handover_id

            WHERE hr.request_id = ?

              AND hr.alternate_faculty_id = ?

            LIMIT 1

            FOR UPDATE
        ");


    if (!$stmt) {

        throw new Exception(
            "Request query failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "ii",
        $request_id,
        $current_faculty_id
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to read handover request: " .
            $stmt->error
        );
    }


    $result =
        $stmt->get_result();


    if (
        $result->num_rows === 0
    ) {

        throw new Exception(
            "Handover request not found."
        );
    }


    $request =
        $result->fetch_assoc();


    $stmt->close();


    /* =====================================================
       CHECK REQUEST STATUS
       ===================================================== */

    if (
        $request['request_status'] !== 'PENDING'
    ) {

        throw new Exception(
            "This request has already been processed."
        );
    }


    /* =====================================================
       CHECK HANDOVER STATUS
       ===================================================== */

    if (
        $request['handover_status'] !== 'PENDING'
    ) {

        throw new Exception(
            "This handover is no longer pending."
        );
    }


    /* =====================================================
       GET ORIGINAL FACULTY
       ===================================================== */

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

        throw new Exception(
            "Original faculty query failed: " .
            $conn->error
        );
    }


    $stmt->bind_param(
        "i",
        $request['original_faculty_id']
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to get original faculty: " .
            $stmt->error
        );
    }


    $result =
        $stmt->get_result();


    if (
        $result->num_rows === 0
    ) {

        throw new Exception(
            "Original faculty was not found."
        );
    }


    $originalFaculty =
        $result->fetch_assoc();


    $original_user_id =
        (int) $originalFaculty['user_id'];


    $stmt->close();


    /* =====================================================
       REJECT
       ===================================================== */

    if (
        $response_status === 'REJECTED'
    ) {


        /* =============================================
           UPDATE ONLY THIS REQUEST
           ============================================= */

        $stmt =
            $conn->prepare("
                UPDATE handover_requests

                SET
                    status = 'REJECTED',
                    responded_at = CURRENT_TIMESTAMP

                WHERE request_id = ?

                  AND alternate_faculty_id = ?

                  AND status = 'PENDING'
            ");


        if (!$stmt) {

            throw new Exception(
                "Reject update failed: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "ii",
            $request_id,
            $current_faculty_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to reject request: " .
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


        /* =============================================
           NOTIFY ORIGINAL FACULTY
           ============================================= */

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


        if (!$stmt->execute()) {

            throw new Exception(
                "Notification creation failed: " .
                $stmt->error
            );
        }


        $stmt->close();


        /*
         * IMPORTANT:
         *
         * Other faculty requests remain PENDING.
         */

        $conn->commit();


        echo json_encode([

            "success" => true,

            "message" =>
                "Handover request rejected. Other faculty requests are still pending.",

            "status" =>
                "REJECTED"

        ]);


        exit;
    }


    /* =====================================================
       ACCEPT
       ===================================================== */

    if (
        $response_status === 'ACCEPTED'
    ) {


        /* =============================================
           ACCEPT CURRENT REQUEST
           ============================================= */

        $stmt =
            $conn->prepare("
                UPDATE handover_requests

                SET
                    status = 'ACCEPTED',
                    responded_at = CURRENT_TIMESTAMP

                WHERE request_id = ?

                  AND alternate_faculty_id = ?

                  AND status = 'PENDING'
            ");


        if (!$stmt) {

            throw new Exception(
                "Accept update failed: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "ii",
            $request_id,
            $current_faculty_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to accept request: " .
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


        /* =============================================
           ASSIGN HANDOVER
           ============================================= */

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


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to assign handover: " .
                $stmt->error
            );
        }


        if (
            $stmt->affected_rows !== 1
        ) {

            throw new Exception(
                "Handover was already assigned."
            );
        }


        $stmt->close();


        /* =============================================
           EXPIRE ALL OTHER PENDING REQUESTS
           ============================================= */

        $stmt =
            $conn->prepare("
                UPDATE handover_requests

                SET
                    status = 'EXPIRED',
                    responded_at = CURRENT_TIMESTAMP

                WHERE handover_id = ?

                  AND request_id != ?

                  AND status = 'PENDING'
            ");


        if (!$stmt) {

            throw new Exception(
                "Unable to prepare expiry update: " .
                $conn->error
            );
        }


        $stmt->bind_param(
            "ii",
            $request['handover_id'],
            $request_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to expire other requests: " .
                $stmt->error
            );
        }


        $expired_count =
            $stmt->affected_rows;


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


        if (!$stmt->execute()) {

            throw new Exception(
                "Notification creation failed: " .
                $stmt->error
        );
        }


        $stmt->close();


        /* =============================================
           COMMIT
           ============================================= */

        $conn->commit();


        echo json_encode([

            "success" => true,

            "message" =>
                "Handover accepted successfully. Other pending requests have expired.",

            "status" =>
                "ACCEPTED",

            "expired_count" =>
                $expired_count

        ]);


        exit;
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