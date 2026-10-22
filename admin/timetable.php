<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Timetable Management";


/*
 * Get timetable
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

            <p>
                Manage section, faculty, subject and classroom schedules.
            </p>

        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a
                href="timetable-upload.php"
                class="btn btn-secondary"
                style="background: #252b47; border: 1px solid #554988; color: #ded4ff;"
            >
                ⬆ Upload Timetable
            </a>
            <a
                href="timetable-add.php"
                class="btn btn-primary"
            >
                + Add Timetable
            </a>
        </div>

    </div>


    <!-- Success -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success">

            <?php

            if ($_GET["success"] === "added") {

                echo "Timetable entry added successfully.";

            } elseif ($_GET["success"] === "updated") {

                echo "Timetable entry updated successfully.";

            } elseif ($_GET["success"] === "deleted") {

                echo "Timetable entry deleted successfully.";

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- Errors -->

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-error">

            <?php

            $error = $_GET["error"];

            if ($error === "conflict") {

                echo "Timetable conflict detected. Please check section, faculty and classroom.";

            } elseif ($error === "not_found") {

                echo "Timetable entry not found.";

            } elseif ($error === "invalid_request") {

                echo "Invalid request.";

            } elseif ($error === "delete_failed") {

                echo "Failed to delete timetable entry.";

            } else {

                echo "An error occurred.";

            }

            ?>

        </div>

    <?php endif; ?>


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

                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["day"]
                                );
                                ?>
                            </td>


                            <td>

                                <strong>
                                    P<?php
                                    echo (int)$row["period_number"];
                                    ?>
                                </strong>

                                <br>

                                <small>

                                    <?php
                                    echo htmlspecialchars(
                                        substr(
                                            $row["start_time"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo htmlspecialchars(
                                        substr(
                                            $row["end_time"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>

                                </small>

                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["section_name"]
                                );
                                ?>
                            </td>


                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["subject_code"]
                                    );
                                    ?>
                                </strong>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["subject_name"]
                                    );
                                    ?>
                                </small>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["faculty_name"]
                                );
                                ?>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["faculty_code"]
                                    );
                                    ?>
                                </small>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["room_number"]
                                );
                                ?>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["room_type"]
                                    );
                                    ?>
                                </small>

                            </td>


                            <td>

                                <?php if (
                                    $row["status"] === "PUBLISHED"
                                ): ?>

                                    <span class="status-badge active">
                                        PUBLISHED
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge warning">
                                        DRAFT
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="timetable-edit.php?id=<?php
                                        echo (int)$row["timetable_id"];
                                    ?>"
                                    class="btn btn-primary"
                                >
                                    Edit
                                </a>


                                <a
                                    href="timetable-delete.php?id=<?php
                                        echo (int)$row["timetable_id"];
                                    ?>"
                                    class="btn btn-danger"
                                    onclick="
                                        return confirm(
                                            'Delete this timetable entry?'
                                        );
                                    "
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">
                            No timetable entries found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>