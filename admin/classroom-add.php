<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Classroom / Lab";

$error = "";

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

        $error = "Capacity must be greater than 0.";

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
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "s",
            $room_number
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
             * Insert
             */

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

                    $error =
                        "Failed to add resource.";

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

<main>

    <div class="page-header">

        <div>

            <h1>Add Classroom / Lab</h1>

            <p>
                Add a classroom or laboratory.
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
                    placeholder="Example: 36 or LAB-1"
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
                    placeholder="Example: 1st Floor"
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

                    <option value="">
                        Select Type
                    </option>

                    <option value="CLASSROOM">
                        Classroom
                    </option>

                    <option value="LAB">
                        Laboratory
                    </option>

                    <option value="PROJECT">
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
                    value="64"
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

                    <option value="AVAILABLE">
                        Available
                    </option>

                    <option value="UNAVAILABLE">
                        Unavailable
                    </option>

                </select>

            </div>


            <!-- Buttons -->

            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Resource
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