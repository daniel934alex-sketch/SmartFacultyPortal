
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Reports";

/* ==========================================
   REPORT FILTERS
   ========================================== */

$selectedSection = isset($_GET["section_id"])
    ? (int)$_GET["section_id"]
    : 0;

$selectedDay = isset($_GET["day"])
    ? strtoupper(trim($_GET["day"]))
    : "";

$selectedStatus = isset($_GET["status"])
    ? strtoupper(trim($_GET["status"]))
    : "";

$validDays = [
    "MONDAY",
    "TUESDAY",
    "WEDNESDAY",
    "THURSDAY",
    "FRIDAY",
    "SATURDAY"
];

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

/* ==========================================
   HELPER FOR SAFE HTML OUTPUT
   ========================================== */

function reportEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ==========================================
   GET SECTIONS FOR FILTER
   ========================================== */

$sectionListResult = $conn->query("
    SELECT section_id, section_name
    FROM sections
    ORDER BY section_name
");

/* ==========================================
   TIMETABLE SUMMARY
   ========================================== */

$summary = [
    "total_entries" => 0,
    "published_entries" => 0,
    "draft_entries" => 0
];

$result = $conn->query("
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
");

if ($result) {

    $row = $result->fetch_assoc();

    $summary["total_entries"] =
        (int)$row["total_entries"];

    $summary["published_entries"] =
        (int)$row["published_entries"];

    $summary["draft_entries"] =
        (int)$row["draft_entries"];
}

/* ==========================================
   SECTION-WISE TIMETABLE
   ========================================== */

$sectionResult = $conn->query("
    SELECT
        sec.section_name,
        COUNT(t.timetable_id) AS total_periods

    FROM sections sec

    LEFT JOIN timetable t
        ON sec.section_id = t.section_id

    GROUP BY
        sec.section_id,
        sec.section_name

    ORDER BY sec.section_name
");

/* ==========================================
   FACULTY TEACHING PERIODS
   ========================================== */

$facultyResult = $conn->query("
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

    ORDER BY f.faculty_code
");

/* ==========================================
   SUBJECT-WISE PERIODS
   ========================================== */

$subjectResult = $conn->query("
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

    ORDER BY s.subject_code
");

/* ==========================================
   CLASSROOM USAGE
   ========================================== */

$classroomResult = $conn->query("
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

    ORDER BY c.room_number
");

/* ==========================================
   DETAILED TIMETABLE REPORT FILTERS
   ========================================== */

$where = [];
$params = [];
$types = "";

if ($selectedSection > 0) {

    $where[] = "t.section_id = ?";
    $params[] = $selectedSection;
    $types .= "i";
}

if ($selectedDay !== "") {

    $where[] = "t.day = ?";
    $params[] = $selectedDay;
    $types .= "s";
}

if ($selectedStatus !== "") {

    $where[] = "t.status = ?";
    $params[] = $selectedStatus;
    $types .= "s";
}

/* ==========================================
   DETAILED TIMETABLE QUERY
   ========================================== */

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

    $detailResult = $detailStmt->get_result();
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ============================================
   REPORTS - PREMIUM DARK HOD THEME

   This CSS affects only main.reports-page.
   Faculty pages and shared CSS are untouched.
   ============================================ */

main.reports-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.reports-page *,
.reports-page *::before,
.reports-page *::after {
    box-sizing: border-box;
}

.reports-page .reports-wrap {
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
}

/* ==========================================
   PAGE HEADER
   ========================================== */

.reports-page .reports-header {
    margin-bottom: 25px;
    padding: 28px 32px;

    border: 1px solid #51478c;
    border-radius: 18px;

    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 60%,
        #574898
    );
}

.reports-page .reports-header h1 {
    margin: 0 0 8px;

    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
}

.reports-page .reports-header p {
    margin: 0;

    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* ==========================================
   SUMMARY CARDS
   ========================================== */

.reports-page .reports-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 26px;
}

.reports-page .reports-stat {
    min-width: 0;
    padding: 25px;

    border: 1px solid #2d3a50;
    border-radius: 16px;

    background: #172235;
    box-shadow: 0 10px 25px rgba(0,0,0,.1);
}

.reports-page .reports-stat-label {
    margin: 0 0 16px;

    color: #baa5ff;
    font-size: 13px;
    font-weight: 750;
}

.reports-page .reports-stat-number {
    margin: 0;

    color: #ffffff;
    font-size: 35px;
    line-height: 1.2;
    font-weight: 800;
}

/* ==========================================
   REPORT CARDS
   ========================================== */

.reports-page .reports-panel {
    width: 100%;
    min-width: 0;

    margin-bottom: 25px;
    padding: 26px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;
    box-shadow: 0 12px 28px rgba(0,0,0,.12);
}

.reports-page .reports-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;

    margin-bottom: 22px;
}

.reports-page .reports-panel-header h2,
.reports-page .reports-panel h2 {
    margin: 0;

    color: #ffffff;
    font-size: 20px;
    font-weight: 800;
}

.reports-page .reports-description {
    margin: 9px 0 0;

    color: #a8b6ce;
    font-size: 13px;
    line-height: 1.6;
}

/* ==========================================
   FILTER FORM
   ========================================== */

.reports-page .reports-filter {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(140px, 1fr))
        auto;

    gap: 16px;
    align-items: end;

    width: 100%;
    max-width: none;
    margin: 0 0 24px;
    padding: 23px;

    border: 1px solid #3a4962;
    border-radius: 13px;

    background: #111b2d;
    box-shadow: none;
}

.reports-page .reports-filter-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 10px;
}

.reports-page .reports-filter-field label {
    display: block;

    margin: 0;
    color: #dce5f4;

    font-size: 13px;
    font-weight: 700;
}

.reports-page .reports-filter-field select {
    display: block;

    width: 100%;
    min-width: 0;
    min-height: 46px;

    padding: 10px 12px;

    border: 1px solid #3a4962;
    border-radius: 10px;

    outline: none;

    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
}

.reports-page .reports-filter-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,.14);
}

