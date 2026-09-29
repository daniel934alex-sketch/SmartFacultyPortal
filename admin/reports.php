<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Reports";


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$selectedSection = isset($_GET["section_id"])
    ? (int)$_GET["section_id"]
    : 0;

$selectedDay = isset($_GET["day"])
    ? strtoupper(trim($_GET["day"]))
    : "";

$selectedStatus = isset($_GET["status"])
    ? strtoupper(trim($_GET["status"]))
    : "";


/*
|--------------------------------------------------------------------------
| VALID DAYS
|--------------------------------------------------------------------------
*/

$validDays = [
    "MONDAY",
    "TUESDAY",
    "WEDNESDAY",
    "THURSDAY",
    "FRIDAY",
    "SATURDAY"
];


/*
|--------------------------------------------------------------------------
| VALID STATUS
|--------------------------------------------------------------------------
*/

$validStatuses = [
    "DRAFT",
    "PUBLISHED"
];

if (!in_array($selectedDay, $validDays, true)) {
    $selectedDay = "";
}

if (!in_array($selectedStatus, $validStatuses, true)) {
    $selectedStatus = "";
}


/*
|--------------------------------------------------------------------------
| GET SECTIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_name
";

$sectionListResult = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| TIMETABLE SUMMARY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(*) AS total_entries,

        SUM(
            CASE
                WHEN status = 'PUBLISHED'
                THEN 1
                ELSE 0
            END
        ) AS published_entries,

        SUM(
            CASE
                WHEN status = 'DRAFT'
                THEN 1
                ELSE 0
            END
        ) AS draft_entries

    FROM timetable
";

$result = $conn->query($sql);

$summary = [
    "total_entries" => 0,
    "published_entries" => 0,
    "draft_entries" => 0
];

if ($result) {

    $row = $result->fetch_assoc();

    $summary["total_entries"] =
        (int)$row["total_entries"];

    $summary["published_entries"] =
        (int)$row["published_entries"];

    $summary["draft_entries"] =
        (int)$row["draft_entries"];
}


/*
|--------------------------------------------------------------------------
| SECTION-WISE TIMETABLE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        sec.section_name,
        COUNT(t.timetable_id) AS total_periods

    FROM sections sec

    LEFT JOIN timetable t
        ON sec.section_id = t.section_id

    GROUP BY
        sec.section_id,
        sec.section_name

    ORDER BY
        sec.section_name
";

$sectionResult = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| FACULTY TEACHING PERIODS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        f.faculty_code,
        f.faculty_name,
        COUNT(t.timetable_id) AS total_periods

    FROM faculty f

    LEFT JOIN timetable t
        ON f.faculty_id = t.faculty_id

    GROUP BY
        f.faculty_id,
        f.faculty_code,
        f.faculty_name

    ORDER BY
        f.faculty_code
";

$facultyResult = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| SUBJECT-WISE PERIODS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.subject_code,
        s.subject_name,
        s.subject_type,
        COUNT(t.timetable_id) AS total_periods

    FROM subjects s

    LEFT JOIN timetable t
        ON s.subject_id = t.subject_id

    GROUP BY
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.subject_type

    ORDER BY
        s.subject_code
";

$subjectResult = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| CLASSROOM USAGE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.room_number,
        c.room_type,
        COUNT(t.timetable_id) AS total_periods

    FROM classrooms c

    LEFT JOIN timetable t
        ON c.classroom_id = t.classroom_id

    GROUP BY
        c.classroom_id,
        c.room_number,
        c.room_type

    ORDER BY
        c.room_number
";

$classroomResult = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| DETAILED TIMETABLE REPORT
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";


/*
| Section filter
*/

if ($selectedSection > 0) {

    $where[] = "t.section_id = ?";

    $params[] = $selectedSection;

    $types .= "i";
}


/*
| Day filter
*/

if ($selectedDay !== "") {

    $where[] = "t.day = ?";

    $params[] = $selectedDay;

    $types .= "s";
}


/*
| Status filter
*/

if ($selectedStatus !== "") {

    $where[] = "t.status = ?";

    $params[] = $selectedStatus;

    $types .= "s";
}


