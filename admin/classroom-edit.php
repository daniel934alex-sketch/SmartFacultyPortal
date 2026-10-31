<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Classroom / Lab";

$error = "";


/* ==========================================
   VALIDATE CLASSROOM ID
   ========================================== */

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: classrooms.php?error=invalid_request"
    );

    exit;
}

$classroom_id = (int)$_GET["id"];


/* ==========================================
   GET EXISTING CLASSROOM / LAB
   ========================================== */

$sql = "
    SELECT
        classroom_id,
        room_number,
        floor,
        room_type,
        capacity,
        status
    FROM classrooms
    WHERE classroom_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $classroom_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: classrooms.php?error=not_found"
    );

    exit;
}

$room = $result->fetch_assoc();

$stmt->close();


/* ==========================================
   UPDATE CLASSROOM / LAB
   ========================================== */

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

        $error =
            "Capacity must be greater than 0.";

    } elseif ($capacity > 64) {

        $error =
            "Capacity cannot be greater than 64.";

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
            AND classroom_id != ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "si",
            $room_number,
            $classroom_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Room number already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /* UPDATE RESOURCE */

            $sql = "
                UPDATE classrooms
                SET
                    room_number = ?,
                    floor = ?,
                    room_type = ?,
                    capacity = ?,
                    status = ?
                WHERE classroom_id = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "sssisi",
                $room_number,
                $floor,
                $room_type,
                $capacity,
                $status,
                $classroom_id
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: classrooms.php?success=updated"
                );

                exit;

            } else {

                $error = "Failed to update resource.";

                $stmt->close();
            }
        }
    }


    /* KEEP ENTERED VALUES ON ERROR */

    $room["room_number"] = $room_number;
    $room["floor"] = $floor;
    $room["room_type"] = $room_type;
    $room["capacity"] = $capacity;
    $room["status"] = $status;
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ============================================
   EDIT CLASSROOM / LAB
   PREMIUM DARK HOD THEME
   PAGE-SPECIFIC STYLING ONLY
   ============================================ */

/* MAIN CONTENT */

main.classroom-edit-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.classroom-edit-page *,
.classroom-edit-page *::before,
.classroom-edit-page *::after {
    box-sizing: border-box;
}

/* MAIN WRAPPER */

.classroom-edit-page .classroom-edit-wrap {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
}

/* PAGE HEADER */

