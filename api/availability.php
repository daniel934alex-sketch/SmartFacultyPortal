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
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "User session not found. Please login again."
    ]);
    exit;
}

/* =========================================================
   CHECK ROLE
   ========================================================= */

if (
    !isset($_SESSION['role']) ||
    strtoupper((string)$_SESSION['role']) !== 'FACULTY'
) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Only faculty can access availability."
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* =========================================================
   FIND FACULTY RECORD
   ========================================================= */

$stmt = $conn->prepare("
    SELECT faculty_id, faculty_name, department_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Faculty query failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$faculty_res = $stmt->get_result();

if ($faculty_res->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Faculty record not found for this user account."
    ]);
    $stmt->close();
    exit;
}

$faculty = $faculty_res->fetch_assoc();
$faculty_id = (int)$faculty['faculty_id'];
$faculty_name = $faculty['faculty_name'] ?? 'Faculty Member';
$department_id = (int)$faculty['department_id'];
$stmt->close();

/* =========================================================
   HANDLE GET / FETCH REQUEST (LOAD SAVED AVAILABILITY)
   ========================================================= */

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'get') {
    $date = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));

    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid date format. Expected YYYY-MM-DD."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT period_id, status, reason
        FROM faculty_availability
        WHERE faculty_id = ? AND availability_date = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Availability fetch preparation failed: " . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("is", $faculty_id, $date);
    $stmt->execute();
    $res = $stmt->get_result();

    $availability_map = [];
    while ($row = $res->fetch_assoc()) {
        $availability_map[(int)$row['period_id']] = [
            "status" => $row['status'],
            "reason" => $row['reason']
        ];
    }
    $stmt->close();

    echo json_encode([
        "success" => true,
        "date" => $date,
        "availability" => $availability_map
    ]);
    exit;
}

/* =========================================================
   HANDLE POST REQUEST (SAVE AVAILABILITY)
   ========================================================= */

// CSRF validation if token exists in session
if (isset($_SESSION['csrf_token'])) {
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token !== '' && !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Invalid CSRF security token."
        ]);
        exit;
    }
}

$date = trim($_POST['date'] ?? '');
$period_id = (int)($_POST['period_id'] ?? 0);
$status = strtoupper(trim($_POST['status'] ?? ''));
$reason = trim($_POST['reason'] ?? '');

// Validate input presence
if ($date === '' || $period_id <= 0 || $status === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Date, period, and status are required."
    ]);
    exit;
}

// Validate date format
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid date format. Expected YYYY-MM-DD."
    ]);
    exit;
}

// Validate status
$allowed_status = ["AVAILABLE", "UNAVAILABLE", "LEAVE"];
if (!in_array($status, $allowed_status, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid availability status. Must be CAN ATTEND (AVAILABLE), CAN'T GO (UNAVAILABLE), or LEAVE."
    ]);
    exit;
}

// Validate reason requirement for UNAVAILABLE and LEAVE
if (($status === 'UNAVAILABLE' || $status === 'LEAVE') && $reason === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a reason for " . ($status === 'LEAVE' ? "leave." : "unavailability.")
    ]);
    exit;
}

// For AVAILABLE, clear reason
if ($status === 'AVAILABLE') {
    $reason = null;
}

/* =========================================================
   CHECK EXISTING STATUS TO PREVENT DUPLICATES
   ========================================================= */

$stmt = $conn->prepare("
    SELECT availability_id, status, reason
    FROM faculty_availability
    WHERE faculty_id = ? AND availability_date = ? AND period_id = ?
    LIMIT 1
");

$existing_row = null;
if ($stmt) {
    $stmt->bind_param("isi", $faculty_id, $date, $period_id);
    $stmt->execute();
    $existing_res = $stmt->get_result();
    if ($existing_res->num_rows > 0) {
        $existing_row = $existing_res->fetch_assoc();
    }
    $stmt->close();
}

$previous_status = $existing_row ? $existing_row['status'] : null;
$previous_reason = $existing_row ? trim((string)$existing_row['reason']) : '';

// If exact same status and reason, skip writing & duplicate notifications
if (
    $existing_row !== null &&
    $previous_status === $status &&
    ($status === 'AVAILABLE' || $previous_reason === $reason)
) {
    echo json_encode([
        "success" => true,
        "message" => "Period {$period_id} is already saved as {$status}.",
        "period_id" => $period_id,
        "status" => $status,
        "notifications_sent" => 0,
        "already_saved" => true
    ]);
    exit;
}

/* =========================================================
   FETCH PERIOD TIMINGS
   ========================================================= */

$period_timings = "Period {$period_id}";
$stmt = $conn->prepare("
    SELECT period_number, start_time, end_time
    FROM periods
    WHERE period_id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $period_id);
    $stmt->execute();
    $period_res = $stmt->get_result();
    if ($period_res->num_rows > 0) {
        $p_row = $period_res->fetch_assoc();
        $start = date("h:i A", strtotime($p_row['start_time']));
        $end = date("h:i A", strtotime($p_row['end_time']));
        $period_timings = "{$start} - {$end}";
    }
    $stmt->close();
}

