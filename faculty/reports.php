<?php

require_once("../config/db.php");

/* --------------------------------
   Get Sections
-------------------------------- */

$sections = $conn->query("
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_name
");


/* --------------------------------
   Selected Section
-------------------------------- */

$section_id = isset($_GET['section_id'])
    ? (int)$_GET['section_id']
    : 0;


/* --------------------------------
   Section Attendance Report
-------------------------------- */

$report = [];

if ($section_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            st.student_id,
            st.register_number,
            st.student_name,

            COUNT(a.attendance_id) AS total_classes,

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

        FROM students st

        LEFT JOIN attendance a
            ON st.student_id = a.student_id

        WHERE st.section_id = ?

        GROUP BY
            st.student_id,
            st.register_number,
            st.student_name

        ORDER BY
            st.register_number
    ");

    $stmt->bind_param(
        "i",
        $section_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $total =
            (int)$row['total_classes'];

        $present =
            (int)$row['present_classes'];

        $absent =
            (int)$row['absent_classes'];

        $percentage = 0;

        if ($total > 0) {

            $percentage =
                ($present / $total) * 100;
        }

        $row['percentage'] =
            $percentage;

        $report[] = $row;
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Section-wise Attendance Report</title>

</head>

<body>

<h1>
    Section-wise Attendance Report
</h1>


<!-- --------------------------------
     Section Selection
--------------------------------- -->

<form method="GET">

    <label>

        <strong>
            Select Section:
        </strong>

    </label>


    <select
        name="section_id"
        required
    >

        <option value="">
            -- Select Section --
        </option>


        <?php
        while (
            $section =
            $sections->fetch_assoc()
        ):
        ?>

            <option
                value="<?php
                echo $section['section_id'];
                ?>"
                <?php

                if (
                    $section['section_id']
                    == $section_id
                ) {

                    echo "selected";
                }

                ?>
            >

                <?php
                echo htmlspecialchars(
                    $section['section_name']
                );
                ?>

            </option>

        <?php endwhile; ?>

    </select>


    <button type="submit">

        Generate Report

    </button>

</form>


<hr>


<?php if ($section_id > 0): ?>

    <h2>
        Student Attendance Report
    </h2>


    <?php if (count($report) == 0): ?>

        <p style="color:red;">

            <strong>
                No students found for this section.
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
                    Total Classes
                </th>

                <th>
                    Present
                </th>

                <th>
                    Absent
                </th>

                <th>
                    Attendance Percentage
                </th>

            </tr>


            <?php
            foreach ($report as $student):
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
                        echo $student['total_classes'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $student['present_classes'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $student['absent_classes'];
                        ?>

                    </td>


                    <td>

                        <strong>

                            <?php
                            echo number_format(
                                $student['percentage'],
                                2
                            );
                            ?>%

                        </strong>

                    </td>

                </tr>


            <?php endforeach; ?>


        </table>


    <?php endif; ?>


<?php endif; ?>


</body>

</html>