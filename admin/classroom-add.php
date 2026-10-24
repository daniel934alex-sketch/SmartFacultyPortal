
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Classroom / Lab";

$error = "";

$room_number = "";
$floor = "";
$room_type = "";
$capacity = 64;
$status = "AVAILABLE";


/* =========================================
   FORM SUBMISSION
   ========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $room_number = trim(
        $_POST["room_number"] ?? ""
    );

    $floor = trim(
        $_POST["floor"] ?? ""
    );

    $room_type = strtoupper(
        trim($_POST["room_type"] ?? "")
    );

    $capacity = isset($_POST["capacity"])
        ? (int)$_POST["capacity"]
        : 64;

    $status = strtoupper(
        trim($_POST["status"] ?? "AVAILABLE")
    );


    /* VALIDATION */

    if (
        $room_number === "" ||
        $room_type === ""
    ) {

        $error =
            "Room number and resource type are required.";

    } elseif (
        !in_array(
            $room_type,
            ["CLASSROOM", "LAB", "PROJECT"],
            true
        )
    ) {

        $error = "Invalid resource type.";

    } elseif ($capacity <= 0) {

        $error = "Capacity must be greater than 0.";

    } elseif ($capacity > 64) {

        $error = "Capacity cannot be greater than 64.";

    } elseif (
        !in_array(
            $status,
            ["AVAILABLE", "UNAVAILABLE"],
            true
        )
    ) {

        $error = "Invalid status.";

    } else {

        /* CHECK DUPLICATE ROOM NUMBER */

        $sql = "
            SELECT classroom_id
            FROM classrooms
            WHERE room_number = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "s",
            $room_number
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Room number already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /* INSERT CLASSROOM / LAB */

            $sql = "
                INSERT INTO classrooms
                (
                    room_number,
                    floor,
                    room_type,
                    capacity,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {

                $error =
                    "Database error: " .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    "sssis",
                    $room_number,
                    $floor,
                    $room_type,
                    $capacity,
                    $status
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        "Location: classrooms.php?success=added"
                    );

                    exit;

                } else {

                    $error = "Failed to add resource.";

                    $stmt->close();
                }
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ============================================
   ADD CLASSROOM / LAB - PREMIUM DARK THEME
   STYLES ARE SCOPED TO THIS HOD PAGE
   ============================================ */

/* MAIN CONTENT */

main.classroom-add-page {
    min-height: 100vh;
    box-sizing: border-box;
    color: #edf1fb;
}

.classroom-add-page *,
.classroom-add-page *::before,
.classroom-add-page *::after {
    box-sizing: border-box;
}

.classroom-add-page .classroom-wrap {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
}

/* PAGE HEADER */

.classroom-add-page .classroom-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;

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

.classroom-add-page .classroom-header h1 {
    margin: 0 0 9px;
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.25;
}

.classroom-add-page .classroom-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* BACK BUTTON */

.classroom-add-page .classroom-back {
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
    white-space: nowrap;
}

.classroom-add-page .classroom-back:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM PANEL */

.classroom-add-page .classroom-card {
    width: 100%;
    padding: 34px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;

    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

/* TWO-COLUMN FORM */

.classroom-add-page .classroom-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 23px 24px;
}

/* FORM FIELDS */

.classroom-add-page .classroom-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.classroom-add-page .classroom-field label {
    display: block;
    margin-bottom: 10px;

    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUTS AND SELECTS */

.classroom-add-page .classroom-field input,
.classroom-add-page .classroom-field select {
    display: block;

    width: 100%;
    min-width: 0;
    min-height: 50px;
    padding: 11px 15px;

    border: 1px solid #3a4962;
    border-radius: 10px;

    outline: none;

    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 14px;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.classroom-add-page .classroom-field input::placeholder {
    color: #91a0b9;
}

.classroom-add-page .classroom-field input:focus,
.classroom-add-page .classroom-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

.classroom-add-page .classroom-field select option {
    background: #10192a;
    color: #ffffff;
}

/* CAPACITY HELPER */

.classroom-add-page .classroom-field small {
    display: block;
    margin-top: 9px;

    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.5;
}

/* BUTTON ROW */

.classroom-add-page .classroom-actions {
    grid-column: 1 / -1;

    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;

    padding-top: 7px;
}

.classroom-add-page .classroom-submit,
.classroom-add-page .classroom-cancel {
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

/* ADD RESOURCE BUTTON */

.classroom-add-page .classroom-submit {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.classroom-add-page .classroom-submit:hover {
    filter: brightness(1.1);
}

/* CANCEL BUTTON */

.classroom-add-page .classroom-cancel {
    border: 1px solid #4b5a74;

    background: #28364e;
    color: #e1e9f7;
}

.classroom-add-page .classroom-cancel:hover {
    background: #34445f;
}

/* ERROR ALERT */

.classroom-add-page .classroom-error {
    margin-bottom: 22px;
    padding: 15px 18px;

    border: 1px solid #915067;
    border-radius: 11px;

    background: #482a38;
    color: #ffd1da;

    font-size: 13px;
    line-height: 1.7;
}

/* RESPONSIVE */

@media (max-width: 760px) {
    .classroom-add-page .classroom-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .classroom-add-page .classroom-header h1 {
        font-size: 25px;
    }

    .classroom-add-page .classroom-card {
        padding: 23px 19px;
    }

    .classroom-add-page .classroom-form {
        grid-template-columns: 1fr;
        gap: 19px;
    }

    .classroom-add-page .classroom-actions {
        grid-column: 1;
    }

    .classroom-add-page .classroom-back {
        width: 100%;
    }
}
</style>

<main class="classroom-add-page">

    <div class="classroom-wrap">

        <!-- HEADER -->

        <div class="classroom-header">

            <div>
                <h1>Add Classroom / Lab</h1>

                <p>
                    Add a classroom or laboratory.
                </p>
            </div>

            <a
                href="classrooms.php"
                class="classroom-back"
            >
                ← Back to Classrooms
            </a>

        </div>


        <!-- VALIDATION ERROR -->

        <?php if ($error !== ""): ?>

            <div class="classroom-error" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <!-- ADD CLASSROOM FORM -->

        <div class="classroom-card">

            <form
                method="POST"
                action=""
                class="classroom-form"
            >

                <!-- ROOM NUMBER -->

                <div class="classroom-field">

                    <label for="room_number">
                        Room / Lab Number
                    </label>

                    <input
                        type="text"
                        id="room_number"
                        name="room_number"
                        maxlength="30"
                        placeholder="Example: 36 or LAB-1"
                        value="<?= htmlspecialchars(
                            $room_number,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <!-- FLOOR -->

                <div class="classroom-field">

                    <label for="floor">
                        Floor / Location
                    </label>

                    <input
                        type="text"
                        id="floor"
                        name="floor"
                        maxlength="50"
                        placeholder="Example: 1st Floor"
                        value="<?= htmlspecialchars(
                            $floor,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <!-- RESOURCE TYPE -->

                <div class="classroom-field">

                    <label for="room_type">
                        Resource Type
                    </label>

                    <select
                        id="room_type"
                        name="room_type"
                        required
                    >

                        <option value="">
                            Select Type
                        </option>

                        <option
                            value="CLASSROOM"
                            <?= $room_type === "CLASSROOM"
                                ? "selected"
                                : "" ?>
                        >
                            Classroom
                        </option>

                        <option
                            value="LAB"
                            <?= $room_type === "LAB"
                                ? "selected"
                                : "" ?>
                        >
                            Laboratory
                        </option>

                        <option
                            value="PROJECT"
                            <?= $room_type === "PROJECT"
                                ? "selected"
                                : "" ?>
                        >
                            Project Room
                        </option>

                    </select>

                </div>


                <!-- CAPACITY -->

                <div class="classroom-field">

                    <label for="capacity">
                        Capacity
                    </label>

                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        max="64"
                        value="<?= (int)$capacity ?>"
                        required
                    >

                    <small>
                        Maximum capacity is 64.
                    </small>

                </div>


                <!-- STATUS -->

                <div class="classroom-field">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="AVAILABLE"
                            <?= $status === "AVAILABLE"
                                ? "selected"
                                : "" ?>
                        >
                            Available
                        </option>

                        <option
                            value="UNAVAILABLE"
                            <?= $status === "UNAVAILABLE"
                                ? "selected"
                                : "" ?>
                        >
                            Unavailable
                        </option>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="classroom-actions">

                    <button
                        type="submit"
                        class="classroom-submit"
                    >
                        Add Resource
                    </button>

                    <a
                        href="classrooms.php"
                        class="classroom-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