$day = strtoupper(date("l", strtotime($date)));

/* =========================================================
   START TRANSACTION & ATOMIC SAVE
   ========================================================= */

$conn->begin_transaction();

try {
    // 1. SAVE AVAILABILITY
    $stmt = $conn->prepare("
        INSERT INTO faculty_availability
        (
            faculty_id,
            availability_date,
            period_id,
            status,
            reason
        )
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            reason = VALUES(reason),
            updated_at = CURRENT_TIMESTAMP
    ");

    if (!$stmt) {
        throw new Exception("Availability statement failed: " . $conn->error);
    }

    $stmt->bind_param(
        "isiss",
        $faculty_id,
        $date,
        $period_id,
        $status,
        $reason
    );

    if (!$stmt->execute()) {
        throw new Exception("Availability save failed: " . $stmt->error);
    }
    $stmt->close();

    // 2. CHECK TIMETABLE FOR AFFECTED CLASS
    $class_stmt = $conn->prepare("
        SELECT
            t.timetable_id,
            t.section_id,
            sec.section_name,
            t.subject_id,
            s.subject_code,
            s.subject_name,
            t.classroom_id,
            c.room_number
        FROM timetable t
        JOIN sections sec ON sec.section_id = t.section_id
        JOIN subjects s ON s.subject_id = t.subject_id
        JOIN classrooms c ON c.classroom_id = t.classroom_id
        WHERE t.faculty_id = ?
          AND t.day = ?
          AND t.period_id = ?
          AND t.status = 'PUBLISHED'
        LIMIT 1
    ");

    if (!$class_stmt) {
        throw new Exception("Class check statement failed: " . $conn->error);
    }

    $class_stmt->bind_param("isi", $faculty_id, $day, $period_id);
    $class_stmt->execute();
    $class_res = $class_stmt->get_result();
    $class = ($class_res->num_rows > 0) ? $class_res->fetch_assoc() : null;
    $class_stmt->close();

    // 3. PREPARE NOTIFICATION HELPERS
    $check_notif = $conn->prepare("
        SELECT notification_id
        FROM notifications
        WHERE user_id = ?
          AND title = ?
          AND message = ?
          AND created_at >= NOW() - INTERVAL 10 MINUTE
        LIMIT 1
    ");

    $insert_notif = $conn->prepare("
        INSERT INTO notifications
        (user_id, title, message, notification_type, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    if (!$check_notif || !$insert_notif) {
        throw new Exception("Notification preparation failed: " . $conn->error);
    }

    $notifications_sent = 0;
    $sent_user_ids = [];

    $sendNotification = function(
        int $target_user_id,
        string $title,
        string $message,
        string $type
    ) use ($check_notif, $insert_notif, &$notifications_sent, &$sent_user_ids): bool {
        if ($target_user_id <= 0 || isset($sent_user_ids[$target_user_id])) {
            return false;
        }

        $check_notif->bind_param("iss", $target_user_id, $title, $message);
        $check_notif->execute();
        $dup_res = $check_notif->get_result();
        if ($dup_res->num_rows > 0) {
            return false;
        }

        $insert_notif->bind_param("isss", $target_user_id, $title, $message, $type);
        if ($insert_notif->execute()) {
            $notifications_sent++;
            $sent_user_ids[$target_user_id] = true;
            return true;
        }
        return false;
    };

    // 4. FIND HOD/ADMIN USERS TO NOTIFY
    $hod_users = [];
    $hod_stmt = $conn->prepare("
        SELECT DISTINCT u.user_id
        FROM users u
        LEFT JOIN faculty f ON f.user_id = u.user_id
        WHERE u.status = 'ACTIVE'
          AND (u.role IN ('HOD', 'ADMIN') OR f.is_hod = 1)
          AND (f.department_id = ? OR f.department_id IS NULL)
    ");

    if ($hod_stmt) {
        $hod_stmt->bind_param("i", $department_id);
        $hod_stmt->execute();
        $hod_res = $hod_stmt->get_result();
        while ($hrow = $hod_res->fetch_assoc()) {
            $hod_users[] = (int)$hrow['user_id'];
        }
        $hod_stmt->close();
    }

    // 5. DISPATCH NOTIFICATIONS BASED ON STATUS & TIMETABLE
    if ($status === 'UNAVAILABLE' || $status === 'LEAVE') {
        $status_label = ($status === 'LEAVE') ? 'ON LEAVE' : "UNAVAILABLE (CAN'T GO)";

        if ($class !== null) {
            // CLASS IS AFFECTED - Full coverage alert
            $class_info = "{$class['subject_name']} ({$class['subject_code']}), Section: {$class['section_name']}, Room: {$class['room_number']}";

            // Notify HOD
            $hod_title = "Faculty {$status} Alert: Period {$period_id} - Class Affected";
            $hod_msg = "{$faculty_name} is {$status_label} on {$date} ({$day}) for Period {$period_id} ({$period_timings}). Affected Class: {$class_info}. Reason: {$reason}. Handover / class coverage is needed.";

            foreach ($hod_users as $h_uid) {
                $sendNotification($h_uid, $hod_title, $hod_msg, 'AVAILABILITY_ALERT');
            }

            // Find relevant affected faculty (eligible alternates capable of covering)
            $alt_stmt = $conn->prepare("
                SELECT f.faculty_id, f.faculty_name, u.user_id,
                       CASE WHEN fs.subject_id = ? THEN 1 ELSE 0 END AS is_subject_match
                FROM faculty f
                INNER JOIN users u ON u.user_id = f.user_id
                LEFT JOIN faculty_availability fa
                    ON fa.faculty_id = f.faculty_id
                    AND fa.availability_date = ?
                    AND fa.period_id = ?
                LEFT JOIN timetable tt
                    ON tt.faculty_id = f.faculty_id
                    AND tt.day = ?
                    AND tt.period_id = ?
                    AND tt.status = 'PUBLISHED'
                LEFT JOIN faculty_subjects fs
                    ON fs.faculty_id = f.faculty_id
                    AND fs.subject_id = ?
                WHERE u.status = 'ACTIVE'
                  AND f.department_id = ?
                  AND f.faculty_id != ?
                  AND (fa.availability_id IS NULL OR fa.status = 'AVAILABLE')
                  AND tt.timetable_id IS NULL
                ORDER BY is_subject_match DESC, f.faculty_name ASC
            ");

            if ($alt_stmt) {
                $subj_id = (int)$class['subject_id'];
                $alt_stmt->bind_param(
                    "isisiiii",
                    $subj_id,
                    $date,
                    $period_id,
                    $day,
                    $period_id,
                    $subj_id,
                    $department_id,
                    $faculty_id
                );
                $alt_stmt->execute();
                $alt_res = $alt_stmt->get_result();

                $subject_matches = [];
                $general_free = [];

                while ($alt = $alt_res->fetch_assoc()) {
                    if ((int)$alt['is_subject_match'] === 1) {
                        $subject_matches[] = $alt;
                    } else {
                        $general_free[] = $alt;
                    }
                }
                $alt_stmt->close();

                // Select recipients: prioritize subject-matched substitutes; fallback to free department faculty
                $recipients = !empty($subject_matches) ? $subject_matches : $general_free;

                $alt_title = "Class Coverage Needed: {$class['subject_code']} - Period {$period_id}";
                $alt_msg = "{$faculty_name} is {$status_label} on {$date} ({$day}) for Period {$period_id} ({$period_timings}). Class: {$class_info}. Reason: {$reason}. Please check handover requests if you are available to cover.";

                foreach ($recipients as $recip) {
                    $alt_uid = (int)$recip['user_id'];
                    $sendNotification($alt_uid, $alt_title, $alt_msg, 'COVERAGE_ALERT');
                }
            }

        } else {
            // NO CLASS SCHEDULED - Only notify HOD of availability change
            $hod_title = "Faculty Availability Update: Period {$period_id}";
            $hod_msg = "{$faculty_name} has marked Period {$period_id} ({$period_timings}) on {$date} ({$day}) as {$status_label}. Reason: {$reason}.";

            foreach ($hod_users as $h_uid) {
                $sendNotification($h_uid, $hod_title, $hod_msg, 'AVAILABILITY_UPDATE');
            }
        }

    } elseif ($status === 'AVAILABLE') {
        // If restoring availability from UNAVAILABLE or LEAVE, notify HOD
        if ($previous_status === 'UNAVAILABLE' || $previous_status === 'LEAVE') {
            $hod_title = "Faculty Availability Restored: Period {$period_id}";
            $hod_msg = "{$faculty_name} has updated availability to AVAILABLE for Period {$period_id} ({$period_timings}) on {$date} ({$day}).";

            foreach ($hod_users as $h_uid) {
                $sendNotification($h_uid, $hod_title, $hod_msg, 'AVAILABILITY_UPDATE');
            }
        }
    }

    $check_notif->close();
    $insert_notif->close();

    // 6. COMMIT TRANSACTION
    $conn->commit();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Period {$period_id} - {$status} saved successfully.",
        "period_id" => $period_id,
        "status" => $status,
        "class_affected" => ($class !== null),
        "notifications_sent" => $notifications_sent
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database save failed: " . $e->getMessage()
    ]);
}

$conn->close();