<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Classroom / Lab";

$error = "";


/*
 * Validate ID
 */

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


/*
 * Get existing resource
 */

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


/*
 * Update
 */

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


    /*
     * Validation
     */

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

        /*
         * Check duplicate room number
         */

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

            $error =
                "Room number already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /*
             * Update
             */

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

                $error =
                    "Failed to update resource.";

                $stmt->close();
            }
        }
    }


    /*
     * Keep entered values
     */

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

<main>

    <div class="page-header">

        <div>

            <h1>Edit Classroom / Lab</h1>

            <p>
                Update resource information.
            </p>

        </div>

        <a
            href="classrooms.php"
            class="btn btn-secondary"
        >
            Back
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

        <form method="POST">


            <!-- Room Number -->

            <div class="form-group">

                <label for="room_number">
                    Room / Lab Number
                </label>

                <input
                    type="text"
                    id="room_number"
                    name="room_number"
                    maxlength="30"
                    value="<?php
                        echo htmlspecialchars(
                            $room["room_number"]
                        );
                    ?>"
                    required
                >

            </div>


            <!-- Floor -->

            <div class="form-group">

                <label for="floor">
                    Floor / Location
                </label>

                <input
                    type="text"
                    id="floor"
                    name="floor"
                    maxlength="50"
                    value="<?php
                        echo htmlspecialchars(
                            $room["floor"] ?? ""
                        );
                    ?>"
                >

            </div>


            <!-- Type -->

            <div class="form-group">

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
                        <?php
                        echo
                            $room["room_type"] === "CLASSROOM"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Classroom
                    </option>

                    <option
                        value="LAB"
                        <?php
                        echo
                            $room["room_type"] === "LAB"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Laboratory
                    </option>

                    <option
                        value="PROJECT"
                        <?php
                        echo
                            $room["room_type"] === "PROJECT"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Project Room
                    </option>

                </select>

            </div>


            <!-- Capacity -->

            <div class="form-group">

                <label for="capacity">
                    Capacity
                </label>

                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    min="1"
                    max="64"
                    value="<?php
                        echo (int)$room["capacity"];
                    ?>"
                    required
                >

                <small>
                    Maximum capacity is 64.
                </small>

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option
                        value="AVAILABLE"
                        <?php
                        echo
                            $room["status"] === "AVAILABLE"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Available
                    </option>

                    <option
                        value="UNAVAILABLE"
                        <?php
                        echo
                            $room["status"] === "UNAVAILABLE"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Unavailable
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Resource
            </button>

            <a
                href="classrooms.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>