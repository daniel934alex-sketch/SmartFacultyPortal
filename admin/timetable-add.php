<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Timetable";

$error = "";


/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/

// Periods
$periods = $conn->query("
    SELECT
        period_id,
        period_number,
        start_time,
        end_time
    FROM periods
    ORDER BY period_number
");


// Sections
$sections = $conn->query("
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_name
");


// Subjects
$subjects = $conn->query("
    SELECT
        subject_id,
        subject_code,
        subject_name,
        subject_type,
        weekly_periods,
        continuous_periods
    FROM subjects
    WHERE subject_type != 'PROJECT'
    ORDER BY subject_code
");


// Faculty
$faculty = $conn->query("
    SELECT
        faculty_id,
        faculty_code,
        faculty_name
    FROM faculty
    ORDER BY faculty_code
");


// Classrooms and Labs
$classrooms = $conn->query("
    SELECT
        classroom_id,
        room_number,
        room_type,
        capacity,
        status
    FROM classrooms
    WHERE status = 'AVAILABLE'
    ORDER BY classroom_id
");


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $day = strtoupper(
        trim($_POST["day"] ?? "")
    );

    $period_id = (int)(
        $_POST["period_id"] ?? 0
    );

    $section_id = (int)(
        $_POST["section_id"] ?? 0
    );

    $subject_id = (int)(
        $_POST["subject_id"] ?? 0
    );

    $faculty_id = (int)(
        $_POST["faculty_id"] ?? 0
    );

    $classroom_id = (int)(
        $_POST["classroom_id"] ?? 0
    );

    $status = strtoupper(
        trim($_POST["status"] ?? "DRAFT")
    );


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    $valid_days = [
        "MONDAY",
        "TUESDAY",
        "WEDNESDAY",
        "THURSDAY",
        "FRIDAY",
        "SATURDAY"
    ];


    if (!in_array($day, $valid_days, true)) {

        $error = "Invalid day.";

    } elseif ($period_id <= 0) {

        $error = "Please select a period.";

    } elseif ($section_id <= 0) {

        $error = "Please select a section.";

    } elseif ($subject_id <= 0) {

        $error = "Please select a subject.";

    } elseif ($faculty_id <= 0) {

        $error = "Please select faculty.";

    } elseif ($classroom_id <= 0) {

        $error = "Please select classroom or lab.";

    } elseif (
        !in_array(
            $status,
            ["DRAFT", "PUBLISHED"],
            true
        )
    ) {

        $error = "Invalid timetable status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET SUBJECT DETAILS
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                subject_id,
                subject_code,
                subject_name,
                subject_type,
                weekly_periods,
                continuous_periods
            FROM subjects
            WHERE subject_id = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "i",
            $subject_id
        );

        $stmt->execute();

        $subject_result = $stmt->get_result();

        if ($subject_result->num_rows === 0) {

            $error = "Subject not found.";

            $stmt->close();

        } else {

            $subject = $subject_result->fetch_assoc();

            $stmt->close();


            $continuous_periods =
                (int)$subject["continuous_periods"];

            $weekly_periods =
                (int)$subject["weekly_periods"];


            /*
            |--------------------------------------------------------------------------
            | GET STARTING PERIOD NUMBER
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT period_number
                FROM periods
                WHERE period_id = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "i",
                $period_id
            );

            $stmt->execute();

            $period_result = $stmt->get_result();

            if ($period_result->num_rows === 0) {

                $error = "Period not found.";

                $stmt->close();

            } else {

                $period_data =
                    $period_result->fetch_assoc();

                $stmt->close();


                $start_period =
                    (int)$period_data["period_number"];


                /*
                |--------------------------------------------------------------------------
                | SATURDAY RULE
                |--------------------------------------------------------------------------
                |
                | Saturday is reserved for EE Project.
                | Therefore normal theory/lab subjects
                | cannot be scheduled on Saturday.
                |
                */

                if ($day === "SATURDAY") {

                    $error =
                        "Saturday is reserved for EE Project.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDATE CONTINUOUS BLOCK
                    |--------------------------------------------------------------------------
                    */

                    $block_end =
                        $start_period +
                        $continuous_periods -
                        1;


                    /*
                    | A period block cannot go beyond P9.
                    */

                    if ($block_end > 9) {

                        $error =
                            $subject["subject_code"] .
                            " requires " .
                            $continuous_periods .
                            " continuous periods, but the selected starting period is too late.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | BREAK / LUNCH VALIDATION
                        |--------------------------------------------------------------------------
                        |
                        | Valid blocks:
                        |
                        | P1-P3
                        | P4-P6
                        | P7-P9
                        |
                        */

                        $valid_block = false;


                        if (
                            $start_period >= 1 &&
                            $block_end <= 3
                        ) {

                            $valid_block = true;

                        } elseif (
                            $start_period >= 4 &&
                            $block_end <= 6
                        ) {

                            $valid_block = true;

                        } elseif (
                            $start_period >= 7 &&
                            $block_end <= 9
                        ) {

                            $valid_block = true;
                        }


                        if (!$valid_block) {

                            $error =
                                "The selected " .
                                $subject["subject_code"] .
                                " requires " .
                                $continuous_periods .
                                " continuous periods without crossing a break or lunch.";

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | GET ALL PERIOD IDS IN BLOCK
                            |--------------------------------------------------------------------------
                            */

                            $block_period_ids = [];

                            $sql = "
                                SELECT
                                    period_id,
                                    period_number
                                FROM periods
                                WHERE period_number BETWEEN ? AND ?
                                ORDER BY period_number
                            ";

                            $stmt = $conn->prepare($sql);

                            $stmt->bind_param(
                                "ii",
                                $start_period,
                                $block_end
                            );

                            $stmt->execute();

                            $block_result =
                                $stmt->get_result();


                            while (
                                $period_row =
                                $block_result->fetch_assoc()
                            ) {

                                $block_period_ids[
                                    (int)$period_row["period_number"]
                                ] =
                                    (int)$period_row["period_id"];
                            }

                            $stmt->close();


                            /*
                            |--------------------------------------------------------------------------
                            | VERIFY ALL PERIODS EXIST
                            |--------------------------------------------------------------------------
                            */

                            if (
                                count($block_period_ids)
                                !==
                                $continuous_periods
                            ) {

                                $error =
                                    "Required continuous periods are not available.";

                            } else {


                                /*
                                |--------------------------------------------------------------------------
                                | WEEKLY PERIOD LIMIT
                                |--------------------------------------------------------------------------
                                */

                                $sql = "
                                    SELECT COUNT(*) AS total
                                    FROM timetable
                                    WHERE section_id = ?
                                    AND subject_id = ?
                                ";

                                $stmt = $conn->prepare($sql);

                                $stmt->bind_param(
                                    "ii",
                                    $section_id,
                                    $subject_id
                                );

                                $stmt->execute();

                                $count_result =
                                    $stmt->get_result();

                                $count_row =
                                    $count_result->fetch_assoc();

                                $existing_periods =
                                    (int)$count_row["total"];

                                $stmt->close();


                                if (
                                    $existing_periods +
                                    $continuous_periods
                                    >
                                    $weekly_periods
                                ) {

                                    $error =
                                        "Weekly limit exceeded for " .
                                        $subject["subject_code"] .
                                        ". Maximum allowed: " .
                                        $weekly_periods .
                                        " periods.";

                                } else {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | CHECK ALL PERIODS FOR CONFLICTS
                                    |--------------------------------------------------------------------------
                                    */

                                    foreach (
                                        $block_period_ids
                                        as $period_number =>
                                        $current_period_id
                                    ) {


                                        /*
                                        | SECTION CONFLICT
                                        */

                                        $sql = "
                                            SELECT timetable_id
                                            FROM timetable
                                            WHERE day = ?
                                            AND period_id = ?
                                            AND section_id = ?
                                        ";

                                        $stmt =
                                            $conn->prepare($sql);

                                        $stmt->bind_param(
                                            "sii",
                                            $day,
                                            $current_period_id,
                                            $section_id
                                        );

                                        $stmt->execute();

                                        $conflict_result =
                                            $stmt->get_result();


                                        if (
                                            $conflict_result->num_rows
                                            > 0
                                        ) {

                                            $error =
                                                "Section already has a class at P" .
                                                $period_number .
                                                ".";

                                            $stmt->close();

                                            break;
                                        }

                                        $stmt->close();


                                        /*
                                        | FACULTY CONFLICT
                                        */

                                        $sql = "
                                            SELECT timetable_id
                                            FROM timetable
                                            WHERE day = ?
                                            AND period_id = ?
                                            AND faculty_id = ?
                                        ";

                                        $stmt =
                                            $conn->prepare($sql);

                                        $stmt->bind_param(
                                            "sii",
                                            $day,
                                            $current_period_id,
                                            $faculty_id
                                        );

                                        $stmt->execute();

                                        $conflict_result =
                                            $stmt->get_result();


                                        if (
                                            $conflict_result->num_rows
                                            > 0
                                        ) {

                                            $error =
                                                "Faculty already has a class at P" .
                                                $period_number .
                                                ".";

                                            $stmt->close();

                                            break;
                                        }

                                        $stmt->close();


                                        /*
                                        | CLASSROOM / LAB CONFLICT
                                        */

                                        $sql = "
                                            SELECT timetable_id
                                            FROM timetable
                                            WHERE day = ?
                                            AND period_id = ?
                                            AND classroom_id = ?
                                        ";

                                        $stmt =
                                            $conn->prepare($sql);

                                        $stmt->bind_param(
                                            "sii",
                                            $day,
                                            $current_period_id,
                                            $classroom_id
                                        );

                                        $stmt->execute();

                                        $conflict_result =
                                            $stmt->get_result();


                                        if (
                                            $conflict_result->num_rows
                                            > 0
                                        ) {

                                            $error =
                                                "Classroom / Lab is already occupied at P" .
                                                $period_number .
                                                ".";

                                            $stmt->close();

                                            break;
                                        }

                                        $stmt->close();
                                    }


                                    /*
                                    |--------------------------------------------------------------------------
                                    | INSERT BLOCK
                                    |--------------------------------------------------------------------------
                                    */

                                    if ($error === "") {

                                        $conn->begin_transaction();

                                        try {

                                            $sql = "
                                                INSERT INTO timetable
                                                (
                                                    day,
                                                    period_id,
                                                    section_id,
                                                    subject_id,
                                                    faculty_id,
                                                    classroom_id,
                                                    status
                                                )
                                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                            ";

                                            $stmt =
                                                $conn->prepare($sql);


                                            foreach (
                                                $block_period_ids
                                                as $current_period_id
                                            ) {

                                                $stmt->bind_param(
                                                    "siiiiis",
                                                    $day,
                                                    $current_period_id,
                                                    $section_id,
                                                    $subject_id,
                                                    $faculty_id,
                                                    $classroom_id,
                                                    $status
                                                );

                                                if (
                                                    !$stmt->execute()
                                                ) {

                                                    throw new Exception(
                                                        "Failed to insert timetable block."
                                                    );
                                                }
                                            }


                                            $stmt->close();

                                            $conn->commit();


                                            header(
                                                "Location: timetable.php?success=added"
                                            );

                                            exit;

                                        } catch (
                                            Exception $e
                                        ) {

                                            $conn->rollback();

                                            if (
                                                isset($stmt)
                                            ) {

                                                $stmt->close();
                                            }

                                            $error =
                                                $e->getMessage();
                                        }
                                    }
                                }
                            }
                        }
                    }
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

            <h1>Add Timetable Entry</h1>

            <p>
                Schedule theory and laboratory classes.
            </p>

        </div>

        <a
            href="timetable.php"
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


            <!-- DAY -->

            <div class="form-group">

                <label>
                    Day
                </label>

                <select
                    name="day"
                    required
                >

                    <option value="">
                        Select Day
                    </option>

                    <option value="MONDAY">
                        Monday
                    </option>

                    <option value="TUESDAY">
                        Tuesday
                    </option>

                    <option value="WEDNESDAY">
                        Wednesday
                    </option>

                    <option value="THURSDAY">
                        Thursday
                    </option>

                    <option value="FRIDAY">
                        Friday
                    </option>

                    <option value="SATURDAY">
                        Saturday - EE Project
                    </option>

                </select>

            </div>


            <!-- PERIOD -->

            <div class="form-group">

                <label>
                    Starting Period
                </label>

                <select
                    name="period_id"
                    required
                >

                    <option value="">
                        Select Starting Period
                    </option>

                    <?php while (
                        $p = $periods->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$p["period_id"];
                            ?>"
                        >

                            P<?php
                            echo (int)$p["period_number"];
                            ?>

                            -
                            <?php
                            echo htmlspecialchars(
                                substr(
                                    $p["start_time"],
                                    0,
                                    5
                                )
                            );
                            ?>

                            to

                            <?php
                            echo htmlspecialchars(
                                substr(
                                    $p["end_time"],
                                    0,
                                    5
                                )
                            );
                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <small>
                    For a 3-period lab, select P1, P4 or P7.
                </small>

            </div>


            <!-- SECTION -->

            <div class="form-group">

                <label>
                    Section
                </label>

                <select
                    name="section_id"
                    required
                >

                    <option value="">
                        Select Section
                    </option>

                    <?php while (
                        $s = $sections->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$s["section_id"];
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $s["section_name"]
                            );
                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- SUBJECT -->

            <div class="form-group">

                <label>
                    Subject
                </label>

                <select
                    name="subject_id"
                    required
                >

                    <option value="">
                        Select Subject
                    </option>

                    <?php while (
                        $s = $subjects->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$s["subject_id"];
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $s["subject_code"]
                            );
                            ?>

                            -
                            <?php
                            echo htmlspecialchars(
                                $s["subject_name"]
                            );
                            ?>

                            (
                            <?php
                            echo htmlspecialchars(
                                $s["subject_type"]
                            );
                            ?>

                            /
                            <?php
                            echo (int)$s[
                                "continuous_periods"
                            ];
                            ?>
                            consecutive)

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- FACULTY -->

            <div class="form-group">

                <label>
                    Faculty
                </label>

                <select
                    name="faculty_id"
                    required
                >

                    <option value="">
                        Select Faculty
                    </option>

                    <?php while (
                        $f = $faculty->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$f["faculty_id"];
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $f["faculty_code"]
                            );
                            ?>

                            -
                            <?php
                            echo htmlspecialchars(
                                $f["faculty_name"]
                            );
                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- CLASSROOM -->

            <div class="form-group">

                <label>
                    Classroom / Lab
                </label>

                <select
                    name="classroom_id"
                    required
                >

                    <option value="">
                        Select Classroom / Lab
                    </option>

                    <?php while (
                        $c = $classrooms->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$c["classroom_id"];
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $c["room_number"]
                            );
                            ?>

                            -
                            <?php
                            echo htmlspecialchars(
                                $c["room_type"]
                            );
                            ?>

                            (Capacity:
                            <?php
                            echo (int)$c["capacity"];
                            ?>
                            )

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label>
                    Status
                </label>

                <select
                    name="status"
                >

                    <option value="DRAFT">
                        Draft
                    </option>

                    <option value="PUBLISHED">
                        Published
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Timetable
            </button>


            <a
                href="timetable.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>