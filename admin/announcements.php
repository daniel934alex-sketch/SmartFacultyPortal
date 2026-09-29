<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Announcements";

$sql = "
    SELECT
        a.announcement_id,
        a.title,
        a.message,
        a.created_at,
        u.username AS created_by_username
    FROM announcements a
    INNER JOIN users u
        ON a.created_by = u.user_id
    ORDER BY a.created_at DESC
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Announcements</h1>

            <p>
                Create and manage announcements for students and faculty.
            </p>
        </div>

        <a
            href="announcement-add.php"
            class="btn btn-primary"
        >
            + Add Announcement
        </a>

    </div>


    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="alert alert-success">
                Announcement created successfully.
            </div>

        <?php elseif ($_GET["success"] === "updated"): ?>

            <div class="alert alert-success">
                Announcement updated successfully.
            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="alert alert-success">
                Announcement deleted successfully.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-error">

            <?php

            switch ($_GET["error"]) {

                case "invalid_request":
                    echo "Invalid request.";
                    break;

                case "not_found":
                    echo "Announcement not found.";
                    break;

                case "delete_failed":
                    echo "Failed to delete announcement.";
                    break;

                default:
                    echo "Something went wrong.";
                    break;
            }

            ?>

        </div>

    <?php endif; ?>


    <div class="table-card">

        <div class="table-responsive">

            <table>

                <thead>

                    <tr>

                        <th>Title</th>

                        <th>Message</th>

                        <th>Created By</th>

                        <th>Created At</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        $result &&
                        $result->num_rows > 0
                    ): ?>

                        <?php while (
                            $row = $result->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $row["title"]
                                        );
                                        ?>
                                    </strong>
                                </td>


                                <td>

                                    <?php

                                    $message =
                                        $row["message"];

                                    if (
                                        strlen($message) > 100
                                    ) {

                                        echo htmlspecialchars(
                                            substr(
                                                $message,
                                                0,
                                                100
                                            )
                                        );

                                        echo "...";

                                    } else {

                                        echo htmlspecialchars(
                                            $message
                                        );
                                    }

                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row[
                                            "created_by_username"
                                        ]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        date(
                                            "d-m-Y h:i A",
                                            strtotime(
                                                $row["created_at"]
                                            )
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <a
                                        href="announcement-edit.php?id=<?php echo (int)$row["announcement_id"]; ?>"
                                        class="btn btn-warning"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="announcement-delete.php?id=<?php echo (int)$row["announcement_id"]; ?>"
                                        class="btn btn-danger"
                                        onclick="
                                            return confirm(
                                                'Are you sure you want to delete this announcement?'
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

                            <td
                                colspan="5"
                                style="text-align:center;"
                            >
                                No announcements found.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>