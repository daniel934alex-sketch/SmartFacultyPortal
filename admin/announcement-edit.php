<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Announcement";

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: announcements.php?error=invalid_request"
    );

    exit;
}

$announcement_id = (int)$_GET["id"];


$title = "";
$message = "";

$error = "";


/*
|--------------------------------------------------------------------------
| Load Announcement
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        announcement_id,
        title,
        message
    FROM announcements
    WHERE announcement_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Database error.");
}

$stmt->bind_param(
    "i",
    $announcement_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: announcements.php?error=not_found"
    );

    exit;
}


$row = $result->fetch_assoc();

$title = $row["title"];
$message = $row["message"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Update Announcement
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");


    if ($title === "") {

        $error =
            "Please enter the announcement title.";

    } elseif (strlen($title) > 150) {

        $error =
            "Title must not exceed 150 characters.";

    } elseif ($message === "") {

        $error =
            "Please enter the announcement message.";

    } else {

        $sql = "
            UPDATE announcements

            SET
                title = ?,
                message = ?

            WHERE announcement_id = ?
        ";

        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param(
                "ssi",
                $title,
                $message,
                $announcement_id
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: announcements.php?success=updated"
                );

                exit;

            } else {

                $error =
                    "Failed to update announcement.";

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

            <h1>Edit Announcement</h1>

            <p>
                Update the announcement details.
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
                    Update Announcement
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