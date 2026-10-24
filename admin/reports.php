<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");
require_once "../config/database.php";

$pageTitle = "Student Attendance Reports";

function reportEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$attendancePeriod = strtolower(trim($_GET['attendance_period'] ?? 'weekly'));
if (!in_array($attendancePeriod, ['weekly', 'monthly', 'semester'], true)) {
    $attendancePeriod = 'weekly';
}
$attendanceSection = isset($_GET['attendance_section']) ? (int)$_GET['attendance_section'] : 0;
$today = new DateTimeImmutable('today');
$currentMonday = $today->modify('monday this week');
$attendanceWeekStart = $_GET['week_start'] ?? $currentMonday->format('Y-m-d');
$attendanceMonth = (isset($_GET['report_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['report_month']))
    ? $_GET['report_month'] : $today->format('Y-m');
$attendanceSemesterStart = $_GET['semester_start'] ?? $today->modify('first day of this month')->modify('-3 months')->format('Y-m-d');
$attendanceSemesterEnd = $_GET['semester_end'] ?? $today->format('Y-m-d');

$attendanceStart = '';
$attendanceEnd = '';
$attendancePeriodLabel = '';
$attendanceDateError = '';

try {
    if ($attendancePeriod === 'weekly') {
        $weekDate = new DateTimeImmutable($attendanceWeekStart);
        $attendanceStart = $weekDate->format('Y-m-d');
        $attendanceEnd = $weekDate->modify('+6 days')->format('Y-m-d');
        $attendancePeriodLabel = 'Weekly report: ' . $attendanceStart . ' to ' . $attendanceEnd;
    } elseif ($attendancePeriod === 'monthly') {
        $monthDate = DateTimeImmutable::createFromFormat('!Y-m', $attendanceMonth);
        if (!$monthDate || $monthDate->format('Y-m') !== $attendanceMonth) {
            throw new Exception('Choose a valid month.');
        }
        $attendanceStart = $monthDate->modify('first day of this month')->format('Y-m-d');
        $attendanceEnd = $monthDate->modify('last day of this month')->format('Y-m-d');
        $attendancePeriodLabel = 'Monthly report: ' . $monthDate->format('F Y');
    } else {
        $semesterStartDate = new DateTimeImmutable($attendanceSemesterStart);
        $semesterEndDate = new DateTimeImmutable($attendanceSemesterEnd);
        if ($semesterStartDate > $semesterEndDate) {
            throw new Exception('Semester start date must be on or before the end date.');
        }
        $attendanceStart = $semesterStartDate->format('Y-m-d');
        $attendanceEnd = $semesterEndDate->format('Y-m-d');
        $attendancePeriodLabel = 'Semester report: ' . $attendanceStart . ' to ' . $attendanceEnd;
    }
} catch (Throwable $e) {
    $attendanceDateError = $e->getMessage();
    $attendanceStart = $today->format('Y-m-d');
    $attendanceEnd = $today->format('Y-m-d');
    $attendancePeriodLabel = 'Attendance report';
}

$sectionOptions = $conn->query("SELECT section_id, section_name FROM sections ORDER BY section_name");
$attendanceRows = [];
$attendanceSummary = ['students' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0.0];
$attendanceQueryError = '';

if ($attendanceDateError === '') {
    // Count calendar days, not individual periods. A date is present if any recorded period is PRESENT.
    // It is absent only when every recorded period that date is ABSENT.
    $sql = "
        SELECT
            st.student_id,
            st.register_number,
            st.student_name,
            sec.section_name,
            COALESCE(SUM(CASE WHEN daily.has_present = 1 THEN 1 ELSE 0 END), 0) AS present_days,
            COALESCE(SUM(CASE WHEN daily.has_present = 0 AND daily.attendance_date IS NOT NULL THEN 1 ELSE 0 END), 0) AS absent_days
        FROM students st
        INNER JOIN sections sec ON sec.section_id = st.section_id
        LEFT JOIN (
            SELECT student_id, attendance_date,
                   MAX(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) AS has_present
            FROM attendance
            WHERE attendance_date BETWEEN ? AND ?
            GROUP BY student_id, attendance_date
        ) daily ON daily.student_id = st.student_id
        WHERE (? = 0 OR st.section_id = ?)
        GROUP BY st.student_id, st.register_number, st.student_name, sec.section_name
        ORDER BY sec.section_name, st.register_number
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssii', $attendanceStart, $attendanceEnd, $attendanceSection, $attendanceSection);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['present_days'] = (int)$row['present_days'];
                $row['absent_days'] = (int)$row['absent_days'];
                $totalDays = $row['present_days'] + $row['absent_days'];
                $row['percentage'] = $totalDays > 0 ? round(($row['present_days'] / $totalDays) * 100, 2) : 0.0;
                $attendanceRows[] = $row;
                $attendanceSummary['students']++;
                $attendanceSummary['present'] += $row['present_days'];
                $attendanceSummary['absent'] += $row['absent_days'];
            }
            $totalReportedDays = $attendanceSummary['present'] + $attendanceSummary['absent'];
            $attendanceSummary['percentage'] = $totalReportedDays > 0
                ? round(($attendanceSummary['present'] / $totalReportedDays) * 100, 2) : 0.0;
        } else {
            $attendanceQueryError = 'Could not generate the attendance report.';
        }
        $stmt->close();
    } else {
        $attendanceQueryError = 'Could not prepare the attendance report query.';
    }
}
?>
<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
main.reports-page{min-height:100vh;color:#edf1fb;box-sizing:border-box}
.reports-page *,.reports-page *::before,.reports-page *::after{box-sizing:border-box}
.reports-wrap{width:100%;max-width:1450px;margin:0 auto}
.reports-panel{width:100%;min-width:0;margin-bottom:25px;padding:26px;border:1px solid #2d3a50;border-radius:18px;background:#172235;box-shadow:0 12px 28px rgba(0,0,0,.12)}
.reports-panel-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;margin-bottom:22px}
.reports-panel-header h1{margin:0;color:#fff;font-size:25px;font-weight:800}
.reports-description{margin:9px 0 0;color:#a8b6ce;font-size:13px;line-height:1.6}
.reports-filter{display:grid;grid-template-columns:repeat(3,minmax(140px,1fr)) auto;gap:16px;align-items:end;padding:24px;border:1px solid #34445d;border-radius:14px;background:#111c2e;margin-bottom:18px}
.reports-filter-field{display:flex;flex-direction:column;gap:9px;min-width:0}
.reports-filter-field label{color:#edf1fb;font-size:13px;font-weight:700}
.reports-filter-field select,.reports-filter-field input{width:100%;min-width:0;min-height:46px;padding:10px 12px;border:1px solid #3a4962;border-radius:10px;outline:none;background:#10192a;color:#f4f6ff;color-scheme:dark;font-family:inherit;font-size:13px}
.reports-actions{display:flex;gap:10px;align-items:center}
.reports-button,.reports-reset,.reports-print{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border:1px solid transparent;border-radius:10px;font-size:13px;font-weight:750;text-decoration:none;cursor:pointer;white-space:nowrap}
.reports-button{background:#9c83ff;color:#11152b}.reports-reset{background:#28364d;color:#f4f6ff;border-color:#43516a}.reports-print{background:#302a55;color:#f3edff;border-color:#7664bb}
.reports-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:18px 0 22px}
.reports-stat{min-width:0;padding:22px;border:1px solid #2d3a50;border-radius:16px;background:#172235}
.reports-stat-label{margin:0 0 14px;color:#baa5ff;font-size:13px;font-weight:750}.reports-stat-number{color:#fff;font-size:32px;font-weight:800}
.reports-table-scroll{width:100%;overflow-x:auto;border:1px solid #2d3a50;border-radius:12px}
.reports-table{width:100%;border-collapse:collapse;min-width:760px;background:#172235}
.reports-table th{padding:17px 18px;text-align:left;background:#243550;color:#e8e3ff;font-size:12px;font-weight:800;white-space:nowrap}
.reports-table td{padding:16px 18px;border-top:1px solid #2d3a50;color:#eaf0fb;font-size:13px}
.reports-empty{text-align:center;color:#a8b6ce!important;padding:28px!important}
@media(max-width:1000px){.reports-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.reports-actions{flex-wrap:wrap}}
@media(max-width:650px){.reports-panel{padding:16px}.reports-filter,.reports-summary{grid-template-columns:1fr}.reports-actions>*{flex:1}}
@media print{body *{visibility:hidden!important}.reports-page,.reports-page *{visibility:visible!important}.reports-page{position:absolute;left:0;top:0;width:100%;color:#111!important;background:#fff!important}.reports-panel,.reports-stat,.reports-table{background:#fff!important;color:#111!important;box-shadow:none!important;border-color:#ccc!important}.reports-panel-header h1,.reports-stat-number,.reports-stat-label,.reports-table td,.reports-table th,.reports-description{color:#111!important}.reports-filter,.reports-print,.reports-reset,.reports-button,.navbar,.sidebar{display:none!important}.reports-table th{background:#eee!important}}
</style>

<main class="reports-page">
  <div class="reports-wrap">
    <section class="reports-panel" id="attendance-reports">
      <div class="reports-panel-header">
        <div>
          <h1>Student Attendance Reports</h1>
          <p class="reports-description">Generate weekly, monthly, or semester-wise attendance for each CSE section.</p>
        </div>
        <button type="button" class="reports-print" onclick="window.print()">Print Report</button>
      </div>

      <?php if ($attendanceDateError !== ''): ?><p class="reports-description" style="color:#ffd2a8"><?= reportEscape($attendanceDateError) ?></p><?php endif; ?>
      <?php if ($attendanceQueryError !== ''): ?><p class="reports-description" style="color:#ffb4b4"><?= reportEscape($attendanceQueryError) ?></p><?php endif; ?>

      <form method="GET" action="reports.php#attendance-reports" class="reports-filter" id="attendanceFilter">
        <div class="reports-filter-field">
          <label for="attendance_period">Report period</label>
          <select name="attendance_period" id="attendance_period">
            <option value="weekly" <?= $attendancePeriod === 'weekly' ? 'selected' : '' ?>>Weekly</option>
            <option value="monthly" <?= $attendancePeriod === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            <option value="semester" <?= $attendancePeriod === 'semester' ? 'selected' : '' ?>>Semester</option>
          </select>
        </div>
        <div class="reports-filter-field">
          <label for="attendance_section">Section</label>
          <select name="attendance_section" id="attendance_section">
            <option value="0" <?= $attendanceSection === 0 ? 'selected' : '' ?>>All Sections</option>
            <?php if ($sectionOptions): while ($secOption = $sectionOptions->fetch_assoc()): ?>
              <option value="<?= (int)$secOption['section_id'] ?>" <?= $attendanceSection === (int)$secOption['section_id'] ? 'selected' : '' ?>><?= reportEscape($secOption['section_name']) ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <div class="reports-filter-field attendance-date-field" data-for="weekly">
          <label for="week_start">Week starting date</label>
          <input type="date" id="week_start" name="week_start" value="<?= reportEscape($attendanceWeekStart) ?>">
        </div>
        <div class="reports-filter-field attendance-date-field" data-for="monthly">
          <label for="report_month">Month</label>
          <input type="month" id="report_month" name="report_month" value="<?= reportEscape($attendanceMonth) ?>">
        </div>
        <div class="reports-filter-field attendance-date-field" data-for="semester">
          <label for="semester_start">Semester start</label>
          <input type="date" id="semester_start" name="semester_start" value="<?= reportEscape($attendanceSemesterStart) ?>">
        </div>
        <div class="reports-filter-field attendance-date-field" data-for="semester">
          <label for="semester_end">Semester end</label>
          <input type="date" id="semester_end" name="semester_end" value="<?= reportEscape($attendanceSemesterEnd) ?>">
        </div>
        <div class="reports-actions">
          <button type="submit" class="reports-button">Generate Attendance</button>
          <a href="reports.php#attendance-reports" class="reports-reset">Reset</a>
        </div>
      </form>

      <p class="reports-description" style="margin:0 0 16px"><?= reportEscape($attendancePeriodLabel) ?>. Present and absent days are counted from attendance records saved during the selected period.</p>

      <div class="reports-summary">
        <div class="reports-stat"><h2 class="reports-stat-label">Students Listed</h2><div class="reports-stat-number"><?= (int)$attendanceSummary['students'] ?></div></div>
        <div class="reports-stat"><h2 class="reports-stat-label">Overall Attendance</h2><div class="reports-stat-number"><?= number_format($attendanceSummary['percentage'], 2) ?>%</div></div>
      </div>

      <div class="reports-table-scroll">
        <table class="reports-table">
          <thead><tr><th>Section</th><th>Register Number</th><th>Student Name</th><th>Present Days</th><th>Absent Days</th><th>Attendance %</th></tr></thead>
          <tbody>
          <?php if (count($attendanceRows) > 0): foreach ($attendanceRows as $arow): ?>
            <tr>
              <td><?= reportEscape($arow['section_name']) ?></td>
              <td><strong><?= reportEscape($arow['register_number']) ?></strong></td>
              <td><?= reportEscape($arow['student_name']) ?></td>
              <td><?= (int)$arow['present_days'] ?></td>
              <td><?= (int)$arow['absent_days'] ?></td>
              <td><strong><?= number_format($arow['percentage'], 2) ?>%</strong></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="reports-empty">No students found for the selected section.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</main>

<script>
(function () {
  const periodSelect = document.getElementById('attendance_period');
  const fields = document.querySelectorAll('.attendance-date-field');
  function updateAttendanceFields() {
    const selected = periodSelect.value;
    fields.forEach(function (field) {
      const active = field.dataset.for === selected;
      field.style.display = active ? 'flex' : 'none';
      field.querySelectorAll('input').forEach(function (input) { input.disabled = !active; });
    });
  }
  periodSelect.addEventListener('change', updateAttendanceFields);
  updateAttendanceFields();
})();
</script>

<?php require_once "../includes/footer.php"; ?>