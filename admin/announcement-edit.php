
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Announcement";


/* ==========================================
   VALIDATE ANNOUNCEMENT ID
   ========================================== */

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


/* ==========================================
   LOAD ANNOUNCEMENT
   ========================================== */

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


/* ==========================================
   UPDATE ANNOUNCEMENT
   ========================================== */

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

                $error = "Failed to update announcement.";
                $stmt->close();
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ===============================================
   EDIT ANNOUNCEMENT - PREMIUM DARK HOD THEME
   PAGE-SPECIFIC CSS
   =============================================== */

main.announcement-edit-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.announcement-edit-page *,
.announcement-edit-page *::before,
.announcement-edit-page *::after {
    box-sizing: border-box;
}

.announcement-edit-page .announcement-wrap {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
}

/* HEADER */

.announcement-edit-page .announcement-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;

    padding: 28px 32px;
    margin-bottom: 25px;

    border: 1px solid #51478c;
    border-radius: 18px;

    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 60%,
        #574898
    );
}

.announcement-edit-page .announcement-header h1 {
    margin: 0 0 9px;
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.25;
}

.announcement-edit-page .announcement-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* BACK BUTTON */

.announcement-edit-page .announcement-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 44px;
    padding: 11px 20px;

    border: 1px solid #9183bd;
    border-radius: 10px;

    background: rgba(255,255,255,0.09);
    color: #ffffff;

    text-decoration: none;
    font-size: 13px;
    font-weight: 750;
}

.announcement-edit-page .announcement-back:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM PANEL */

.announcement-edit-page .announcement-card {
    width: 100%;
    padding: 35px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;
    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

.announcement-edit-page .announcement-form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* FORM FIELDS */

.announcement-edit-page .announcement-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.announcement-edit-page .announcement-field label {
    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUT AND TEXTAREA */

.announcement-edit-page .announcement-field input,
.announcement-edit-page .announcement-field textarea {
    display: block;
    width: 100%;
    min-width: 0;

    padding: 14px 16px;

    border: 1px solid #3a4962;
    border-radius: 10px;

    outline: none;
    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 14px;
    line-height: 1.6;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.announcement-edit-page .announcement-field input {
    min-height: 51px;
}

.announcement-edit-page .announcement-field textarea {
    min-height: 210px;
    resize: vertical;
}

.announcement-edit-page .announcement-field input:focus,
.announcement-edit-page .announcement-field textarea:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

/* BUTTONS */

.announcement-edit-page .announcement-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;
    padding-top: 5px;
}

.announcement-edit-page .announcement-submit,
.announcement-edit-page .announcement-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 46px;
    padding: 12px 23px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;

    cursor: pointer;
}

.announcement-edit-page .announcement-submit {
    border: none;
    background: linear-gradient(115deg, #b8a6ff, #947cfa);
    color: #191330;
}

.announcement-edit-page .announcement-submit:hover {
    filter: brightness(1.1);
}

.announcement-edit-page .announcement-cancel {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.announcement-edit-page .announcement-cancel:hover {
    background: #34445f;
}

/* ERROR PANEL */

.announcement-edit-page .announcement-error {
    margin-bottom: 22px;
    padding: 15px 18px;

    border: 1px solid #915067;
    border-radius: 11px;

    background: #482a38;
    color: #ffd1da;

    font-size: 13px;
    line-height: 1.7;
}

/* MOBILE */

@media (max-width: 760px) {

    .announcement-edit-page .announcement-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .announcement-edit-page .announcement-header h1 {
        font-size: 25px;
    }

    .announcement-edit-page .announcement-card {
        padding: 23px 19px;
    }

    .announcement-edit-page .announcement-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .announcement-edit-page .announcement-submit,
    .announcement-edit-page .announcement-cancel {
        width: 100%;
    }
}
</style>

<main class="announcement-edit-page">

    <div class="announcement-wrap">

        <!-- PAGE HEADER -->

        <div class="announcement-header">

            <div>

                <h1>Edit Announcement</h1>

                <p>
                    Update the announcement details.
                </p>

            </div>

            <a
                href="announcements.php"
                class="announcement-back"
            >
                ← Back to Announcements
            </a>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($error !== ""): ?>

            <div
                class="announcement-error"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <!-- EDIT ANNOUNCEMENT FORM -->

        <div class="announcement-card">

            <form
                method="POST"
                action=""
                class="announcement-form"
            >

                <!-- TITLE -->

                <div class="announcement-field">

                    <label for="title">
                        Announcement Title
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $title,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </div>


                <!-- MESSAGE -->

                <div class="announcement-field">

                    <label for="message">
                        Announcement Message
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        rows="8"
                        required
                    ><?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?></textarea>

                </div>


                <!-- BUTTONS -->

                <div class="announcement-actions">

                    <button
                        type="submit"
                        class="announcement-submit"
                    >
                        Update Announcement
                    </button>

                    <a
                        href="announcements.php"
                        class="announcement-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