.reports-page .reports-filter-field select option {
    background: #10192a;
    color: #ffffff;
}

/* ==========================================
   BUTTONS
   ========================================== */

.reports-page .reports-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.reports-page .reports-button,
.reports-page .reports-reset,
.reports-page .reports-print {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 46px;
    padding: 12px 19px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;

    cursor: pointer;
}

.reports-page .reports-button {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.reports-page .reports-button:hover {
    filter: brightness(1.1);
}

.reports-page .reports-reset {
    border: 1px solid #4b5a74;

    background: #28364e;
    color: #e1e9f7;
}

.reports-page .reports-reset:hover {
    background: #34445f;
}

.reports-page .reports-print {
    border: 1px solid #8677c7;

    background: #302956;
    color: #ffffff;
}

.reports-page .reports-print:hover {
    background: #42356e;
}

/* ==========================================
   TABLES
   ========================================== */

.reports-page .reports-table-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;

    border: 1px solid #2d3a50;
    border-radius: 12px;
}

.reports-page .reports-table {
    width: 100%;

    margin: 0;
    border-collapse: separate;
    border-spacing: 0;

    table-layout: auto;

    color: #edf1fb;
    font-size: 13px;
}

.reports-page .reports-table th,
.reports-page .reports-table td {
    padding: 15px 17px;

    border: none;
    border-bottom: 1px solid #29374d;

    text-align: left;
    vertical-align: middle;

    line-height: 1.6;
    overflow-wrap: anywhere;
}

.reports-page .reports-table thead th {
    background: #23334c;

    color: #e2d9ff;
    font-size: 12px;
    font-weight: 800;

    white-space: nowrap;
}

.reports-page .reports-table tbody tr {
    background: #172235;
}

.reports-page .reports-table tbody tr:nth-child(even) {
    background: #1b293e;
}

.reports-page .reports-table tbody tr:hover {
    background: #24344e;
}

.reports-page .reports-table tbody tr:last-child td {
    border-bottom: none;
}

.reports-page .reports-table td {
    color: #e3ebf8;
}

.reports-page .reports-table td strong {
    color: #ffffff;
    font-weight: 750;
}

.reports-page .reports-table td small {
    display: block;
    margin-top: 3px;

    color: #a8b6ce;
    font-size: 11px;
    line-height: 1.5;
}

/* DETAILED REPORT NEEDS ROOM FOR 8 COLUMNS */

.reports-page .reports-table-detailed {
    min-width: 1100px;
}

.reports-page .reports-table-detailed th:nth-child(1),
.reports-page .reports-table-detailed td:nth-child(1) {
    min-width: 116px;
    white-space: nowrap;
}

