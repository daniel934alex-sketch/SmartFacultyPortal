<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Timetable";

$error = "";


/*
|--------------------------------------------------------------------------
| VALIDATE ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: timetable.php?error=invalid_request");
    exit;
}

$timetable_id = (int)$_GET["id"];


/*
|--------------------------------------------------------------------------
| GET CURRENT TIMETABLE ENTRY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.section_id,
        t.subject_id,
        t.faculty_id,
        t.classroom_id,
        t.status,

        p.period_number,

        s.subject_code,
        s.subject_name,
        s.subject_type,
        s.weekly_periods,
        s.continuous_periods

    FROM timetable t

    INNER JOIN periods p
        ON t.period_id = p.period_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    WHERE t.timetable_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $timetable_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: timetable.php?error=not_found"
    );

    exit;
}


$timetable = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| STORE OLD BLOCK VALUES
|--------------------------------------------------------------------------
*/

$old_day =
    $timetable["day"];

$old_period_id =
    (int)$timetable["period_id"];

$old_period_number =
    (int)$timetable["period_number"];

$old_section_id =
    (int)$timetable["section_id"];

$old_subject_id =
    (int)$timetable["subject_id"];

$old_faculty_id =
    (int)$timetable["faculty_id"];

$old_classroom_id =
    (int)$timetable["classroom_id"];

$old_continuous_periods =
    (int)$timetable["continuous_periods"];


/*
|--------------------------------------------------------------------------
| OLD BLOCK END
|--------------------------------------------------------------------------
*/

$old_block_end =
    $old_period_number +
    $old_continuous_periods -
    1;


/*
|--------------------------------------------------------------------------
| LOAD PERIODS
|--------------------------------------------------------------------------
*/

