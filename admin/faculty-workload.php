<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Faculty Workload";


/*
|--------------------------------------------------------------------------
| FACULTY WORKLOAD
|--------------------------------------------------------------------------
|
| Count timetable periods for each faculty.
|
| Theory = THEORY subjects
| Lab    = LAB subjects
| Total  = all scheduled periods
|
*/

$sql = "
    SELECT
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        f.designation,

        COALESCE(
            SUM(
                CASE
                    WHEN s.subject_type = 'THEORY'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS theory_periods,

        COALESCE(
            SUM(
                CASE
                    WHEN s.subject_type = 'LAB'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS lab_periods,

        COUNT(t.timetable_id) AS total_periods

    FROM faculty f

    LEFT JOIN timetable t
        ON f.faculty_id = t.faculty_id

    LEFT JOIN subjects s
        ON t.subject_id = s.subject_id

    GROUP BY
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        f.designation

    ORDER BY
        f.faculty_code
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<main>

    <div class="page-header">

        <div>

            <h1>
                Faculty Workload
            </h1>

            <p>
                View faculty teaching workload based on the timetable.
            </p>

        </div>

        <a
            href="faculty.php"
            class="btn btn-secondary"
        >
            Back to Faculty
        </a>

    </div>


    <div class="table-card">

        <div class="table-responsive">

            <table>

                <thead>

                    <tr>

                        <th>
                            Faculty Code
                        </th>

                        <th>
                            Faculty Name
                        </th>

                        <th>
                            Designation
                        </th>

                        <th>
                            Theory Periods
                        </th>

                        <th>
                            Lab Periods
                        </th>

                        <th>
                            Total Periods
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        $result &&
                        $result->num_rows > 0
                    ): ?>

                        <?php while (
                            $row =
                            $result->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["faculty_code"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["faculty_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["designation"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo (int)
                                        $row["theory_periods"];
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo (int)
                                        $row["lab_periods"];
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?php
                                        echo (int)
                                            $row["total_periods"];
                                        ?>

                                    </strong>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                style="text-align:center;"
                            >

                                No faculty records found.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>


<?php require_once "../includes/footer.php"; ?>