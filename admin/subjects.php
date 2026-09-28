<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Subject Management";

$sql = "
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.subject_type,
        s.weekly_periods,
        s.continuous_periods,
        s.department_id,
        d.department_code,
        d.department_name
    FROM subjects s
    LEFT JOIN departments d
        ON s.department_id = d.department_id
    ORDER BY s.subject_id ASC
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Subject Management</h1>
            <p>
                Manage subjects, subject type and weekly periods.
            </p>
        </div>

        <a
            href="subject-add.php"
            class="btn btn-primary"
        >
            + Add Subject
        </a>

    </div>


    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="alert alert-success">
                Subject added successfully.
            </div>

        <?php elseif ($_GET["success"] === "updated"): ?>

            <div class="alert alert-success">
                Subject updated successfully.
            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="alert alert-success">
                Subject deleted successfully.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-error">

            <?php

            $error = $_GET["error"];

            if ($error === "in_use") {

                echo "This subject cannot be deleted because it is already being used.";

            } elseif ($error === "not_found") {

                echo "Subject not found.";

            } elseif ($error === "delete_failed") {

                echo "Failed to delete subject.";

            } elseif ($error === "invalid_request") {

                echo "Invalid request.";

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

                    <th>Subject Code</th>

                    <th>Subject Name</th>

                    <th>Type</th>

                    <th>Weekly Periods</th>

                    <th>Continuous</th>

                    <th>Department</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["subject_code"]
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["subject_name"]
                                );
                                ?>
                            </td>

                            <td>

                                <?php if ($row["subject_type"] === "THEORY"): ?>

                                    <span class="status-badge active">
                                        THEORY
                                    </span>

                                <?php elseif ($row["subject_type"] === "LAB"): ?>

                                    <span class="status-badge warning">
                                        LAB
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge">
                                        PROJECT
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php
                                echo (int)$row["weekly_periods"];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$row["continuous_periods"];
                                ?>
                            </td>

                            <td>

                                <?php

                                if (!empty($row["department_code"])) {

                                    echo htmlspecialchars(
                                        $row["department_code"]
                                    );

                                } else {

                                    echo "Not assigned";
                                }

                                ?>

                            </td>

                            <td>

                                <a
                                    href="subject-edit.php?id=<?php
                                        echo (int)$row["subject_id"];
                                    ?>"
                                    class="btn btn-primary"
                                >
                                    Edit
                                </a>

                                <a
                                    href="subject-delete.php?id=<?php
                                        echo (int)$row["subject_id"];
                                    ?>"
                                    class="btn btn-danger"
                                    onclick="
                                        return confirm(
                                            'WARNING: This will permanently delete this subject. Continue?'
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

                        <td colspan="7">
                            No subjects found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>