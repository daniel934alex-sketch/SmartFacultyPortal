<?php

require_once __DIR__ . "/../config/database.php";

date_default_timezone_set("Asia/Kolkata");

// Current day and time
$current_day = strtoupper(date("l"));
$current_time = date("H:i");

echo "Current day: " . $current_day . PHP_EOL;
echo "Current time: " . $current_time . PHP_EOL;


// Period start times
$period_start_times = [
    1 => "08:30",
    2 => "09:15",
    3 => "10:00",
    4 => "11:00",
    5 => "11:45",
    6 => "12:30",
    7 => "14:00",
    8 => "14:45",
    9 => "15:45"
];


// Find today's published timetable
$sql = "
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.faculty_id,
        t.status,
        f.user_id,
        f.faculty_name,
        s.subject_name
    FROM timetable t
    INNER JOIN faculty f
        ON t.faculty_id = f.faculty_id
    INNER JOIN subjects s
        ON t.subject_id = s.subject_id
    WHERE t.day = ?
      AND t.status = 'PUBLISHED'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_day);
$stmt->execute();

$result = $stmt->get_result();

$reminder_created = false;


while ($row = $result->fetch_assoc()) {

    $period_id = (int)$row['period_id'];

    // Check whether period exists
    if (!isset($period_start_times[$period_id])) {
        continue;
    }

    $class_start = $period_start_times[$period_id];

    // Calculate reminder time = 5 minutes before class
    $reminder_time = date(
        "H:i",
        strtotime($class_start . " -5 minutes")
    );

    echo "Period " . $period_id .
         " | Class: " . $row['subject_name'] .
         " | Start: " . $class_start .
         " | Reminder: " . $reminder_time . PHP_EOL;


    // Check whether it is exactly reminder time
    if ($current_time !== $reminder_time) {
        continue;
    }


    // Prevent duplicate notification
    $check_sql = "
        SELECT notification_id
        FROM notifications
        WHERE user_id = ?
          AND title = 'Upcoming Class'
          AND message = ?
          AND DATE(created_at) = CURDATE()
        LIMIT 1
    ";

    $message = "You have " . $row['subject_name'] . " in 5 minutes.";

    $check_stmt = $conn->prepare($check_sql);

    $check_stmt->bind_param(
        "is",
        $row['user_id'],
        $message
    );

    $check_stmt->execute();

    $check_result = $check_stmt->get_result();


    if ($check_result->num_rows > 0) {

        echo "Reminder already exists for " .
             $row['subject_name'] . PHP_EOL;

        $check_stmt->close();

        continue;
    }

    $check_stmt->close();


    // Insert notification
    $insert_sql = "
        INSERT INTO notifications
        (
            user_id,
            title,
            message,
            notification_type,
            is_read
        )
        VALUES
        (
            ?,
            'Upcoming Class',
            ?,
            'CLASS_REMINDER',
            0
        )
    ";

    $insert_stmt = $conn->prepare($insert_sql);

    $insert_stmt->bind_param(
        "is",
        $row['user_id'],
        $message
    );

    if ($insert_stmt->execute()) {

        echo "SUCCESS: Reminder created for " .
             $row['faculty_name'] .
             " - " .
             $row['subject_name'] .
             PHP_EOL;

        $reminder_created = true;

    } else {

        echo "ERROR: Could not create reminder." . PHP_EOL;
    }

    $insert_stmt->close();
}


if (!$reminder_created) {
    echo "No reminder created at this time." . PHP_EOL;
}


$stmt->close();
$conn->close();

?>