.reports-page .reports-table-detailed th:nth-child(2),
.reports-page .reports-table-detailed td:nth-child(2) {
    min-width: 85px;
    white-space: nowrap;
}

.reports-page .reports-table-detailed th:nth-child(3),
.reports-page .reports-table-detailed td:nth-child(3) {
    min-width: 165px;
    white-space: nowrap;
}

.reports-page .reports-table-detailed th:nth-child(4),
.reports-page .reports-table-detailed td:nth-child(4) {
    min-width: 100px;
}

.reports-page .reports-table-detailed th:nth-child(5),
.reports-page .reports-table-detailed td:nth-child(5) {
    min-width: 170px;
}

.reports-page .reports-table-detailed th:nth-child(6),
.reports-page .reports-table-detailed td:nth-child(6) {
    min-width: 170px;
}

.reports-page .reports-table-detailed th:nth-child(7),
.reports-page .reports-table-detailed td:nth-child(7) {
    min-width: 110px;
}

.reports-page .reports-table-detailed th:nth-child(8),
.reports-page .reports-table-detailed td:nth-child(8) {
    min-width: 120px;
}

/* ==========================================
   STATUS BADGES
   ========================================== */

.reports-page .reports-status {
    display: inline-flex;
    align-items: center;

    padding: 6px 11px;

    border-radius: 100px;

    font-size: 11px;
    font-weight: 800;
    letter-spacing: .3px;
}

.reports-page .reports-status-published {
    border: 1px solid #1d6658;
    background: #143d38;
    color: #81e7c5;
}

.reports-page .reports-status-draft {
    border: 1px solid #8a6b2d;
    background: #433719;
    color: #ffe09b;
}

/* EMPTY REPORTS */

.reports-page .reports-empty {
    padding: 28px 18px !important;
    text-align: center !important;

    color: #a8b6ce !important;
    font-size: 13px;
}

/* ==========================================
   RESPONSIVE
   ========================================== */

@media (max-width: 1100px) {

    .reports-page .reports-filter {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .reports-page .reports-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 760px) {

    .reports-page .reports-header {
        padding: 24px 20px;
    }

    .reports-page .reports-header h1 {
        font-size: 25px;
    }

    .reports-page .reports-summary {
        grid-template-columns: 1fr;
    }

    .reports-page .reports-stat {
        padding: 22px;
    }

    .reports-page .reports-panel {
        padding: 19px;
    }

    .reports-page .reports-filter {
        grid-template-columns: 1fr;
        padding: 17px;
    }

    .reports-page .reports-actions {
        grid-column: auto;
    }

    .reports-page .reports-panel-header h2,
    .reports-page .reports-panel h2 {
        font-size: 18px;
    }
}

/* ==========================================
   PRINT REPORT
   ========================================== */

@media print {

    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
        color: #000000 !important;
    }

    body > header,
    body > nav,
    body > aside,
    .navbar,
    .sidebar,
    .topbar,
    .top-navbar,
    .hod-navbar,
    .hod-sidebar,
    .reports-page .reports-filter,
    .reports-page .reports-print,
    .reports-page .reports-actions {
        display: none !important;
    }

    main.reports-page {
        position: static !important;

        width: 100% !important;
        max-width: none !important;
        min-height: 0 !important;

        margin: 0 !important;
        padding: 0 !important;

        transform: none !important;

        background: #ffffff !important;
        color: #000000 !important;
    }

    .reports-page .reports-wrap {
        max-width: none !important;
        margin: 0 !important;
    }

    .reports-page .reports-header {
        padding: 0 0 14px !important;

        border: none !important;
        border-bottom: 2px solid #000000 !important;
        border-radius: 0 !important;

        background: #ffffff !important;
        box-shadow: none !important;
    }

    .reports-page .reports-header h1,
    .reports-page .reports-header p,
    .reports-page .reports-panel h2,
    .reports-page .reports-panel-header h2,
    .reports-page .reports-description {
        color: #000000 !important;
    }

    .reports-page .reports-summary {
        gap: 10px;
        margin-bottom: 15px;
    }

    .reports-page .reports-stat {
        padding: 12px !important;

        border: 1px solid #b9b9b9 !important;
        border-radius: 0 !important;

        background: #ffffff !important;
        box-shadow: none !important;
    }

    .reports-page .reports-stat-label,
    .reports-page .reports-stat-number {
        color: #000000 !important;
    }

    .reports-page .reports-stat-number {
        font-size: 23px;
    }

    .reports-page .reports-panel {
        padding: 0 !important;
        margin-bottom: 18px !important;

        border: none !important;
        border-radius: 0 !important;

        background: #ffffff !important;
        box-shadow: none !important;
    }

    .reports-page .reports-table-scroll {
        overflow: visible !important;
        border: none !important;
    }

    .reports-page .reports-table,
    .reports-page .reports-table-detailed {
        width: 100% !important;
        min-width: 0 !important;
        font-size: 9px !important;
        border-collapse: collapse !important;
    }

    .reports-page .reports-table th,
    .reports-page .reports-table td {
        min-width: 0 !important;
        padding: 6px 7px !important;
        border: 1px solid #c5c5c5 !important;

        background: #ffffff !important;
        color: #000000 !important;

        white-space: normal !important;
    }

    .reports-page .reports-table thead th {
        background: #e7e7e7 !important;
        font-weight: 800 !important;
    }

    .reports-page .reports-table td strong,
    .reports-page .reports-table td small {
        color: #000000 !important;
    }

    .reports-page .reports-status {
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        color: #000000 !important;
    }

    .reports-page .reports-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .reports-page .reports-table thead {
        display: table-header-group;
    }
}
</style>

