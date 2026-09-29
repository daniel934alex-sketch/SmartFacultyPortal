<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Classrooms & Labs";

$sql = "
    SELECT
        classroom_id,
        room_number,
        floor,
        room_type,
        capacity,
        status
    FROM classrooms
    ORDER BY classroom_id ASC
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Classrooms & Labs</h1>
            <p>
                Manage classrooms and laboratories.
            </p>
        </div>

        <a
            href="classroom-add.php"
            class="btn btn-primary"
        >
            + Add Resource
        </a>

    </div>


    <!-- Success Messages -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success">

            <?php

            if ($_GET["success"] === "added") {

                echo "Resource added successfully.";

            } elseif ($_GET["success"] === "updated") {

                echo "Resource updated successfully.";

            } elseif ($_GET["success"] === "deleted") {

                echo "Resource deleted successfully.";

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- Error Messages -->

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-error">

            <?php

            $error = $_GET["error"];

            if ($error === "in_use") {

                echo "This resource cannot be deleted because it is already used in the timetable.";

            } elseif ($error === "not_found") {

                echo "Resource not found.";

            } elseif ($error === "invalid_request") {

                echo "Invalid request.";

            } elseif ($error === "duplicate") {

                echo "Room number already exists.";

            } elseif ($error === "delete_failed") {

                echo "Failed to delete resource.";

            } else {

                echo "An error occurred.";

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- Resource Table -->

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Room / Lab</th>
                    <th>Type</th>
                    <th>Floor / Location</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <!-- Room Number -->

                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["room_number"]
                                    );
                                    ?>
                                </strong>

                            </td>


                            <!-- Type -->

                            <td>

                                <?php if (
                                    $row["room_type"] === "CLASSROOM"
                                ): ?>

                                    <span class="status-badge active">
                                        CLASSROOM
                                    </span>

                                <?php elseif (
                                    $row["room_type"] === "LAB"
                                ): ?>

                                    <span class="status-badge warning">
                                        LAB
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge">
                                        PROJECT
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Floor -->

                            <td>

                                <?php

                                if (
                                    !empty($row["floor"])
                                ) {

                                    echo htmlspecialchars(
                                        $row["floor"]
                                    );

                                } else {

                                    echo "Not specified";

                                }

                                ?>

                            </td>


                            <!-- Capacity -->

                            <td>

                                <?php
                                echo (int)$row["capacity"];
                                ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php if (
                                    $row["status"] === "AVAILABLE"
                                ): ?>

                                    <span class="status-badge active">
                                        AVAILABLE
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge inactive">
                                        UNAVAILABLE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Actions -->

                            <td>

                                <a
                                    href="classroom-edit.php?id=<?php
                                        echo (int)$row["classroom_id"];
                                    ?>"
                                    class="btn btn-primary"
                                >
                                    Edit
                                </a>


                                <a
                                    href="classroom-delete.php?id=<?php
                                        echo (int)$row["classroom_id"];
                                    ?>"
                                    class="btn btn-danger"
                                    onclick="
                                        return confirm(
                                            'WARNING: This will permanently delete this resource. Continue?'
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

                        <td colspan="6">
                            No classrooms or labs found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>