$detailSql = "
    SELECT

        t.timetable_id,

        t.day,

        p.period_number,

        p.start_time,

        p.end_time,

        sec.section_name,

        s.subject_code,

        s.subject_name,

        s.subject_type,

        f.faculty_code,

        f.faculty_name,

        c.room_number,

        c.room_type,

        t.status

    FROM timetable t

    INNER JOIN periods p
        ON t.period_id = p.period_id

    INNER JOIN sections sec
        ON t.section_id = sec.section_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    INNER JOIN faculty f
        ON t.faculty_id = f.faculty_id

    INNER JOIN classrooms c
        ON t.classroom_id = c.classroom_id
";


if (count($where) > 0) {

    $detailSql .=
        " WHERE " .
        implode(" AND ", $where);
}


$detailSql .= "
    ORDER BY

        FIELD(
            t.day,
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),

        p.period_number,

        sec.section_name
";


$detailStmt = $conn->prepare($detailSql);

$detailResult = false;


if ($detailStmt) {

    if (count($params) > 0) {

        $bindParams = [];

        $bindParams[] = $types;

        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }

        call_user_func_array(
            [$detailStmt, "bind_param"],
            $bindParams
        );
    }

    $detailStmt->execute();

    $detailResult =
        $detailStmt->get_result();
}

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<main>

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Reports
            </h1>

            <p>
                View timetable, faculty, subject and classroom reports.
            </p>

        </div>

    </div>


    <!-- =====================================================
         SUMMARY CARDS
         ===================================================== -->

    <div class="dashboard-cards">

        <div class="dashboard-card">

            <h3>
                Total Timetable Entries
            </h3>

            <div class="number">
                <?php
                echo $summary["total_entries"];
                ?>
            </div>

        </div>


        <div class="dashboard-card">

            <h3>
                Published Entries
            </h3>

            <div class="number">
                <?php
                echo $summary["published_entries"];
                ?>
            </div>

        </div>


        <div class="dashboard-card">

            <h3>
                Draft Entries
            </h3>

            <div class="number">
                <?php
                echo $summary["draft_entries"];
                ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         DETAILED TIMETABLE REPORT
         ===================================================== -->

    <div class="table-container">

        <div
            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:15px;
                margin-bottom:20px;
                flex-wrap:wrap;
            "
        >

            <div>

                <h2>
                    Detailed Timetable Report
                </h2>

                <p
                    style="
                        color:#64748b;
                        margin-top:5px;
                    "
                >
                    Filter and view timetable entries.
                </p>

            </div>


            <button
                type="button"
                class="btn btn-secondary"
                onclick="window.print();"
            >
                Print Report
            </button>

        </div>


        <!-- FILTER FORM -->

        <form
            method="GET"
            action="reports.php"
            style="
                max-width:none;
                background:#f8fafc;
                padding:20px;
                border-radius:8px;
                margin-bottom:20px;
            "
        >

            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(auto-fit,minmax(180px,1fr));
                    gap:15px;
                    align-items:end;
                "
            >

                <!-- SECTION -->

                <div class="form-group">

                    <label for="section_id">
                        Section
                    </label>

                    <select
                        name="section_id"
                        id="section_id"
                    >

                        <option value="0">
                            All Sections
                        </option>

                        <?php if (
                            $sectionListResult &&
                            $sectionListResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $section =
                                $sectionListResult->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?php
                                        echo (int)
                                            $section["section_id"];
                                    ?>"
                                    <?php
                                    if (
                                        $selectedSection ===
                                        (int)$section["section_id"]
                                    ) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $section["section_name"]
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- DAY -->

                <div class="form-group">

                    <label for="day">
                        Day
                    </label>

                    <select
                        name="day"
                        id="day"
                    >

                        <option value="">
                            All Days
                        </option>

                        <?php foreach (
                            $validDays as $day
                        ): ?>

                            <option
                                value="<?php
                                    echo $day;
                                ?>"
                                <?php
                                if (
                                    $selectedDay === $day
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >

                                <?php
                                echo $day;
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                    >

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="DRAFT"
                            <?php
                            if (
                                $selectedStatus === "DRAFT"
                            ) {
                                echo "selected";
                            }
                            ?>
                        >
                            Draft
                        </option>

                        <option
                            value="PUBLISHED"
                            <?php
                            if (
                                $selectedStatus === "PUBLISHED"
                            ) {
                                echo "selected";
                            }
                            ?>
                        >
                            Published
                        </option>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Generate Report
                    </button>


                    <a
                        href="reports.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>

            </div>

        </form>


        <!-- DETAILED TABLE -->

        <div
            style="
                overflow-x:auto;
            "
        >

            <table>

                <thead>

                    <tr>

                        <th>
                            Day
                        </th>

                        <th>
                            Period
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Section
                        </th>

                        <th>
                            Subject
                        </th>

                        <th>
                            Faculty
                        </th>

                        <th>
                            Room
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        $detailResult &&
                        $detailResult->num_rows > 0
                    ): ?>

                        <?php while (
                            $row =
                            $detailResult->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["day"]
                                    );
                                    ?>
                                </td>


                                <td>

                                    P<?php
                                    echo (int)
                                        $row["period_number"];
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime(
                                            $row["start_time"]
                                        )
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime(
                                            $row["end_time"]
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["section_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $row["subject_code"]
                                        );
                                        ?>

                                    </strong>

                                    <br>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            $row["subject_name"]
                                        );
                                        ?>

                                    </small>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["faculty_code"]
                                    );
                                    ?>

                                    <br>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            $row["faculty_name"]
                                        );
                                        ?>

                                    </small>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["room_number"]
                                    );
                                    ?>

                                    <br>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            $row["room_type"]
                                        );
                                        ?>

                                    </small>

                                </td>


                                <td>

                                    <?php
                                    if (
                                        $row["status"] ===
                                        "PUBLISHED"
                                    ):
                                    ?>

                                        <span
                                            class="status-active"
                                        >
                                            PUBLISHED
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status-inactive"
                                        >
                                            DRAFT
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="8"
                                style="
                                    text-align:center;
                                    padding:25px;
                                "
                            >
                                No timetable entries found
                                for the selected filters.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         SECTION-WISE REPORT
         ===================================================== -->

    <div class="table-container">

        <h2 style="margin-bottom:15px;">
            Section-wise Timetable
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Section
                    </th>

                    <th>
                        Timetable Periods
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    $sectionResult &&
                    $sectionResult->num_rows > 0
                ): ?>

                    <?php while (
                        $row =
                        $sectionResult->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["section_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)
                                    $row["total_periods"];
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="2">
                            No section data found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- =====================================================
         FACULTY REPORT
         ===================================================== -->

    <div class="table-container">

        <h2 style="margin-bottom:15px;">
            Faculty Teaching Periods
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Faculty Code
                    </th>

                    <th>
                        Faculty Name
                    </th>

                    <th>
                        Total Periods
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    $facultyResult &&
                    $facultyResult->num_rows > 0
                ): ?>

                    <?php while (
                        $row =
                        $facultyResult->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["faculty_code"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["faculty_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo (int)
                                        $row["total_periods"];
                                    ?>
                                </strong>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="3">
                            No faculty timetable data found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- =====================================================
         SUBJECT REPORT
         ===================================================== -->

    <div class="table-container">

        <h2 style="margin-bottom:15px;">
            Subject-wise Periods
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Subject Code
                    </th>

                    <th>
                        Subject Name
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Timetable Periods
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    $subjectResult &&
                    $subjectResult->num_rows > 0
                ): ?>

                    <?php while (
                        $row =
                        $subjectResult->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["subject_code"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["subject_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["subject_type"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)
                                    $row["total_periods"];
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="4">
                            No subject timetable data found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- =====================================================
         CLASSROOM REPORT
         ===================================================== -->

    <div class="table-container">

        <h2 style="margin-bottom:15px;">
            Classroom Usage
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Room
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Used Periods
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    $classroomResult &&
                    $classroomResult->num_rows > 0
                ): ?>

                    <?php while (
                        $row =
                        $classroomResult->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["room_number"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $row["room_type"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)
                                    $row["total_periods"];
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="3">
                            No classroom data found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>


<?php require_once "../includes/footer.php"; ?>