<main class="reports-page">

    <div class="reports-wrap">

        <!-- =====================================
             PAGE HEADER
             ===================================== -->

        <div class="reports-header">

            <h1>Reports</h1>

            <p>
                View timetable, faculty, subject and
                classroom reports.
            </p>

        </div>


        <!-- =====================================
             SUMMARY CARDS
             ===================================== -->

        <div class="reports-summary">

            <div class="reports-stat">

                <h3 class="reports-stat-label">
                    Total Timetable Entries
                </h3>

                <div class="reports-stat-number">
                    <?= $summary["total_entries"] ?>
                </div>

            </div>

            <div class="reports-stat">

                <h3 class="reports-stat-label">
                    Published Entries
                </h3>

                <div class="reports-stat-number">
                    <?= $summary["published_entries"] ?>
                </div>

            </div>

            <div class="reports-stat">

                <h3 class="reports-stat-label">
                    Draft Entries
                </h3>

                <div class="reports-stat-number">
                    <?= $summary["draft_entries"] ?>
                </div>

            </div>

        </div>


        <!-- =====================================
             DETAILED TIMETABLE REPORT
             ===================================== -->

        <section class="reports-panel">

            <div class="reports-panel-header">

                <div>

                    <h2>
                        Detailed Timetable Report
                    </h2>

                    <p class="reports-description">
                        Filter and view timetable entries.
                    </p>

                </div>

                <button
                    type="button"
                    class="reports-print"
                    onclick="window.print();"
                >
                    Print Report
                </button>

            </div>


            <!-- FILTER FORM -->

            <form
                method="GET"
                action="reports.php"
                class="reports-filter"
            >

                <!-- SECTION -->

                <div class="reports-filter-field">

                    <label for="section_id">
                        Section
                    </label>

                    <select
                        id="section_id"
                        name="section_id"
                    >

                        <option value="0">
                            All Sections
                        </option>

                        <?php if ($sectionListResult): ?>

                            <?php while (
                                $section =
                                    $sectionListResult->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= (int)$section["section_id"] ?>"
                                    <?= $selectedSection ===
                                        (int)$section["section_id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= reportEscape(
                                        $section["section_name"]
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- DAY -->

                <div class="reports-filter-field">

                    <label for="day">
                        Day
                    </label>

                    <select
                        id="day"
                        name="day"
                    >

                        <option value="">
                            All Days
                        </option>

                        <?php foreach ($validDays as $day): ?>

                            <option
                                value="<?= reportEscape($day) ?>"
                                <?= $selectedDay === $day
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= ucfirst(strtolower($day)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- STATUS -->

                <div class="reports-filter-field">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="DRAFT"
                            <?= $selectedStatus === "DRAFT"
                                ? "selected"
                                : "" ?>
                        >
                            Draft
                        </option>

                        <option
                            value="PUBLISHED"
                            <?= $selectedStatus === "PUBLISHED"
                                ? "selected"
                                : "" ?>
                        >
                            Published
                        </option>

                    </select>

                </div>


                <!-- GENERATE + RESET -->

                <div class="reports-actions">

                    <button
                        type="submit"
                        class="reports-button"
                    >
                        Generate Report
                    </button>

                    <a
                        href="reports.php"
                        class="reports-reset"
                    >
                        Reset
                    </a>

                </div>

            </form>


            <!-- DETAILED TABLE -->

            <div class="reports-table-scroll">

                <table class="reports-table reports-table-detailed">

                    <thead>

                        <tr>
                            <th>Day</th>
                            <th>Period</th>
                            <th>Time</th>
                            <th>Section</th>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th>Room</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (
                            $detailResult &&
                            $detailResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $row = $detailResult->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>
                                        <?= reportEscape(
                                            ucfirst(
                                                strtolower($row["day"])
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            P<?= (int)$row["period_number"] ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= date(
                                            "h:i A",
                                            strtotime($row["start_time"])
                                        ) ?>

                                        -

                                        <?= date(
                                            "h:i A",
                                            strtotime($row["end_time"])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $row["section_name"]
                                        ) ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= reportEscape(
                                                $row["subject_code"]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= reportEscape(
                                                $row["subject_name"]
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= reportEscape(
                                                $row["faculty_code"]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= reportEscape(
                                                $row["faculty_name"]
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= reportEscape(
                                                $row["room_number"]
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= reportEscape(
                                                $row["room_type"]
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <?php if (
                                            $row["status"] === "PUBLISHED"
                                        ): ?>

                                            <span
                                                class="reports-status reports-status-published"
                                            >
                                                PUBLISHED
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="reports-status reports-status-draft"
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
                                    class="reports-empty"
                                >
                                    No timetable entries found
                                    for the selected filters.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =====================================
             SECTION-WISE TIMETABLE
             ===================================== -->

        <section class="reports-panel">

            <div class="reports-panel-header">
                <h2>Section-wise Timetable</h2>
            </div>

            <div class="reports-table-scroll">

                <table class="reports-table">

                    <thead>

                        <tr>
                            <th>Section</th>
                            <th>Timetable Periods</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (
                            $sectionResult &&
                            $sectionResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $row = $sectionResult->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>
                                        <?= reportEscape(
                                            $row["section_name"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$row["total_periods"] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="2"
                                    class="reports-empty"
                                >
                                    No section data found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =====================================
             FACULTY TEACHING PERIODS REPORT
             ===================================== -->

        <section class="reports-panel">

            <div class="reports-panel-header">
                <h2>Faculty Teaching Periods</h2>
            </div>

            <div class="reports-table-scroll">

                <table class="reports-table">

                    <thead>

                        <tr>
                            <th>Faculty Code</th>
                            <th>Faculty Name</th>
                            <th>Total Periods</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (
                            $facultyResult &&
                            $facultyResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $row = $facultyResult->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>
                                        <?= reportEscape(
                                            $row["faculty_code"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $row["faculty_name"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$row["total_periods"] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="3"
                                    class="reports-empty"
                                >
                                    No faculty timetable data found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =====================================
             SUBJECT-WISE PERIODS REPORT
             ===================================== -->

        <section class="reports-panel">

            <div class="reports-panel-header">
                <h2>Subject-wise Periods</h2>
            </div>

            <div class="reports-table-scroll">

                <table class="reports-table">

                    <thead>

                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Type</th>
                            <th>Timetable Periods</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (
                            $subjectResult &&
                            $subjectResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $row = $subjectResult->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>
                                        <?= reportEscape(
                                            $row["subject_code"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $row["subject_name"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $row["subject_type"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$row["total_periods"] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="4"
                                    class="reports-empty"
                                >
                                    No subject timetable data found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =====================================
             CLASSROOM USAGE REPORT
             ===================================== -->

        <section class="reports-panel">

            <div class="reports-panel-header">
                <h2>Classroom Usage</h2>
            </div>

            <div class="reports-table-scroll">

                <table class="reports-table">

                    <thead>

                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Used Periods</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (
                            $classroomResult &&
                            $classroomResult->num_rows > 0
                        ): ?>

                            <?php while (
                                $row = $classroomResult->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>
                                        <?= reportEscape(
                                            $row["room_number"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $row["room_type"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$row["total_periods"] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="3"
                                    class="reports-empty"
                                >
                                    No classroom data found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>

</main>

<?php

if ($detailStmt) {
    $detailStmt->close();
}

?>

<?php require_once "../includes/footer.php"; ?>
