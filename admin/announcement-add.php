<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Announcement";

$title = "";
$message = "";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");


    if ($title === "") {

        $error = "Please enter the announcement title.";

    } elseif (strlen($title) > 150) {

        $error = "Title must not exceed 150 characters.";

    } elseif ($message === "") {

        $error = "Please enter the announcement message.";

    } else {

        $created_by = $_SESSION["user_id"];


        $sql = "
            INSERT INTO announcements
            (
                title,
                message,
                created_by
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ";

        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param(
                "ssi",
                $title,
                $message,
                $created_by
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: announcements.php?success=added"
                );

                exit;

            } else {

                $error =
                    "Failed to create announcement.";

                $stmt->close();
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>

            <h1>Add Announcement</h1>

            <p>
                Create a new announcement.
            </p>

        </div>


        <a
            href="announcements.php"
            class="btn btn-secondary"
        >
            Back to Announcements
        </a>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="form-card">

        <form
            method="POST"
            action=""
        >

            <div class="form-group">

                <label for="title">
                    Announcement Title
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    value="<?php
                        echo htmlspecialchars($title);
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="message">
                    Announcement Message
                </label>

                <textarea
                    id="message"
                    name="message"
                    rows="8"
                    required
                ><?php
                    echo htmlspecialchars($message);
                ?></textarea>

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Create Announcement
                </button>


                <a
                    href="announcements.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>