.classroom-edit-page .classroom-edit-header {
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

.classroom-edit-page .classroom-edit-header h1 {
    margin: 0 0 9px;

    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.25;
}

.classroom-edit-page .classroom-edit-header p {
    margin: 0;

    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* BACK BUTTON */

.classroom-edit-page .classroom-edit-back {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    min-height: 44px;
    padding: 11px 20px;

    border: 1px solid #9183bd;
    border-radius: 10px;

    background: rgba(255,255,255,0.09);
    color: #ffffff;

    font-family: inherit;
    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
    white-space: nowrap;
}

.classroom-edit-page .classroom-edit-back:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM CARD */

.classroom-edit-page .classroom-edit-card {
    width: 100%;
    padding: 34px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;

    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

/* TWO-COLUMN FORM */

.classroom-edit-page .classroom-edit-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 23px 24px;
}

/* FORM GROUPS */

.classroom-edit-page .classroom-edit-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.classroom-edit-page .classroom-edit-field label {
    display: block;
    margin-bottom: 10px;

    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUT FIELDS AND DROPDOWNS */

.classroom-edit-page .classroom-edit-field input,
.classroom-edit-page .classroom-edit-field select {
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

.classroom-edit-page .classroom-edit-field input:focus,
.classroom-edit-page .classroom-edit-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

.classroom-edit-page .classroom-edit-field select option {
    background: #10192a;
    color: #ffffff;
}

/* HELPER TEXT */

.classroom-edit-page .classroom-edit-field small {
    display: block;
    margin-top: 9px;

    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.5;
}

/* ACTION BUTTONS */

.classroom-edit-page .classroom-edit-actions {
    grid-column: 1 / -1;

    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;

    padding-top: 7px;
}

.classroom-edit-page .classroom-edit-submit,
.classroom-edit-page .classroom-edit-cancel {
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

/* UPDATE BUTTON */

.classroom-edit-page .classroom-edit-submit {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.classroom-edit-page .classroom-edit-submit:hover {
    filter: brightness(1.1);
}

/* CANCEL BUTTON */

.classroom-edit-page .classroom-edit-cancel {
    border: 1px solid #4b5a74;

    background: #28364e;
    color: #e1e9f7;
}

.classroom-edit-page .classroom-edit-cancel:hover {
    background: #34445f;
}

/* ERROR MESSAGE */

.classroom-edit-page .classroom-edit-error {
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

    .classroom-edit-page .classroom-edit-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .classroom-edit-page .classroom-edit-header h1 {
        font-size: 25px;
    }

    .classroom-edit-page .classroom-edit-card {
        padding: 23px 19px;
    }

    .classroom-edit-page .classroom-edit-form {
        grid-template-columns: 1fr;
        gap: 19px;
    }

    .classroom-edit-page .classroom-edit-actions {
        grid-column: 1;
    }

    .classroom-edit-page .classroom-edit-back {
        width: 100%;
    }
}
</style>

<main class="classroom-edit-page">

    <div class="classroom-edit-wrap">

        <!-- PAGE HEADER -->

        <div class="classroom-edit-header">

            <div>

                <h1>Edit Classroom / Lab</h1>

                <p>
                    Update resource information.
                </p>

            </div>

            <a
                href="classrooms.php"
                class="classroom-edit-back"
            >
                ← Back to Classrooms
            </a>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($error !== ""): ?>

            <div
                class="classroom-edit-error"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <!-- EDIT RESOURCE FORM -->

        <div class="classroom-edit-card">

            <form
                method="POST"
                action=""
                class="classroom-edit-form"
            >

                <!-- ROOM NUMBER -->

                <div class="classroom-edit-field">

                    <label for="room_number">
                        Room / Lab Number
                    </label>

                    <input
                        type="text"
                        id="room_number"
                        name="room_number"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $room["room_number"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >

                </div>


                <!-- FLOOR / LOCATION -->

                <div class="classroom-edit-field">

                    <label for="floor">
                        Floor / Location
                    </label>

                    <input
                        type="text"
                        id="floor"
                        name="floor"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $room["floor"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                </div>


                <!-- RESOURCE TYPE -->

                <div class="classroom-edit-field">

                    <label for="room_type">
                        Resource Type
                    </label>

                    <select
                        id="room_type"
                        name="room_type"
                        required
                    >

                        <option
                            value="CLASSROOM"
                            <?= $room["room_type"] === "CLASSROOM"
                                ? "selected"
                                : "" ?>
                        >
                            Classroom
                        </option>

                        <option
                            value="LAB"
                            <?= $room["room_type"] === "LAB"
                                ? "selected"
                                : "" ?>
                        >
                            Laboratory
                        </option>

                        <option
                            value="PROJECT"
                            <?= $room["room_type"] === "PROJECT"
                                ? "selected"
                                : "" ?>
                        >
                            Project Room
                        </option>

                    </select>

                </div>


                <!-- CAPACITY -->

                <div class="classroom-edit-field">

                    <label for="capacity">
                        Capacity
                    </label>

                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        max="64"
                        value="<?= (int)$room["capacity"] ?>"
                        required
                    >

                    <small>
                        Maximum capacity is 64.
                    </small>

                </div>


                <!-- STATUS -->

                <div class="classroom-edit-field">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="AVAILABLE"
                            <?= $room["status"] === "AVAILABLE"
                                ? "selected"
                                : "" ?>
                        >
                            Available
                        </option>

                        <option
                            value="UNAVAILABLE"
                            <?= $room["status"] === "UNAVAILABLE"
                                ? "selected"
                                : "" ?>
                        >
                            Unavailable
                        </option>

                    </select>

                </div>


                <!-- FORM ACTIONS -->

                <div class="classroom-edit-actions">

                    <button
                        type="submit"
                        class="classroom-edit-submit"
                    >
                        Update Resource
                    </button>

                    <a
                        href="classrooms.php"
                        class="classroom-edit-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
