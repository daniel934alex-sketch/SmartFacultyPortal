<?php

require_once("../config/db.php");

/*
|--------------------------------------------------------------------------
| Testing Values
|--------------------------------------------------------------------------
*/

$faculty_id = 1;
$timetable_id = 1;
$student_id = 1;
$attendance_date = date('Y-m-d');
$status = "PRESENT";

$messages = [];
$security_passed = true;


/*
|--------------------------------------------------------------------------
| 1. Validate Attendance Status
|--------------------------------------------------------------------------
*/

if (
    $status !== "PRESENT" &&
    $status !== "ABSENT"
) {

    $messages[] =
        "Invalid attendance status.";

    $security_passed = false;
}


/*
|--------------------------------------------------------------------------
| 2. Validate Date
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');

if ($attendance_date > $today) {

    $messages[] =
        "Future date attendance is not allowed.";

    $security_passed = false;
}


/*
|--------------------------------------------------------------------------
| 3. Check Timetable
|--------------------------------------------------------------------------
*/

$timetable_stmt = $conn->prepare("
    SELECT
        timetable_id,
        section_id,
        faculty_id,
        subject_id,
        period_id,
        status
    FROM timetable
    WHERE timetable_id = ?
");

$timetable_stmt->bind_param(
    "i",
    $timetable_id
);

$timetable_stmt->execute();

$timetable_result =
    $timetable_stmt->get_result();

$timetable =
    $timetable_result->fetch_assoc();


if (!$timetable) {

    $messages[] =
        "Timetable record not found.";

    $security_passed = false;

} else {

    /*
    |--------------------------------------------------------------------------
    | 4. Faculty Authorization Check
    |--------------------------------------------------------------------------
    */

    if (
        (int)$timetable['faculty_id']
        !== $faculty_id
    ) {

        $messages[] =
            "Faculty is not authorized for this timetable.";

        $security_passed = false;
    }


    /*
    |--------------------------------------------------------------------------
    | 5. Published Timetable Check
    |--------------------------------------------------------------------------
    */

    if (
        $timetable['status']
        !== "PUBLISHED"
    ) {

        $messages[] =
            "Attendance can only be marked for a published timetable.";

        $security_passed = false;
    }


    /*
    |--------------------------------------------------------------------------
    | 6. Student Validation
    |--------------------------------------------------------------------------
    */

    $student_stmt = $conn->prepare("
        SELECT
            student_id,
            section_id
        FROM students
        WHERE student_id = ?
    ");

    $student_stmt->bind_param(
        "i",
        $student_id
    );

    $student_stmt->execute();

    $student_result =
        $student_stmt->get_result();

    $student =
        $student_result->fetch_assoc();


    if (!$student) {

        $messages[] =
            "Student record not found.";

        $security_passed = false;

    } else {

        /*
        |--------------------------------------------------------------------------
        | 7. Student Section Validation
        |--------------------------------------------------------------------------
        */

        if (
            (int)$student['section_id']
            !== (int)$timetable['section_id']
        ) {

            $messages[] =
                "Student does not belong to the timetable section.";

            $security_passed = false;
        }
    }
}


/*
|--------------------------------------------------------------------------
| 8. Duplicate Attendance Check
|--------------------------------------------------------------------------
*/

$duplicate_stmt = $conn->prepare("
    SELECT
        attendance_id,
        status
    FROM attendance
    WHERE timetable_id = ?
      AND student_id = ?
      AND attendance_date = ?
");

$duplicate_stmt->bind_param(
    "iis",
    $timetable_id,
    $student_id,
    $attendance_date
);

$duplicate_stmt->execute();

$duplicate_result =
    $duplicate_stmt->get_result();


if ($duplicate_result->num_rows > 0) {

    $existing =
        $duplicate_result->fetch_assoc();

    $messages[] =
        "Duplicate attendance detected. Existing status: "
        . $existing['status'];

    $security_passed = false;
}


/*
|--------------------------------------------------------------------------
| Final Result
|--------------------------------------------------------------------------
*/

if ($security_passed) {

    $messages[] =
        "All attendance security checks passed.";

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Attendance Security</title>

</head>

<body>

<h1>
    Attendance Security Check
</h1>


<h2>
    Test Details
</h2>


<table
    border="1"
    cellpadding="10"
>

    <tr>

        <th>
            Faculty ID
        </th>

        <td>
            <?php
            echo $faculty_id;
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Timetable ID
        </th>

        <td>
            <?php
            echo $timetable_id;
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Student ID
        </th>

        <td>
            <?php
            echo $student_id;
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Date
        </th>

        <td>
            <?php
            echo $attendance_date;
            ?>
        </td>

    </tr>


    <tr>

        <th>
            Status
        </th>

        <td>
            <?php
            echo $status;
            ?>
        </td>

    </tr>

</table>


<hr>


<h2>
    Security Validation Result
</h2>


<?php if ($security_passed): ?>

    <p style="color:green;">

        <strong>
            SECURITY CHECK PASSED
        </strong>

    </p>


<?php else: ?>

    <p style="color:red;">

        <strong>
            SECURITY CHECK FAILED
        </strong>

    </p>

<?php endif; ?>


<ul>

    <?php foreach ($messages as $message): ?>

        <li>

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </li>

    <?php endforeach; ?>

</ul>


<hr>


<h2>
    Security Rules Checked
</h2>


<ul>

    <li>
        Valid attendance status
    </li>

    <li>
        Future date prevention
    </li>

    <li>
        Timetable existence
    </li>

    <li>
        Faculty authorization
    </li>

    <li>
        Published timetable validation
    </li>

    <li>
        Student existence
    </li>

    <li>
        Student section validation
    </li>

    <li>
        Duplicate attendance prevention
    </li>

</ul>


</body>

</html>