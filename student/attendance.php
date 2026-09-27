<?php

session_start();

require_once("../config/db.php");


/*
|--------------------------------------------------------------------------
| Student Login Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id'])
    ||
    !isset($_SESSION['role'])
    ||
    $_SESSION['role'] !== 'STUDENT'
    ||
    !isset($_SESSION['student_id'])
) {
    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Logged-in Student ID
|--------------------------------------------------------------------------
*/

$student_id = (int)$_SESSION['student_id'];


/*
|--------------------------------------------------------------------------
| Get Student Details
|--------------------------------------------------------------------------
*/

$student_stmt = $conn->prepare("
    SELECT
        student_id,
        register_number,
        student_name,
        email,
        phone,
        section_id
    FROM students
    WHERE student_id = ?
    LIMIT 1
");

$student_stmt->bind_param(
    "i",
    $student_id
);

$student_stmt->execute();

$student_result = $student_stmt->get_result();

$student = $student_result->fetch_assoc();

$student_stmt->close();


/*
|--------------------------------------------------------------------------
| Overall Attendance
|--------------------------------------------------------------------------
*/

$total_classes = 0;
$present_classes = 0;
$absent_classes = 0;
$percentage = 0;


$overall_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_classes,

        SUM(
            CASE
                WHEN status = 'PRESENT'
                THEN 1
                ELSE 0
            END
        ) AS present_classes,

        SUM(
            CASE
                WHEN status = 'ABSENT'
                THEN 1
                ELSE 0
            END
        ) AS absent_classes

    FROM attendance

    WHERE student_id = ?
");


$overall_stmt->bind_param(
    "i",
    $student_id
);

$overall_stmt->execute();

$overall_result = $overall_stmt->get_result();


if ($overall_result->num_rows > 0) {

    $overall = $overall_result->fetch_assoc();

    $total_classes =
        (int)$overall['total_classes'];

    $present_classes =
        (int)$overall['present_classes'];

    $absent_classes =
        (int)$overall['absent_classes'];
}


$overall_stmt->close();


/*
|--------------------------------------------------------------------------
| Overall Percentage
|--------------------------------------------------------------------------
*/

if ($total_classes > 0) {

    $percentage =
        ($present_classes / $total_classes) * 100;
}


/*
|--------------------------------------------------------------------------
| Subject-wise Attendance
|--------------------------------------------------------------------------
*/

$subject_attendance = [];


$subject_stmt = $conn->prepare("
    SELECT
        s.subject_code,
        s.subject_name,

        COUNT(*) AS total_classes,

        SUM(
            CASE
                WHEN a.status = 'PRESENT'
                THEN 1
                ELSE 0
            END
        ) AS present_classes,

        SUM(
            CASE
                WHEN a.status = 'ABSENT'
                THEN 1
                ELSE 0
            END
        ) AS absent_classes

    FROM attendance a

    INNER JOIN timetable t
        ON a.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    WHERE a.student_id = ?

    GROUP BY
        s.subject_id,
        s.subject_code,
        s.subject_name

    ORDER BY
        s.subject_code
");


$subject_stmt->bind_param(
    "i",
    $student_id
);

$subject_stmt->execute();

$subject_result =
    $subject_stmt->get_result();


while (
    $row =
    $subject_result->fetch_assoc()
) {

    $subject_total =
        (int)$row['total_classes'];

    $subject_present =
        (int)$row['present_classes'];

    $subject_percentage = 0;


    if ($subject_total > 0) {

        $subject_percentage =
            ($subject_present / $subject_total) * 100;
    }


    $row['percentage'] =
        $subject_percentage;


    $subject_attendance[] =
        $row;
}


$subject_stmt->close();


/*
|--------------------------------------------------------------------------
| Attendance History
|--------------------------------------------------------------------------
*/

$records = [];


$history_stmt = $conn->prepare("
    SELECT
        a.attendance_date,
        a.status,
        a.attendance_id,
        s.subject_code,
        s.subject_name

    FROM attendance a

    INNER JOIN timetable t
        ON a.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    WHERE a.student_id = ?

    ORDER BY
        a.attendance_date DESC,
        a.attendance_id DESC
");


$history_stmt->bind_param(
    "i",
    $student_id
);

$history_stmt->execute();

$history_result =
    $history_stmt->get_result();


while (
    $row =
    $history_result->fetch_assoc()
) {

    $records[] = $row;
}


$history_stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Student Attendance
    </title>

</head>


<body>


<h1>
    Student Attendance
</h1>


<?php if (!$student): ?>

    <p style="color:red;">

        <strong>
            Student not found.
        </strong>

    </p>


<?php else: ?>


<!-- =========================================================
     STUDENT DETAILS
========================================================= -->

<h2>
    Student Details
</h2>


<table
    border="1"
    cellpadding="10"
>

    <tr>

        <th>
            Student ID
        </th>

        <td>
            <?php
            echo $student['student_id'];
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Register Number
        </th>

        <td>
            <?php
            echo htmlspecialchars(
                $student['register_number']
            );
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Student Name
        </th>

        <td>
            <?php
            echo htmlspecialchars(
                $student['student_name']
            );
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Email
        </th>

        <td>
            <?php
            echo htmlspecialchars(
                $student['email']
            );
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Phone
        </th>

        <td>
            <?php
            echo htmlspecialchars(
                $student['phone'] ?? ''
            );
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Section ID
        </th>

        <td>
            <?php
            echo $student['section_id'];
            ?>
        </td>

    </tr>

</table>


<br>


<!-- =========================================================
     OVERALL ATTENDANCE
========================================================= -->

<h2>
    Overall Attendance
</h2>


<table
    border="1"
    cellpadding="10"
>

    <tr>

        <th>
            Total Classes
        </th>

        <th>
            Present
        </th>

        <th>
            Absent
        </th>

        <th>
            Percentage
        </th>

    </tr>


    <tr>

        <td>
            <?php
            echo $total_classes;
            ?>
        </td>


        <td>
            <?php
            echo $present_classes;
            ?>
        </td>


        <td>
            <?php
            echo $absent_classes;
            ?>
        </td>


        <td>

            <strong>

                <?php

                echo number_format(
                    $percentage,
                    2
                );

                ?>

                %

            </strong>

        </td>

    </tr>

</table>


<br>


<!-- =========================================================
     SUBJECT-WISE ATTENDANCE
========================================================= -->

<h2>
    Subject-wise Attendance
</h2>


<?php if (count($subject_attendance) == 0): ?>

    <p>
        No subject attendance records found.
    </p>


<?php else: ?>


<table
    border="1"
    cellpadding="10"
>

    <tr>

        <th>
            Subject Code
        </th>

        <th>
            Subject Name
        </th>

        <th>
            Total Classes
        </th>

        <th>
            Present
        </th>

        <th>
            Absent
        </th>

        <th>
            Percentage
        </th>

    </tr>


    <?php

    foreach (
        $subject_attendance
        as $subject
    ):

    ?>


    <tr>

        <td>

            <?php

            echo htmlspecialchars(
                $subject['subject_code']
            );

            ?>

        </td>


        <td>

            <?php

            echo htmlspecialchars(
                $subject['subject_name']
            );

            ?>

        </td>


        <td>

            <?php

            echo $subject['total_classes'];

            ?>

        </td>


        <td>

            <?php

            echo $subject['present_classes'];

            ?>

        </td>


        <td>

            <?php

            echo $subject['absent_classes'];

            ?>

        </td>


        <td>

            <strong>

                <?php

                echo number_format(
                    $subject['percentage'],
                    2
                );

                ?>

                %

            </strong>

        </td>

    </tr>


    <?php endforeach; ?>


</table>


<?php endif; ?>


<br>


<!-- =========================================================
     ATTENDANCE HISTORY
========================================================= -->

<h2>
    Attendance History
</h2>


<?php if (count($records) == 0): ?>

    <p>
        No attendance history found.
    </p>


<?php else: ?>


<table
    border="1"
    cellpadding="10"
>

    <tr>

        <th>
            Date
        </th>

        <th>
            Subject Code
        </th>

        <th>
            Subject Name
        </th>

        <th>
            Status
        </th>

    </tr>


    <?php

    foreach (
        $records
        as $record
    ):

    ?>


    <tr>

        <td>

            <?php

            echo htmlspecialchars(
                $record['attendance_date']
            );

            ?>

        </td>


        <td>

            <?php

            echo htmlspecialchars(
                $record['subject_code']
            );

            ?>

        </td>


        <td>

            <?php

            echo htmlspecialchars(
                $record['subject_name']
            );

            ?>

        </td>


        <td>

            <strong>

                <?php

                echo htmlspecialchars(
                    $record['status']
                );

                ?>

            </strong>

        </td>

    </tr>


    <?php endforeach; ?>


</table>


<?php endif; ?>


<?php endif; ?>


</body>

</html>