<?php

require_once("../config/db.php");

/* --------------------------------
   Selected Date
-------------------------------- */

$selected_date = $_GET['date'] ?? date('Y-m-d');


/* --------------------------------
   Selected Period
-------------------------------- */

$period_id = isset($_GET['period_id'])
    ? (int)$_GET['period_id']
    : 0;


/* --------------------------------
   Get Periods
-------------------------------- */

$periods = [];

$period_result = $conn->query("
    SELECT
        period_id,
        period_number,
        start_time,
        end_time
    FROM periods
    ORDER BY period_number
");

if ($period_result) {

    while (
        $row = $period_result->fetch_assoc()
    ) {

        $periods[] = $row;
    }
}


/* --------------------------------
   Get Day Name
-------------------------------- */

$day_name = strtoupper(
    date('l', strtotime($selected_date))
);


/* --------------------------------
   Get Timetable
-------------------------------- */

$classes = [];

if ($period_id > 0) {

    $timetable_stmt = $conn->prepare("
        SELECT
            t.timetable_id,
            t.section_id,
            t.subject_id,
            t.faculty_id,

            sec.section_name,

            sub.subject_code,
            sub.subject_name,

            f.faculty_code,
            f.faculty_name,

            p.period_number,
            p.start_time,
            p.end_time

        FROM timetable t

        INNER JOIN sections sec
            ON t.section_id = sec.section_id

        INNER JOIN subjects sub
            ON t.subject_id = sub.subject_id

        INNER JOIN faculty f
            ON t.faculty_id = f.faculty_id

        INNER JOIN periods p
            ON t.period_id = p.period_id

        WHERE t.day = ?
          AND t.period_id = ?
          AND t.status = 'PUBLISHED'

        ORDER BY
            sec.section_name
    ");

    $timetable_stmt->bind_param(
        "si",
        $day_name,
        $period_id
    );

    $timetable_stmt->execute();

    $timetable_result =
        $timetable_stmt->get_result();

    while (
        $row =
        $timetable_result->fetch_assoc()
    ) {

        $classes[] = $row;
    }
}


/* --------------------------------
   Get Students + Attendance
-------------------------------- */

$reports = [];

foreach ($classes as $class) {

    $student_stmt = $conn->prepare("
        SELECT
            st.student_id,
            st.register_number,
            st.student_name,

            COALESCE(a.status, 'NOT MARKED')
                AS attendance_status

        FROM students st

        LEFT JOIN attendance a
            ON st.student_id = a.student_id
            AND a.timetable_id = ?
            AND a.attendance_date = ?

        WHERE st.section_id = ?

        ORDER BY
            st.register_number
    ");

    $student_stmt->bind_param(
        "isi",
        $class['timetable_id'],
        $selected_date,
        $class['section_id']
    );

    $student_stmt->execute();

    $student_result =
        $student_stmt->get_result();

    $students = [];

    while (
        $student =
        $student_result->fetch_assoc()
    ) {

        $students[] = $student;
    }

    $class['students'] = $students;

    $reports[] = $class;
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Period-wise Attendance Report</title>

</head>

<body>


<h1>
    Period-wise Attendance Report
</h1>


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

        <option value="">

            -- Select Period --

        </option>


        <?php foreach ($periods as $period): ?>

            <option
                value="<?php
                echo $period['period_id'];
                ?>"
                <?php

                if (
                    $period_id
                    == $period['period_id']
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
                    "H:i",
                    strtotime(
                        $period['start_time']
                    )
                );

                ?>

                to

                <?php

                echo date(
                    "H:i",
                    strtotime(
                        $period['end_time']
                    )
                );

                ?>

            </option>

        <?php endforeach; ?>

    </select>


    <button type="submit">

        Generate Report

    </button>

</form>


<hr>


<?php if ($period_id == 0): ?>

    <p>
        Select a date and period to generate the report.
    </p>


<?php elseif (count($reports) == 0): ?>

    <p style="color:red;">

        <strong>

            No published class found for this
            date and period.

        </strong>

    </p>


<?php else: ?>


    <h2>
        Class Details
    </h2>


    <?php foreach ($reports as $class): ?>


        <table
            border="1"
            cellpadding="10"
        >

            <tr>

                <th>
                    Date
                </th>

                <td>

                    <?php
                    echo htmlspecialchars(
                        $selected_date
                    );
                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Day
                </th>

                <td>

                    <?php
                    echo htmlspecialchars(
                        $day_name
                    );
                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Period
                </th>

                <td>

                    <?php
                    echo $class['period_number'];
                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Time
                </th>

                <td>

                    <?php

                    echo date(
                        "H:i",
                        strtotime(
                            $class['start_time']
                        )
                    );

                    ?>

                    -

                    <?php

                    echo date(
                        "H:i",
                        strtotime(
                            $class['end_time']
                        )
                    );

                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Subject
                </th>

                <td>

                    <?php

                    echo htmlspecialchars(
                        $class['subject_code']
                    );

                    ?>

                    -

                    <?php

                    echo htmlspecialchars(
                        $class['subject_name']
                    );

                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Faculty
                </th>

                <td>

                    <?php

                    echo htmlspecialchars(
                        $class['faculty_code']
                    );

                    ?>

                    -

                    <?php

                    echo htmlspecialchars(
                        $class['faculty_name']
                    );

                    ?>

                </td>

            </tr>


            <tr>

                <th>
                    Section
                </th>

                <td>

                    <?php

                    echo htmlspecialchars(
                        $class['section_name']
                    );

                    ?>

                </td>

            </tr>

        </table>


        <br>


        <h3>
            Student Attendance
        </h3>


        <?php if (
            count($class['students']) == 0
        ): ?>

            <p style="color:red;">

                No students found for this section.

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
                        Attendance Status
                    </th>

                </tr>


                <?php
                foreach (
                    $class['students']
                    as $student
                ):
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

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $student['attendance_status']
                                );

                                ?>

                            </strong>

                        </td>

                    </tr>


                <?php endforeach; ?>


            </table>


        <?php endif; ?>


        <br>
        <hr>
        <br>


    <?php endforeach; ?>


<?php endif; ?>


</body>

</html>