$periods = $conn->query("
    SELECT
        period_id,
        period_number,
        start_time,
        end_time
    FROM periods
    ORDER BY period_number
");


/*
|--------------------------------------------------------------------------
| LOAD SECTIONS
|--------------------------------------------------------------------------
*/

$sections = $conn->query("
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_name
");


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| LOAD FACULTY
|--------------------------------------------------------------------------
*/

$faculty = $conn->query("
    SELECT
        faculty_id,
        faculty_code,
        faculty_name
    FROM faculty
    ORDER BY faculty_code
");


/*
|--------------------------------------------------------------------------
| LOAD CLASSROOMS / LABS
|--------------------------------------------------------------------------
*/

$classrooms = $conn->query("
    SELECT
        classroom_id,
        room_number,
        room_type,
        capacity,
        status
    FROM classrooms
    WHERE status = 'AVAILABLE'
       OR classroom_id = " . $old_classroom_id . "
    ORDER BY classroom_id
");


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | GET FORM VALUES
    |--------------------------------------------------------------------------
    */

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
    | VALID DAYS
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


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!in_array($day, $valid_days, true)) {

        $error = "Invalid day.";

    } elseif ($period_id <= 0) {

        $error = "Please select a starting period.";

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

    } elseif ($day === "SATURDAY") {

        $error =
            "Saturday is reserved for EE Project.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | GET SELECTED SUBJECT
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

        $subject_result =
            $stmt->get_result();


        if ($subject_result->num_rows === 0) {

            $error = "Subject not found.";

            $stmt->close();

        } else {

            $subject =
                $subject_result->fetch_assoc();

            $stmt->close();


            $continuous_periods =
                (int)$subject["continuous_periods"];

            $weekly_periods =
                (int)$subject["weekly_periods"];


            /*
            |--------------------------------------------------------------------------
            | GET STARTING PERIOD
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    period_id,
                    period_number
                FROM periods
                WHERE period_id = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "i",
                $period_id
            );

            $stmt->execute();

            $period_result =
                $stmt->get_result();


            if ($period_result->num_rows === 0) {

                $error =
                    "Selected period was not found.";

                $stmt->close();

            } else {

                $period_data =
                    $period_result->fetch_assoc();

                $stmt->close();


                $start_period =
                    (int)$period_data["period_number"];


                /*
                |--------------------------------------------------------------------------
                | NEW BLOCK END
                |--------------------------------------------------------------------------
                */

                $block_end =
                    $start_period +
                    $continuous_periods -
                    1;


                /*
                |--------------------------------------------------------------------------
                | P9 CHECK
                |--------------------------------------------------------------------------
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
                    | BREAK / LUNCH CHECK
                    |--------------------------------------------------------------------------
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
                            $subject["subject_code"] .
                            " requires " .
                            $continuous_periods .
                            " continuous periods without crossing a break or lunch.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | GET NEW BLOCK PERIOD IDS
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

                        $stmt =
                            $conn->prepare($sql);

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
                        | VERIFY PERIOD BLOCK
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
                            | WEEKLY PERIOD COUNT
                            |--------------------------------------------------------------------------
                            |
                            | TYPES:
                            |
                            | i = integer
                            | s = string
                            |
                            | Variables:
                            |
                            | 1 section_id       i
                            | 2 subject_id       i
                            | 3 old_day          s
                            | 4 old_section_id   i
                            | 5 old_subject_id   i
                            | 6 old_faculty_id   i
                            | 7 old_classroom_id i
                            | 8 old_period_number i
                            | 9 old_block_end    i
                            |
                            | Therefore:
                            | i i s i i i i i i
                            |
                            | "iisiiiiii"
                            |--------------------------------------------------------------------------
                            */

                            $sql = "
                                SELECT
                                    COUNT(*) AS total

                                FROM timetable t

                                INNER JOIN periods p
                                    ON t.period_id =
                                       p.period_id

                                WHERE t.section_id = ?
                                AND t.subject_id = ?

                                AND NOT (
                                    t.day = ?
                                    AND t.section_id = ?
                                    AND t.subject_id = ?
                                    AND t.faculty_id = ?
                                    AND t.classroom_id = ?

                                    AND p.period_number
                                        BETWEEN ?
                                        AND ?
                                )
                            ";

                            $stmt =
                                $conn->prepare($sql);

                            $stmt->bind_param(
                                "iisiiiiii",
                                $section_id,
                                $subject_id,
                                $old_day,
                                $old_section_id,
                                $old_subject_id,
                                $old_faculty_id,
                                $old_classroom_id,
                                $old_period_number,
                                $old_block_end
                            );

                            $stmt->execute();

                            $count_result =
                                $stmt->get_result();

                            $count_row =
                                $count_result->fetch_assoc();

                            $existing_periods =
                                (int)$count_row["total"];

                            $stmt->close();


                            /*
                            |--------------------------------------------------------------------------
                            | WEEKLY LIMIT
                            |--------------------------------------------------------------------------
                            */

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
                                | CONFLICT CHECKING
                                |--------------------------------------------------------------------------
                                */

                                foreach (
                                    $block_period_ids
                                    as $period_number =>
                                    $current_period_id
                                ) {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | SECTION CONFLICT
                                    |--------------------------------------------------------------------------
                                    */

                                    $sql = "
                                        SELECT
                                            t.timetable_id

                                        FROM timetable t

                                        INNER JOIN periods p
                                            ON t.period_id =
                                               p.period_id

                                        WHERE t.day = ?
                                        AND t.period_id = ?
                                        AND t.section_id = ?

                                        AND NOT (
                                            t.day = ?
                                            AND t.section_id = ?
                                            AND t.subject_id = ?
                                            AND t.faculty_id = ?
                                            AND t.classroom_id = ?

                                            AND p.period_number
                                                BETWEEN ?
                                                AND ?
                                        )
                                    ";

                                    $stmt =
                                        $conn->prepare($sql);

                                    $stmt->bind_param(
                                        "siisiiiiii",
                                        $day,
                                        $current_period_id,
                                        $section_id,
                                        $old_day,
                                        $old_section_id,
                                        $old_subject_id,
                                        $old_faculty_id,
                                        $old_classroom_id,
                                        $old_period_number,
                                        $old_block_end
                                    );

                                    $stmt->execute();

                                    $conflict =
                                        $stmt->get_result();


                                    if (
                                        $conflict->num_rows > 0
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
                                    |--------------------------------------------------------------------------
                                    | FACULTY CONFLICT
                                    |--------------------------------------------------------------------------
                                    */

                                    $sql = "
                                        SELECT
                                            t.timetable_id

                                        FROM timetable t

                                        INNER JOIN periods p
                                            ON t.period_id =
                                               p.period_id

                                        WHERE t.day = ?
                                        AND t.period_id = ?
                                        AND t.faculty_id = ?

                                        AND NOT (
                                            t.day = ?
                                            AND t.section_id = ?
                                            AND t.subject_id = ?
                                            AND t.faculty_id = ?
                                            AND t.classroom_id = ?

                                            AND p.period_number
                                                BETWEEN ?
                                                AND ?
                                        )
                                    ";

                                    $stmt =
                                        $conn->prepare($sql);

                                    $stmt->bind_param(
                                        "siisiiiiii",
                                        $day,
                                        $current_period_id,
                                        $faculty_id,
                                        $old_day,
                                        $old_section_id,
                                        $old_subject_id,
                                        $old_faculty_id,
                                        $old_classroom_id,
                                        $old_period_number,
                                        $old_block_end
                                    );

                                    $stmt->execute();

                                    $conflict =
                                        $stmt->get_result();


                                    if (
                                        $conflict->num_rows > 0
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
                                    |--------------------------------------------------------------------------
                                    | CLASSROOM / LAB CONFLICT
                                    |--------------------------------------------------------------------------
                                    */

                                    $sql = "
                                        SELECT
                                            t.timetable_id

                                        FROM timetable t

                                        INNER JOIN periods p
                                            ON t.period_id =
                                               p.period_id

                                        WHERE t.day = ?
                                        AND t.period_id = ?
                                        AND t.classroom_id = ?

                                        AND NOT (
                                            t.day = ?
                                            AND t.section_id = ?
                                            AND t.subject_id = ?
                                            AND t.faculty_id = ?
                                            AND t.classroom_id = ?

                                            AND p.period_number
                                                BETWEEN ?
                                                AND ?
                                        )
                                    ";

                                    $stmt =
                                        $conn->prepare($sql);

                                    $stmt->bind_param(
                                        "siisiiiiii",
                                        $day,
                                        $current_period_id,
                                        $classroom_id,
                                        $old_day,
                                        $old_section_id,
                                        $old_subject_id,
                                        $old_faculty_id,
                                        $old_classroom_id,
                                        $old_period_number,
                                        $old_block_end
                                    );

                                    $stmt->execute();

                                    $conflict =
                                        $stmt->get_result();


                                    if (
                                        $conflict->num_rows > 0
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
                                | REPLACE OLD BLOCK
                                |--------------------------------------------------------------------------
                                */

                                if ($error === "") {

                                    $conn->begin_transaction();

                                    try {


                                        /*
                                        |--------------------------------------------------------------------------
                                        | DELETE OLD BLOCK
                                        |--------------------------------------------------------------------------
                                        */

                                        $sql = "
                                            DELETE t
                                            FROM timetable t

                                            INNER JOIN periods p
                                                ON t.period_id =
                                                   p.period_id

                                            WHERE t.day = ?
                                            AND t.section_id = ?
                                            AND t.subject_id = ?
                                            AND t.faculty_id = ?
                                            AND t.classroom_id = ?

                                            AND p.period_number
                                                BETWEEN ?
                                                AND ?
                                        ";

                                        $stmt =
                                            $conn->prepare($sql);

                                        $stmt->bind_param(
                                            "siiiiii",
                                            $old_day,
                                            $old_section_id,
                                            $old_subject_id,
                                            $old_faculty_id,
                                            $old_classroom_id,
                                            $old_period_number,
                                            $old_block_end
                                        );

                                        if (
                                            !$stmt->execute()
                                        ) {

                                            throw new Exception(
                                                "Failed to remove old timetable block."
                                            );
                                        }

                                        $stmt->close();


                                        /*
                                        |--------------------------------------------------------------------------
                                        | INSERT NEW BLOCK
                                        |--------------------------------------------------------------------------
                                        */

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
                                            VALUES
                                            (?, ?, ?, ?, ?, ?, ?)
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
                                                    "Failed to create timetable block."
                                                );
                                            }
                                        }


                                        $stmt->close();


                                        /*
                                        |--------------------------------------------------------------------------
                                        | COMMIT
                                        |--------------------------------------------------------------------------
                                        */

                                        $conn->commit();


                                        header(
                                            "Location: timetable.php?success=updated"
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


    /*
    |--------------------------------------------------------------------------
    | PRESERVE VALUES
    |--------------------------------------------------------------------------
    */

    $timetable["day"] =
        $day;

    $timetable["period_id"] =
        $period_id;

    $timetable["section_id"] =
        $section_id;

    $timetable["subject_id"] =
        $subject_id;

    $timetable["faculty_id"] =
        $faculty_id;

    $timetable["classroom_id"] =
        $classroom_id;

    $timetable["status"] =
        $status;
}

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<style>
/* ==========================================
   PREMIUM DARK HOD TIMETABLE FORMS
   Page-specific styling
   ========================================== */

main.tt-dark-page {
    color: #edf1fb;
    min-height: 100vh;
    box-sizing: border-box;
}

.tt-dark-page *,
.tt-dark-page *::before,
.tt-dark-page *::after {
    box-sizing: border-box;
}

/* PAGE HEADER */

.tt-dark-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.tt-dark-page .page-header h1 {
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    margin: 0 0 9px;
}

.tt-dark-page .page-header p {
    color: #ddd5f6;
    font-size: 13px;
    margin: 0;
    line-height: 1.6;
}

/* BACK BUTTON */

.tt-dark-page .page-header .btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 44px;
    padding: 11px 20px;

    border: 1px solid #9183bd;
    border-radius: 10px;

    background: rgba(255,255,255,0.09);
    color: #ffffff;

    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
}

.tt-dark-page .page-header .btn-secondary:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM CARD */

.tt-dark-page .form-card {
    width: 100%;
    max-width: 1050px;

    padding: 34px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;

    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

/* TWO-COLUMN GRID */

.tt-dark-page .form-card form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

/* FIELDS */

.tt-dark-page .form-card .form-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
    margin: 0;
}

.tt-dark-page .form-card .form-group label {
    display: block;
    margin-bottom: 10px;

    color: #dce5f4;

    font-size: 13px;
    font-weight: 700;
}

/* DROPDOWNS AND INPUTS */

.tt-dark-page .form-card select,
.tt-dark-page .form-card input,
.tt-dark-page .form-card textarea {
    display: block;

    width: 100%;
    min-width: 0;
    min-height: 50px;

    padding: 11px 15px;

    border: 1px solid #3a4962;
    border-radius: 10px;

    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 14px;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.tt-dark-page .form-card select:focus,
.tt-dark-page .form-card input:focus,
.tt-dark-page .form-card textarea:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

.tt-dark-page .form-card select option {
    background: #10192a;
    color: #ffffff;
}

/* HELPER TEXT */

.tt-dark-page .form-card small {
    display: block;
    margin-top: 9px;

    color: #a8b6ce;

    font-size: 12px;
    line-height: 1.6;
}

/* BUTTON AREA */

.tt-dark-page .form-card form > button,
.tt-dark-page .form-card form > a {
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

/* SAVE / UPDATE */

.tt-dark-page .form-card .btn-primary {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.tt-dark-page .form-card .btn-primary:hover {
    filter: brightness(1.1);
}

/* CANCEL */

.tt-dark-page .form-card .btn-secondary {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.tt-dark-page .form-card .btn-secondary:hover {
    background: #34445f;
}

/* VALIDATION ERROR */

.tt-dark-page .alert-error {
    max-width: 1050px;
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

    .tt-dark-page .page-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .tt-dark-page .page-header h1 {
        font-size: 25px;
    }

    .tt-dark-page .form-card {
        padding: 23px 19px;
    }

    .tt-dark-page .form-card form {
        grid-template-columns: 1fr;
        gap: 19px;
    }
}
</style>
<main class="tt-dark-page">

    <div class="page-header">

        <div>

            <h1>
                Edit Timetable Entry
            </h1>

            <p>
                Update the complete class or laboratory block.
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

                    <?php

                    $days = [
                        "MONDAY",
                        "TUESDAY",
                        "WEDNESDAY",
                        "THURSDAY",
                        "FRIDAY",
                        "SATURDAY"
                    ];

                    foreach ($days as $day):

                    ?>

                        <option
                            value="<?php
                                echo $day;
                            ?>"
                            <?php
                            echo
                                $timetable["day"] === $day
                                ? "selected"
                                : "";
                            ?>
                        >

                            <?php
                            echo $day;
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STARTING PERIOD -->

            <div class="form-group">

                <label>
                    Starting Period
                </label>

                <select
                    name="period_id"
                    required
                >

                    <?php while (
                        $p = $periods->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$p["period_id"];
                            ?>"
                            <?php
                            echo
                                (int)$timetable["period_id"]
                                ===
                                (int)$p["period_id"]
                                ? "selected"
                                : "";
                            ?>
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
                    For a 3-period lab, use P1, P4 or P7.
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

                    <?php while (
                        $s = $sections->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$s["section_id"];
                            ?>"
                            <?php
                            echo
                                (int)$timetable["section_id"]
                                ===
                                (int)$s["section_id"]
                                ? "selected"
                                : "";
                            ?>
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

                    <?php while (
                        $s = $subjects->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$s["subject_id"];
                            ?>"
                            <?php
                            echo
                                (int)$timetable["subject_id"]
                                ===
                                (int)$s["subject_id"]
                                ? "selected"
                                : "";
                            ?>
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

                    <?php while (
                        $f = $faculty->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$f["faculty_id"];
                            ?>"
                            <?php
                            echo
                                (int)$timetable["faculty_id"]
                                ===
                                (int)$f["faculty_id"]
                                ? "selected"
                                : "";
                            ?>
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


            <!-- CLASSROOM / LAB -->

            <div class="form-group">

                <label>
                    Classroom / Lab
                </label>

                <select
                    name="classroom_id"
                    required
                >

                    <?php while (
                        $c = $classrooms->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$c["classroom_id"];
                            ?>"
                            <?php
                            echo
                                (int)$timetable["classroom_id"]
                                ===
                                (int)$c["classroom_id"]
                                ? "selected"
                                : "";
                            ?>
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

                    <option
                        value="DRAFT"
                        <?php
                        echo
                            $timetable["status"] === "DRAFT"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Draft
                    </option>

                    <option
                        value="PUBLISHED"
                        <?php
                        echo
                            $timetable["status"] === "PUBLISHED"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Published
                    </option>

                </select>

            </div>


            <!-- BUTTONS -->

            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Timetable
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