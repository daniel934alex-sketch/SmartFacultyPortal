<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Timetable Management";

/*
|--------------------------------------------------------------------------
| Get Current Timetable
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.timetable_id,
        t.day,
        t.status,
        p.period_number,
        p.start_time,
        p.end_time,
        sec.section_name,
        s.subject_code,
        s.subject_name,
        s.subject_type,
        f.faculty_code,
        f.faculty_name,
        c.room_number,
        c.room_type
    FROM timetable t
    INNER JOIN periods p
        ON t.period_id = p.period_id
    INNER JOIN sections sec
        ON t.section_id = sec.section_id
    INNER JOIN subjects s
        ON t.subject_id = s.subject_id
    INNER JOIN faculty f
        ON t.faculty_id = f.faculty_id
    INNER JOIN classrooms c
        ON t.classroom_id = c.classroom_id
    ORDER BY
        FIELD(
            t.day,
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),
        p.period_number,
        sec.section_name
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Timetable Management</h1>
            <p>View the current timetable uploaded by the HOD.</p>
        </div>

        <div class="page-actions">
            <a
                href="timetable-upload.php"
                class="btn btn-secondary"
                style="background: #252b47; border: 1px solid #554988; color: #ded4ff;"
            >
                ⬆ Upload Timetable
            </a>
        </div>

    </div>

    <?php if (!$result): ?>

        <div class="alert alert-error">
            Unable to load the timetable. Please check the database connection.
        </div>

    <?php else: ?>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Period</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Room / Lab</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row["day"]);
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        P<?php
                                        echo (int) $row["period_number"];
                                        ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            substr($row["start_time"], 0, 5)
                                        );
                                        ?>
                                        -
                                        <?php
                                        echo htmlspecialchars(
                                            substr($row["end_time"], 0, 5)
                                        );
                                        ?>
                                    </small>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row["section_name"]);
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                        echo htmlspecialchars($row["subject_code"]);
                                        ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?php
                                        echo htmlspecialchars($row["subject_name"]);
                                        ?>
                                    </small>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row["faculty_name"]);
                                    ?>
                                    <br>
                                    <small>
                                        <?php
                                        echo htmlspecialchars($row["faculty_code"]);
                                        ?>
                                    </small>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars($row["room_number"]);
                                    ?>
                                    <br>
                                    <small>
                                        <?php
                                        echo htmlspecialchars($row["room_type"]);
                                        ?>
                                    </small>
                                </td>

                                <td>
                                    <?php if ($row["status"] === "PUBLISHED"): ?>

                                        <span class="status-badge active">
                                            PUBLISHED
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge warning">
                                            DRAFT
                                        </span>

                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">
                                No timetable entries found. Upload a timetable to display entries here.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</main>

<?php require_once "../includes/footer.php"; ?>