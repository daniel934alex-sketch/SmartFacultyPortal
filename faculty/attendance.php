<?php

session_start();

require_once("../config/db.php");


/* --------------------------------
   Faculty Login Check
-------------------------------- */

if (
    !isset($_SESSION['user_id'])
    ||
    $_SESSION['role'] !== 'FACULTY'
    ||
    !isset($_SESSION['faculty_id'])
) {

    header("Location: ../auth/login.php");
    exit;
}


/* --------------------------------
   Logged-in Faculty ID
-------------------------------- */

$faculty_id = (int)$_SESSION['faculty_id'];


$selected_date = $_GET['date'] ?? date('Y-m-d');

$period_id = isset($_GET['period_id'])
    ? (int)$_GET['period_id']
    : 1;

$message = "";
$message_color = "green";


/* --------------------------------
   Attendance Update
-------------------------------- */

if (
    isset(
        $_GET['action'],
        $_GET['student_id'],
        $_GET['timetable_id']
    )
) {

    $action = $_GET['action'];

    $student_id =
        (int)$_GET['student_id'];

    $timetable_id =
        (int)$_GET['timetable_id'];


    if (
        $action === "present"
        ||
        $action === "absent"
    ) {

        $status =
            strtoupper($action);


        /* --------------------------------
           Check Existing Attendance
        -------------------------------- */

        $check = $conn->prepare("
            SELECT
                attendance_id,
                status
            FROM attendance
            WHERE timetable_id = ?
              AND student_id = ?
              AND attendance_date = ?
        ");


        $check->bind_param(
            "iis",
            $timetable_id,
            $student_id,
            $selected_date
        );


        $check->execute();


        $result =
            $check->get_result();


        /* --------------------------------
           Duplicate Attendance Check
        -------------------------------- */

        if (
            $result->num_rows > 0
        ) {

            $row =
                $result->fetch_assoc();


            $existing_status =
                $row['status'];


            $message =
                "Attendance already marked as "
                . $existing_status
                . ".";


            $message_color =
                "red";


        } else {


            /* --------------------------------
               Insert Attendance
            -------------------------------- */

            $insert = $conn->prepare("
                INSERT INTO attendance
                (
                    timetable_id,
                    student_id,
                    faculty_id,
                    attendance_date,
                    status,
                    marked_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");


            $insert->bind_param(
                "iiiss",
                $timetable_id,
                $student_id,
                $faculty_id,
                $selected_date,
                $status
            );


            if (
                $insert->execute()
            ) {

                $message =
                    "Attendance marked as "
                    . $status
                    . " successfully!";


                $message_color =
                    "green";


            } else {

                $message =
                    "Error while saving attendance.";


                $message_color =
                    "red";
            }
        }
    }
}


/* --------------------------------
   Get Periods
-------------------------------- */

$periods = $conn->query("
    SELECT
        period_id,
        period_number,
        start_time,
        end_time
    FROM periods
    ORDER BY period_number
");


/* --------------------------------
   Date Validation
-------------------------------- */

$today =
    date('Y-m-d');


if (
    $selected_date > $today
) {

    $message =
        "Future date attendance cannot be marked.";


    $message_color =
        "red";


    $timetable =
        false;


} else {


    /* --------------------------------
       Get Day Name
    -------------------------------- */

    $day_name =
        strtoupper(
            date(
                'l',
                strtotime($selected_date)
            )
        );


    /* --------------------------------
       Find Published Timetable
    -------------------------------- */

    $stmt = $conn->prepare("
        SELECT
            timetable_id,
            section_id,
            subject_id,
            faculty_id,
            classroom_id
        FROM timetable
        WHERE day = ?
          AND period_id = ?
          AND faculty_id = ?
          AND status = 'PUBLISHED'
        LIMIT 1
    ");


    $stmt->bind_param(
        "sii",
        $day_name,
        $period_id,
        $faculty_id
    );


    $stmt->execute();


    $timetable =
        $stmt
        ->get_result()
        ->fetch_assoc();
}


/* --------------------------------
   Get Students
-------------------------------- */

$students = [];


if (
    $timetable
) {

    $section_id =
        $timetable['section_id'];


    $timetable_id =
        $timetable['timetable_id'];


    $student_stmt = $conn->prepare("
        SELECT
            student_id,
            register_number,
            student_name,
            section_id
        FROM students
        WHERE section_id = ?
        ORDER BY student_id
    ");


    $student_stmt->bind_param(
        "i",
        $section_id
    );


    $student_stmt->execute();


    $student_result =
        $student_stmt->get_result();


    while (
        $row =
        $student_result->fetch_assoc()
    ) {

        $students[] =
            $row;
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>
        Faculty Attendance
    </title>

</head>

<body>


<h1>
    Faculty Attendance Page
</h1>


<p>

    <strong>
        Logged-in Faculty:
    </strong>

    <?php
    echo htmlspecialchars(
        $_SESSION['username']
    );
    ?>

</p>


<p>

    <strong>
        Faculty ID:
    </strong>

    <?php
    echo $faculty_id;
    ?>

</p>


<?php if ($message != ""): ?>

    <p
        style="color:
        <?php
        echo $message_color;
        ?>;"
    >

        <strong>

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </strong>

    </p>

<?php endif; ?>


<!-- --------------------------------
     Date and Period Selection
--------------------------------- -->

<form method="GET">


    <label>

        <strong>
            Select Date:
        </strong>

    </label>


    <input
        type="date"
        name="date"
        value="<?php
        echo htmlspecialchars(
            $selected_date
        );
        ?>"
        required
    >


    <br>
    <br>


    <label>

        <strong>
            Select Period:
        </strong>

    </label>


    <select
        name="period_id"
        required
    >


        <?php

        while (
            $period =
            $periods->fetch_assoc()
        ):

        ?>


            <option
                value="<?php
                echo $period['period_id'];
                ?>"
                <?php

                if (
                    $period['period_id']
                    == $period_id
                ) {

                    echo "selected";
                }

                ?>
            >


                Period
                <?php
                echo $period['period_number'];
                ?>

                -

                <?php
                echo date(
                    "H:i:s",
                    strtotime(
                        $period['start_time']
                    )
                );
                ?>

                -

                <?php
                echo date(
                    "H:i:s",
                    strtotime(
                        $period['end_time']
                    )
                );
                ?>


            </option>


        <?php
        endwhile;
        ?>


    </select>


    <br>
    <br>


    <button
        type="submit"
    >

        Load Students

    </button>


</form>


<hr>


<h2>
    Student Attendance
</h2>


<p>

    <strong>
        Date:
    </strong>

    <?php
    echo htmlspecialchars(
        $selected_date
    );
    ?>

</p>


<?php if (!$timetable): ?>


    <p style="color:red;">

        <strong>

            No published timetable found
            for this faculty, date and period.

        </strong>

    </p>


<?php elseif (
    count($students) == 0
): ?>


    <p style="color:red;">

        <strong>

            No students found
            for this section.

        </strong>

    </p>


<?php else: ?>


    <table
        border="1"
        cellpadding="10"
    >


        <tr>

            <th>
                Student ID
            </th>

            <th>
                Register Number
            </th>

            <th>
                Student Name
            </th>

            <th>
                Section ID
            </th>

            <th>
                Current Status
            </th>

            <th>
                Attendance
            </th>

        </tr>


        <?php

        foreach (
            $students
            as $student
        ):

        ?>


            <?php


            /* --------------------------------
               Get Current Attendance Status
            -------------------------------- */

            $status_stmt =
                $conn->prepare("
                    SELECT
                        status
                    FROM attendance
                    WHERE timetable_id = ?
                      AND student_id = ?
                      AND attendance_date = ?
                ");


            $status_stmt->bind_param(
                "iis",
                $timetable_id,
                $student['student_id'],
                $selected_date
            );


            $status_stmt->execute();


            $status_result =
                $status_stmt
                ->get_result();


            $current_status =
                "NOT MARKED";


            if (
                $status_result->num_rows > 0
            ) {

                $status_row =
                    $status_result
                    ->fetch_assoc();


                $current_status =
                    $status_row['status'];
            }


            ?>


            <tr>


                <td>

                    <?php
                    echo $student['student_id'];
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $student['register_number']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $student['student_name']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo $student['section_id'];
                    ?>

                </td>


                <td>

                    <strong>

                        <?php
                        echo $current_status;
                        ?>

                    </strong>

                </td>


                <td>


                    <!-- Present Button -->

                    <a
                        href="?date=<?php
                        echo urlencode(
                            $selected_date
                        );
                        ?>&period_id=<?php
                        echo $period_id;
                        ?>&action=present&student_id=<?php
                        echo $student['student_id'];
                        ?>&timetable_id=<?php
                        echo $timetable_id;
                        ?>"
                    >

                        <button
                            type="button"
                        >

                            Present

                        </button>

                    </a>


                    <!-- Absent Button -->

                    <a
                        href="?date=<?php
                        echo urlencode(
                            $selected_date
                        );
                        ?>&period_id=<?php
                        echo $period_id;
                        ?>&action=absent&student_id=<?php
                        echo $student['student_id'];
                        ?>&timetable_id=<?php
                        echo $timetable_id;
                        ?>"
                    >

                        <button
                            type="button"
                        >

                            Absent

                        </button>

                    </a>


                </td>


            </tr>


        <?php
        endforeach;
        ?>


    </table>


<?php endif; ?>


</body>

</html>