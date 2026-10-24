<?php
/**
 * SmartFaculty Portal — Timetable Upload & Management
 * 
 * Secure file upload and import system for Admin / HOD:
 * - Formats: PDF, Excel (.xlsx, .xls), CSV (.csv), Images (.png, .jpg, .jpeg)
 * - Drag-and-drop file picker
 * - Reference mapping to smartfacultyportal schema
 * - Interactive editable preview table
 * - Server-side validation and database transactions
 */

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";
require_once "../config/config.php";
require_once "../includes/TimetableParser.php";

$pageTitle = "Timetable Management";

// CSRF Token protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$parser = new TimetableParser($conn);
$refData = $parser->getReferenceData();
$sections = $refData['sections'];
$periods = $refData['periods'];
$subjects = $refData['subjects'];
$faculty = $refData['faculty'];
$classrooms = $refData['classrooms'];
$validDays = $refData['days'];

$notice = null;
$error = null;
$previewRows = null;
$fileMeta = null;
$selectedSectionId = (int)($_POST['section_id'] ?? ($_GET['section_id'] ?? 0));
$conflictMode = $_POST['conflict_mode'] ?? 'replace'; // 'replace' or 'skip'
$defaultStatus = $_POST['default_status'] ?? 'PUBLISHED';

/* ==========================================================================
   HANDLE STEP 2: CONFIRM IMPORT
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    // Validate CSRF
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } elseif ($selectedSectionId <= 0 || !isset($sections[$selectedSectionId])) {
        $error = "Please select a valid section for import.";
    } else {
        $submittedRows = $_POST['rows'] ?? [];
        if (empty($submittedRows) || !is_array($submittedRows)) {
            $error = "No timetable rows to import.";
        } else {
            // Begin Transaction
            $conn->begin_transaction();
            try {
                $importedCount = 0;
                $skippedCount = 0;
                $replacedCount = 0;

                // Prepared Statements
                $checkExistingStmt = $conn->prepare("
                    SELECT timetable_id FROM timetable
                    WHERE section_id = ? AND day = ? AND period_id = ?
                    LIMIT 1
                ");

                $deleteExistingStmt = $conn->prepare("
                    DELETE FROM timetable
                    WHERE section_id = ? AND day = ? AND period_id = ?
                ");

                $checkFacultyCollisionStmt = $conn->prepare("
                    SELECT t.timetable_id, sec.section_name, s.subject_code
                    FROM timetable t
                    INNER JOIN sections sec ON sec.section_id = t.section_id
                    INNER JOIN subjects s ON s.subject_id = t.subject_id
                    WHERE t.faculty_id = ? AND t.day = ? AND t.period_id = ? AND t.section_id != ?
                    LIMIT 1
                ");

                $checkRoomCollisionStmt = $conn->prepare("
                    SELECT t.timetable_id, sec.section_name
                    FROM timetable t
                    INNER JOIN sections sec ON sec.section_id = t.section_id
                    WHERE t.classroom_id = ? AND t.day = ? AND t.period_id = ? AND t.section_id != ?
                    LIMIT 1
                ");

                $insertStmt = $conn->prepare("
                    INSERT INTO timetable (day, period_id, section_id, subject_id, faculty_id, classroom_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                // Period lookup cache
                $periodMapByNumber = [];
                foreach ($periods as $pId => $p) {
                    $periodMapByNumber[(int)$p['period_number']] = $pId;
                }

                $seenSlots = [];

                foreach ($submittedRows as $row) {
                    // Extract and sanitize
                    $day = strtoupper(trim($row['day'] ?? ''));
                    $periodNum = (int)($row['period_number'] ?? 0);
                    $subjectId = (int)($row['subject_id'] ?? 0);
                    $facultyId = (int)($row['faculty_id'] ?? 0);
                    $classroomId = (int)($row['classroom_id'] ?? 0);
                    $status = strtoupper(trim($row['status'] ?? 'PUBLISHED'));

                    if (!in_array($day, $validDays, true)) {
                        throw new Exception("Invalid day specified: " . htmlspecialchars($day));
                    }

                    if (!isset($periodMapByNumber[$periodNum])) {
                        throw new Exception("Invalid period number: P" . $periodNum);
                    }
                    $periodId = $periodMapByNumber[$periodNum];

                    // Check for duplicate slot in same batch submission
                    $slotKey = "{$day}_{$periodId}";
                    if (isset($seenSlots[$slotKey])) {
                        throw new Exception("Duplicate entry in submission for {$day} Period {$periodNum}. Each slot can only have one assigned class.");
                    }
                    $seenSlots[$slotKey] = true;

                    // Check existing timetable entry for this section
                    $checkExistingStmt->bind_param("isi", $selectedSectionId, $day, $periodId);
                    $checkExistingStmt->execute();
                    $checkRes = $checkExistingStmt->get_result();
                    $existingRow = $checkRes ? $checkRes->fetch_assoc() : null;

                    if ($existingRow) {
                        if ($conflictMode === 'skip') {
                            // Slot is occupied and policy is skip -> safely skip this slot and leave existing DB record untouched
                            $skippedCount++;
                            continue;
                        } else {
                            // Policy is 'replace' -> delete the existing slot
                            $deleteExistingStmt->bind_param("isi", $selectedSectionId, $day, $periodId);
                            if (!$deleteExistingStmt->execute()) {
                                throw new Exception("Failed to replace existing schedule slot for {$day} P{$periodNum}.");
                            }
                            $replacedCount++;
                        }
                    }

                    // Validate foreign keys for entries that will be inserted
                    if (!isset($subjects[$subjectId])) {
                        throw new Exception("Invalid subject selected for {$day} P{$periodNum}.");
                    }

                    if (!isset($faculty[$facultyId])) {
                        throw new Exception("Invalid faculty selected for {$day} P{$periodNum}.");
                    }

                    if (!isset($classrooms[$classroomId])) {
                        throw new Exception("Invalid classroom selected for {$day} P{$periodNum}.");
                    }

                    if (!in_array($status, ['PUBLISHED', 'DRAFT'], true)) {
                        $status = 'PUBLISHED';
                    }

                    // Check external collisions with other sections
                    if ($checkFacultyCollisionStmt) {
                        $checkFacultyCollisionStmt->bind_param("isii", $facultyId, $day, $periodId, $selectedSectionId);
                        $checkFacultyCollisionStmt->execute();
                        $facColRes = $checkFacultyCollisionStmt->get_result();
                        if ($facColRes && $facColRes->num_rows > 0) {
                            $facCol = $facColRes->fetch_assoc();
                            $facName = $faculty[$facultyId]['faculty_name'] ?? 'Faculty';
                            throw new Exception("Collision: {$facName} is already assigned to Section {$facCol['section_name']} ({$facCol['subject_code']}) at {$day} P{$periodNum}.");
                        }
                    }

                    if ($checkRoomCollisionStmt) {
                        $checkRoomCollisionStmt->bind_param("isii", $classroomId, $day, $periodId, $selectedSectionId);
                        $checkRoomCollisionStmt->execute();
                        $roomColRes = $checkRoomCollisionStmt->get_result();
                        if ($roomColRes && $roomColRes->num_rows > 0) {
                            $roomCol = $roomColRes->fetch_assoc();
                            $roomNum = $classrooms[$classroomId]['room_number'] ?? 'Room';
                            throw new Exception("Collision: Room {$roomNum} is already assigned to Section {$roomCol['section_name']} at {$day} P{$periodNum}.");
                        }
                    }

                    // Insert new valid timetable row
                    $insertStmt->bind_param("siiiiis", $day, $periodId, $selectedSectionId, $subjectId, $facultyId, $classroomId, $status);
                    if (!$insertStmt->execute()) {
                        throw new Exception("Failed to insert timetable row for {$day} P{$periodNum}: " . $insertStmt->error);
                    }
                    $importedCount++;
                }

                $checkExistingStmt->close();
                $deleteExistingStmt->close();
                if ($checkFacultyCollisionStmt) $checkFacultyCollisionStmt->close();
                if ($checkRoomCollisionStmt) $checkRoomCollisionStmt->close();
                $insertStmt->close();

                // Commit Transaction
                $conn->commit();

                $secName = $sections[$selectedSectionId]['section_name'] ?? "Section #{$selectedSectionId}";
                $notice = [
                    'type' => 'success',
                    'title' => 'Timetable Import Successful!',
                    'message' => "Successfully imported <strong>{$importedCount}</strong> timetable entries for <strong>{$secName}</strong>." .
                                 ($replacedCount > 0 ? " (Replaced {$replacedCount} existing slots)" : "") .
                                 ($skippedCount > 0 ? " ({$skippedCount} conflicting slots skipped)" : ""),
                    'section_id' => $selectedSectionId,
                    'section_name' => $secName
                ];
                // Reset preview
                $previewRows = null;
            } catch (\Throwable $e) {
                $conn->rollback();
                $error = "Import failed and database was safely rolled back: " . $e->getMessage();
            }
        }
    }
}

/* ==========================================================================
   HANDLE STEP 1: FILE UPLOAD AND EXTRACTION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    // Validate CSRF
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $error = "Session expired or invalid token. Please refresh and try again.";
    } elseif ($selectedSectionId <= 0 || !isset($sections[$selectedSectionId])) {
        $error = "Please select a target section from the dropdown.";
    } elseif (!isset($_FILES['timetable_file']) || $_FILES['timetable_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['timetable_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMap = [
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize limit.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "Please choose a timetable file to upload.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder on server.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
        ];
        $error = $errorMap[$uploadError] ?? "File upload error occurred.";
    } else {
        $file = $_FILES['timetable_file'];
        $origName = $file['name'];
        $fileSize = $file['size'];
        $tmpPath = $file['tmp_name'];

        // Size check (10MB max)
        $maxBytes = 10 * 1024 * 1024;
        if ($fileSize > $maxBytes) {
            $error = "File size exceeds 10 MB limit (" . round($fileSize / (1024 * 1024), 2) . " MB uploaded).";
        } else {
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExtensions = ['xlsx', 'xls', 'csv', 'pdf', 'png', 'jpg', 'jpeg'];

            if (!in_array($ext, $allowedExtensions, true)) {
                $error = "Unsupported file type (.$ext). Supported formats: Excel (.xlsx, .xls), CSV (.csv), PDF (.pdf), Images (.png, .jpg, .jpeg).";
            } else {
                // Ensure upload temp directory exists with restricted access
                $uploadDir = __DIR__ . '/../uploads/temp';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                    @file_put_contents($uploadDir . '/.htaccess', "Deny from all\n");
                    @file_put_contents($uploadDir . '/index.html', "");
                }

                $safeFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath = $uploadDir . '/' . $safeFilename;

                if (!move_uploaded_file($tmpPath, $destPath)) {
                    $error = "Failed to store uploaded file safely on the server.";
                } else {
                    // Parse file using TimetableParser with selected conflict policy
                    $parseResult = $parser->parseFile($destPath, $ext, $selectedSectionId, $defaultStatus, $conflictMode);

                    // Delete the temp file now that parsing is complete
                    @unlink($destPath);

                    if (!$parseResult['success']) {
                        $error = $parseResult['message'];
                    } else {
                        $previewRows = $parseResult['rows'];
                        $fileMeta = [
                            'original_name' => $origName,
                            'size' => $fileSize,
                            'format' => $parseResult['format'],
                            'total_rows' => count($previewRows)
                        ];
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

<style>
/* ==========================================================================
   PREMIUM DARK TIMETABLE UPLOAD STYLES
   Adheres to HOD theme: #0c1220, #172235, #2a364b, #9a83ff, Inter font
   ========================================================================== */
main.tt-upload-page {
    color: #edf1fb;
    min-height: 100vh;
    box-sizing: border-box;
}

.tt-upload-page *,
.tt-upload-page *::before,
.tt-upload-page *::after {
    box-sizing: border-box;
}

/* Page Header */
.tt-upload-page .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    padding: 28px 32px;
    margin-bottom: 25px;
    border: 1px solid #51478c;
    border-radius: 18px;
    background: linear-gradient(115deg, #28244e, #39316e 60%, #574898);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.tt-upload-page .page-header h1 {
    color: #ffffff;
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}

.tt-upload-page .page-header p {
    color: #ddd5f6;
    font-size: 13px;
    margin: 0;
    line-height: 1.6;
}

.tt-upload-page .page-header .header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.tt-upload-page .btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border: 1px solid #9183bd;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
    transition: background 0.2s ease, transform 0.2s ease;
}

.tt-upload-page .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.16);
    transform: translateY(-2px);
    color: #ffffff;
}

/* Cards */
.tt-upload-page .card {
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 18px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.14);
}

.tt-upload-page .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    padding-bottom: 15px;
    border-bottom: 1px solid #28374d;
    flex-wrap: wrap;
    gap: 12px;
}

.tt-upload-page .card-header h2 {
    color: #ffffff;
    font-size: 19px;
    font-weight: 750;
    margin: 0;
}

/* Upload Dropzone */
.tt-dropzone {
    border: 2px dashed #4b4279;
    background: #111a2b;
    border-radius: 16px;
    padding: 40px 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
    position: relative;
}

.tt-dropzone:hover,
.tt-dropzone.dragover {
    border-color: #a58aff;
    background: #172036;
    transform: translateY(-2px);
}

.tt-dropzone-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    border-radius: 14px;
    background: linear-gradient(135deg, #3d3170, #2a2252);
    display: grid;
    place-items: center;
    color: #c5b4ff;
}

.tt-dropzone-icon svg {
    width: 32px;
    height: 32px;
}

.tt-dropzone h3 {
    font-size: 16px;
    font-weight: 750;
    color: #f1f5ff;
    margin-bottom: 6px;
}

.tt-dropzone p {
    color: #9ba9c1;
    font-size: 13px;
    margin: 0 0 16px;
}

.tt-file-selected {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #252d48;
    border: 1px solid #544988;
    border-radius: 10px;
    padding: 8px 16px;
    color: #e5ddff;
    font-size: 13px;
    font-weight: 700;
    margin-top: 10px;
}

/* Format Pills */
.tt-formats {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.tt-format-pill {
    background: #1e293f;
    border: 1px solid #334461;
    color: #a8b8d4;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.4px;
}

/* Upload Settings Grid */
.tt-upload-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-top: 24px;
}

.tt-form-group {
    display: flex;
    flex-direction: column;
}

.tt-form-group label {
    font-size: 12px;
    font-weight: 750;
    color: #d2dbec;
    margin-bottom: 8px;
    letter-spacing: 0.3px;
}

.tt-form-group select,
.tt-form-group input {
    width: 100%;
    min-height: 46px;
    padding: 10px 14px;
    background: #10192a;
    border: 1px solid #3a4962;
    border-radius: 10px;
    color: #f4f6ff;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.tt-form-group select:focus,
.tt-form-group input:focus {
    border-color: #a58aff;
    box-shadow: 0 0 0 3px rgba(165, 138, 255, 0.15);
}

.tt-form-group select option {
    background: #10192a;
    color: #ffffff;
}

.tt-form-group small {
    color: #8c9cb6;
    font-size: 11px;
    margin-top: 6px;
    line-height: 1.4;
}

/* Template Download Card */
.tt-template-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #111a2b;
    border: 1px solid #28374d;
    border-radius: 12px;
    padding: 16px 20px;
    margin-top: 22px;
    flex-wrap: wrap;
    gap: 14px;
}

.tt-template-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tt-template-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #25334d;
    display: grid;
    place-items: center;
    color: #9cb3d8;
}

.tt-template-info strong {
    display: block;
    font-size: 13px;
    color: #e5edfa;
}

.tt-template-info span {
    font-size: 12px;
    color: #8f9fb7;
}

.tt-template-links {
    display: flex;
    gap: 10px;
}

.tt-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: transparent;
    border: 1px solid #485b7c;
    border-radius: 8px;
    color: #c9d8ee;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}

.tt-btn-outline:hover {
    background: #202e47;
    border-color: #8298be;
    color: #ffffff;
}

/* KPI Summary Cards */
.tt-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1200px) {
    .tt-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .tt-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .tt-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.tt-kpi-card {
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 14px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
}

.tt-kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.tt-kpi-card.kpi-total::before { background: #8e7bf5; }
.tt-kpi-card.kpi-ready::before { background: #3ecf8e; }
.tt-kpi-card.kpi-skipped::before { background: #38bdf8; }
.tt-kpi-card.kpi-warning::before { background: #f5a623; }
.tt-kpi-card.kpi-error::before { background: #ff5277; }

.tt-kpi-label {
    font-size: 11px;
    font-weight: 750;
    color: #98a7be;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    display: block;
    margin-bottom: 8px;
}

.tt-kpi-val {
    font-size: 28px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1;
}

/* Preview Table */
.tt-table-container {
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 16px;
    overflow-x: auto;
    margin-bottom: 24px;
}

.tt-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    color: #d8e0ed;
    min-width: 1000px;
}

.tt-preview-table th {
    background: #1e2b42;
    color: #ded4ff;
    padding: 14px 12px;
    font-weight: 750;
    text-align: left;
    border-bottom: 1px solid #2d3b51;
    white-space: nowrap;
}

.tt-preview-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #243247;
    background: #172235;
    vertical-align: middle;
}

.tt-preview-table tbody tr:hover td {
    background: #1b263b;
}

.tt-preview-table tr.row-has-error td {
    background: rgba(255, 82, 119, 0.08) !important;
}

.tt-preview-table tr.row-has-warning td {
    background: rgba(245, 166, 35, 0.05);
}

.tt-preview-table tr.row-is-skipped td {
    background: rgba(56, 189, 248, 0.04);
}

.tt-preview-table tr.row-is-skipped select {
    opacity: 0.72;
    border-color: #1e3a5f;
    background: #0f172a;
}

.tt-preview-table tr.row-is-replace td {
    background: rgba(245, 166, 35, 0.06);
}

/* Interactive Table Form Controls */
.tt-select-compact {
    width: 100%;
    min-height: 38px;
    padding: 6px 10px;
    background: #10192a;
    border: 1px solid #334461;
    border-radius: 8px;
    color: #f4f6ff;
    font-family: inherit;
    font-size: 12px;
    outline: none;
    transition: border-color 0.2s ease;
}

.tt-select-compact:focus {
    border-color: #a58aff;
    box-shadow: 0 0 0 2px rgba(165, 138, 255, 0.2);
}

.tt-select-compact.field-unmatched {
    border-color: #f5a623;
    background: #252014;
    color: #ffe6aa;
}

.tt-select-compact.field-error {
    border-color: #ff5277;
    background: #2c171e;
    color: #ffc9d4;
}

/* Badges */
.tt-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.tt-badge-success { background: #1a4233; color: #7cf0be; border: 1px solid #2b6e55; }
.tt-badge-warning { background: #3d2f13; color: #ffdc7a; border: 1px solid #6c5421; }
.tt-badge-danger { background: #4a1f28; color: #ff9fb4; border: 1px solid #823445; }
.tt-badge-info { background: #26294d; color: #c0b3ff; border: 1px solid #4a4583; }
.tt-badge-skipped { background: #0f2942; color: #38bdf8; border: 1px solid #0284c7; }
.tt-badge-replace { background: #3d2f13; color: #fcd34d; border: 1px solid #b45309; }

/* Status Reason Information */
.tt-reason {
    font-size: 11px;
    margin-top: 5px;
    line-height: 1.35;
    max-width: 290px;
    word-break: break-word;
}
.tt-reason-error { color: #ff9fb4; }
.tt-reason-skipped { color: #7dd3fc; }
.tt-reason-replace { color: #fde68a; }
.tt-reason-warning { color: #fcd34d; }
.tt-reason-success { color: #86efac; }

/* Conflict Policy Switcher in Card Header */
.tt-policy-switcher {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #10192a;
    border: 1px solid #334461;
    border-radius: 10px;
    padding: 6px 12px;
}
.tt-policy-label {
    font-size: 12px;
    font-weight: 750;
    color: #c5b4ff;
    white-space: nowrap;
}
.tt-select-policy {
    background: #172235;
    border: 1px solid #475569;
    color: #f1f5f9;
    font-size: 12px;
    font-weight: 700;
    border-radius: 6px;
    padding: 5px 10px;
    outline: none;
    cursor: pointer;
    transition: border-color 0.2s ease;
}
.tt-select-policy:focus {
    border-color: #9a83ff;
}

/* Alerts */
.tt-alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 13px;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.tt-alert-success {
    background: #183a2d;
    border: 1px solid #2b6f56;
    color: #c9f9e3;
}

.tt-alert-error {
    background: #461f2a;
    border: 1px solid #86374a;
    color: #ffcdd8;
}

.tt-alert-warning {
    background: #3c2e17;
    border: 1px solid #735527;
    color: #ffebbe;
}

/* Actions Bottom Bar */
.tt-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 14px;
    padding: 20px 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.tt-actions-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tt-actions-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-primary-gradient {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(115deg, #b8a6ff, #947cfa);
    color: #191330;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: filter 0.2s ease, transform 0.2s ease;
    text-decoration: none;
}

.btn-primary-gradient:hover {
    filter: brightness(1.1);
    transform: translateY(-2px);
    color: #191330;
}

.btn-delete-row {
    background: transparent;
    border: 1px solid #57313e;
    color: #ff91a4;
    padding: 6px 10px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
    transition: all 0.2s ease;
}

.btn-delete-row:hover {
    background: #50222f;
    color: #ffffff;
}

.btn-add-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #1a273b;
    border: 1px dashed #4f6386;
    color: #c4d7f5;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 750;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.2s ease;
}

.btn-add-row:hover {
    background: #233550;
    border-color: #8da4cb;
    color: #ffffff;
}

/* Loading Overlay */
#tt-loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(12, 18, 32, 0.85);
    z-index: 9999;
    place-items: center;
    backdrop-filter: blur(4px);
}

.tt-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #332b57;
    border-top-color: #a58aff;
    border-radius: 50%;
    animation: tt-spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

@keyframes tt-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
    .tt-upload-grid {
        grid-template-columns: 1fr;
    }
    .tt-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Loading Spinner Overlay -->
<div id="tt-loading-overlay">
    <div style="text-align: center;">
        <div class="tt-spinner"></div>
        <h3 style="color: #ffffff; font-size: 18px; margin-bottom: 6px;">Extracting Timetable Data...</h3>
        <p style="color: #a5b5d1; font-size: 13px;">Analyzing schedule structure and mapping reference entities</p>
    </div>
</div>

<main class="tt-upload-page">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1>Timetable Management</h1>
            <p>Upload, validate, and import timetable schedules for academic sections.</p>
        </div>
        <div class="header-actions">
            <a href="timetable.php" class="btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                View Timetable List
            </a>
            <?php if ($selectedSectionId > 0): ?>
                <a href="../student/timetable.php" target="_blank" class="btn-secondary" title="Preview Student View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    Student View
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ERROR NOTIFICATION -->
    <?php if ($error !== null): ?>
        <div class="tt-alert tt-alert-error">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div>
                <strong>Error Encountered</strong><br>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- SUCCESS NOTIFICATION -->
    <?php if ($notice !== null && $notice['type'] === 'success'): ?>
        <div class="tt-alert tt-alert-success">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div>
                <strong style="font-size: 15px;"><?= $notice['title'] ?></strong><br>
                <span><?= $notice['message'] ?></span>
                <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <a href="timetable.php" class="tt-btn-outline" style="border-color: #3ecf8e; color: #a4f5d0;">
                        Open Timetable Management
                    </a>
                    <a href="../student/timetable.php" target="_blank" class="tt-btn-outline" style="border-color: #a58aff; color: #dfd4ff;" title="Student timetable requires an active student session (e.g. student01)">
                        View Live Student Timetable ↗
                    </a>
                </div>
                <div style="margin-top: 8px; font-size: 11px; color: #b7c7e0;">
                    ℹ <em>Note: The Student Timetable portal is role-protected. To view as an enrolled student, sign in with an authorized student account (e.g., <code>student01</code> for Section <?= htmlspecialchars($notice['section_name'] ?? 'CSE-A', ENT_QUOTES, 'UTF-8') ?>).</em>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($previewRows === null): ?>
        <!-- ====================================================================
             STEP 1: UPLOAD & CONFIGURATION CARD
             ==================================================================== -->
        <div class="card">
            <div class="card-header">
                <h2>Upload Timetable File</h2>
                <span class="tt-badge tt-badge-info">Step 1 of 2: Upload & Preview</span>
            </div>

            <form method="POST" enctype="multipart/form-data" id="tt-upload-form" onsubmit="showLoading()">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Drag-and-drop Zone -->
                <div class="tt-dropzone" id="tt-dropzone" onclick="document.getElementById('timetable_file').click()">
                    <div class="tt-dropzone-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <h3>Drag and drop your Timetable file here</h3>
                    <p>or click to browse files from your computer</p>

                    <input
                        type="file"
                        id="timetable_file"
                        name="timetable_file"
                        accept=".xlsx,.xls,.csv,.pdf,.png,.jpg,.jpeg"
                        style="display: none;"
                        required
                        onchange="handleFileSelected(this)"
                    >

                    <div id="tt-file-badge" class="tt-file-selected" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                        <span id="tt-selected-name">No file selected</span>
                        <span id="tt-selected-size" style="color: #9cb0d0; font-weight: normal;"></span>
                    </div>

                    <div class="tt-formats">
                        <span class="tt-format-pill">PDF (.pdf)</span>
                        <span class="tt-format-pill">Excel (.xlsx, .xls)</span>
                        <span class="tt-format-pill">CSV (.csv)</span>
                        <span class="tt-format-pill">Images (.png, .jpg, .jpeg)</span>
                        <span class="tt-format-pill" style="border-color: #55447a; color: #c4b5f8;">Max 10 MB</span>
                    </div>
                </div>

                <!-- Settings: Section, Conflict Mode, Status -->
                <div class="tt-upload-grid">
                    <div class="tt-form-group">
                        <label for="section_id">Target Section <span style="color: #ff6b8b;">*</span></label>
                        <select name="section_id" id="section_id" required>
                            <option value="">-- Choose Academic Section --</option>
                            <?php foreach ($sections as $sId => $sec): ?>
                                <option value="<?= $sId ?>" <?= $selectedSectionId === $sId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Timetable slots will be assigned to this section.</small>
                    </div>

                    <div class="tt-form-group">
                        <label for="conflict_mode">Existing Timetable Conflict Policy</label>
                        <select name="conflict_mode" id="conflict_mode">
                            <option value="replace" <?= $conflictMode === 'replace' ? 'selected' : '' ?>>
                                Replace existing section slots (Recommended)
                            </option>
                            <option value="skip" <?= $conflictMode === 'skip' ? 'selected' : '' ?>>
                                Skip slots that already have classes
                            </option>
                        </select>
                        <small>Decide how to handle overlapping section schedule entries.</small>
                    </div>

                    <div class="tt-form-group">
                        <label for="default_status">Default Publish Status</label>
                        <select name="default_status" id="default_status">
                            <option value="PUBLISHED" <?= $defaultStatus === 'PUBLISHED' ? 'selected' : '' ?>>PUBLISHED (Live for Students)</option>
                            <option value="DRAFT" <?= $defaultStatus === 'DRAFT' ? 'selected' : '' ?>>DRAFT (Admin Only)</option>
                        </select>
                        <small>Published entries are immediately visible on the Student portal.</small>
                    </div>
                </div>

                <!-- Action Button -->
                <div style="margin-top: 26px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-primary-gradient" id="btn-upload-submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        Upload and Preview Timetable
                    </button>
                </div>
            </form>

            <!-- Download Sample Templates -->
            <div class="tt-template-box">
                <div class="tt-template-info">
                    <div class="tt-template-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div>
                        <strong>Need a ready-to-use timetable template?</strong>
                        <span>Download sample templates pre-filled with valid subjects, faculty, and classrooms from this portal.</span>
                    </div>
                </div>
                <div class="tt-template-links">
                    <a href="timetable-template.php?format=csv" class="tt-btn-outline" download>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Download CSV (.csv)
                    </a>
                    <a href="timetable-template.php?format=xlsx" class="tt-btn-outline" download>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Download Excel (.xlsx)
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ====================================================================
             STEP 2: EDITABLE PREVIEW & CONFIRMATION
             ==================================================================== -->
        <?php
        $totalExtracted = count($previewRows);
        $countReady = 0;
        $countSkipped = 0;
        $countWarnings = 0;
        $countErrors = 0;

        foreach ($previewRows as $r) {
            $hasError = !empty($r['errors']);
            $hasExisting = !empty($r['conflict']) && in_array($r['conflict']['type'] ?? '', ['existing_entry_skip', 'existing_entry_replace'], true);
            $hasWarning = !empty($r['warnings']) || ($r['subject_id'] === 0 || $r['faculty_id'] === 0 || $r['classroom_id'] === 0);

            if ($hasError) {
                $countErrors++;
            } elseif ($conflictMode === 'skip' && $hasExisting) {
                $countSkipped++;
            } elseif ($conflictMode === 'replace' && $hasExisting) {
                $countWarnings++; // notice: slot will be replaced
                if (!$hasWarning) {
                    $countReady++;
                }
            } else {
                if ($hasWarning) {
                    $countWarnings++;
                }
                if (!$hasWarning) {
                    $countReady++;
                }
            }
        }
        $targetSectionName = $sections[$selectedSectionId]['section_name'] ?? "Section #{$selectedSectionId}";
        ?>

        <!-- KPI SUMMARY TILES -->
        <div class="tt-kpi-grid">
            <div class="tt-kpi-card kpi-total">
                <span class="tt-kpi-label">Total Extracted</span>
                <div class="tt-kpi-val" id="kpi-total-val"><?= $totalExtracted ?></div>
            </div>
            <div class="tt-kpi-card kpi-ready">
                <span class="tt-kpi-label">Ready to Import</span>
                <div class="tt-kpi-val" id="kpi-ready-val" style="color: #3ecf8e;"><?= $countReady ?></div>
            </div>
            <div class="tt-kpi-card kpi-skipped">
                <span class="tt-kpi-label">Skipped (Occupied)</span>
                <div class="tt-kpi-val" id="kpi-skipped-val" style="color: #38bdf8;"><?= $countSkipped ?></div>
            </div>
            <div class="tt-kpi-card kpi-warning">
                <span class="tt-kpi-label">Warnings / Notices</span>
                <div class="tt-kpi-val" id="kpi-warning-val" style="color: #f5a623;"><?= $countWarnings ?></div>
            </div>
            <div class="tt-kpi-card kpi-error">
                <span class="tt-kpi-label">Blocking Errors</span>
                <div class="tt-kpi-val" id="kpi-error-val" style="color: #ff5277;"><?= $countErrors ?></div>
            </div>
        </div>

        <form method="POST" id="tt-confirm-form" onsubmit="return validateBeforeConfirm();">
            <input type="hidden" name="action" value="confirm_import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="section_id" value="<?= $selectedSectionId ?>">
            <input type="hidden" name="conflict_mode" id="conflict_mode_input" value="<?= htmlspecialchars($conflictMode, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="default_status" value="<?= htmlspecialchars($defaultStatus, ENT_QUOTES, 'UTF-8') ?>">

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2>Timetable Preview & Validation</h2>
                        <span style="font-size: 13px; color: #9bb0cd;">
                            File: <strong><?= htmlspecialchars($fileMeta['original_name'] ?? 'Uploaded File', ENT_QUOTES, 'UTF-8') ?></strong>
                            (<?= round(($fileMeta['size'] ?? 0) / 1024, 1) ?> KB) — Target: <strong><?= htmlspecialchars($targetSectionName, ENT_QUOTES, 'UTF-8') ?></strong>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <!-- Interactive Conflict Policy Switcher -->
                        <div class="tt-policy-switcher">
                            <span class="tt-policy-label">Conflict Policy:</span>
                            <select id="preview_conflict_mode" class="tt-select-policy" onchange="switchConflictPolicy(this.value)">
                                <option value="skip" <?= $conflictMode === 'skip' ? 'selected' : '' ?>>
                                    ⏭ Skip slots with existing classes
                                </option>
                                <option value="replace" <?= $conflictMode === 'replace' ? 'selected' : '' ?>>
                                    ↻ Replace existing section slots
                                </option>
                            </select>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addNewPreviewRow()">
                            + Add Extra Row
                        </button>
                    </div>
                </div>

                <p style="font-size: 12px; color: #a2b2ce; margin-bottom: 16px;">
                    Review the extracted timetable below. You can switch the conflict policy, correct unmatched subjects, faculty, or classrooms directly using dropdowns before confirming the import.
                </p>

                <!-- EDITABLE TABLE -->
                <div class="tt-table-container">
                    <table class="tt-preview-table" id="tt-preview-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <th style="width: 120px;">Day</th>
                                <th style="width: 130px;">Period</th>
                                <th style="width: 170px;">Time</th>
                                <th style="min-width: 200px;">Subject</th>
                                <th style="min-width: 200px;">Faculty</th>
                                <th style="width: 150px;">Classroom</th>
                                <th style="width: 120px;">Status</th>
                                <th style="min-width: 250px;">Status / Reason Info</th>
                                <th style="width: 50px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tt-table-body">
                            <?php foreach ($previewRows as $idx => $r): ?>
                                <?php
                                $hasError = !empty($r['errors']);
                                $hasWarning = !empty($r['warnings']) || ($r['subject_id'] === 0 || $r['faculty_id'] === 0 || $r['classroom_id'] === 0);
                                $hasConflict = !empty($r['conflict']);
                                $isExisting = $hasConflict && in_array($r['conflict']['type'] ?? '', ['existing_entry_skip', 'existing_entry_replace'], true);

                                $skipMsg = '';
                                $replaceMsg = '';
                                if ($isExisting) {
                                    $exSub = $r['conflict']['existing_subject'] ?? 'Class';
                                    $exFac = $r['conflict']['existing_faculty'] ?? 'Faculty';
                                    $exRm = $r['conflict']['existing_room'] ?? 'Room';
                                    $exStat = $r['conflict']['existing_status'] ?? 'PUBLISHED';
                                    $skipMsg = "Slot occupied: Section already has {$exSub} ({$exFac}, Rm {$exRm}, {$exStat}). Will be SKIPPED.";
                                    $replaceMsg = "Slot occupied: Section already has {$exSub} ({$exFac}, Rm {$exRm}, {$exStat}). Will be REPLACED.";
                                }

                                $isSkipped = ($isExisting && $conflictMode === 'skip');
                                $isReplace = ($isExisting && $conflictMode === 'replace');

                                $rowClass = '';
                                if ($hasError) {
                                    $rowClass = 'row-has-error';
                                } elseif ($isSkipped) {
                                    $rowClass = 'row-is-skipped';
                                } elseif ($isReplace) {
                                    $rowClass = 'row-is-replace';
                                } elseif ($hasWarning) {
                                    $rowClass = 'row-has-warning';
                                }
                                ?>
                                <tr class="<?= $rowClass ?>" 
                                    id="preview-row-<?= $idx ?>"
                                    data-row-index="<?= $idx ?>"
                                    data-has-error="<?= $hasError ? '1' : '0' ?>"
                                    data-has-existing="<?= $isExisting ? '1' : '0' ?>"
                                    data-has-warning="<?= $hasWarning ? '1' : '0' ?>"
                                    data-msg-skip="<?= htmlspecialchars($skipMsg, ENT_QUOTES, 'UTF-8') ?>"
                                    data-msg-replace="<?= htmlspecialchars($replaceMsg, ENT_QUOTES, 'UTF-8') ?>">
                                    <!-- Index -->
                                    <td style="text-align: center; color: #8494ae; font-weight: 750;">
                                        <?= $idx + 1 ?>
                                    </td>

                                    <!-- Day -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][day]" class="tt-select-compact" required>
                                            <?php foreach ($validDays as $d): ?>
                                                <option value="<?= $d ?>" <?= $r['day'] === $d ? 'selected' : '' ?>>
                                                    <?= $d ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- Period -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][period_number]" class="tt-select-compact" onchange="updateRowTime(this, <?= $idx ?>)" required>
                                            <option value="">-- Period --</option>
                                            <?php for ($pNum = 1; $pNum <= 9; $pNum++): ?>
                                                <option value="<?= $pNum ?>" <?= (int)$r['period_number'] === $pNum ? 'selected' : '' ?>>
                                                    Period <?= $pNum ?> (P<?= $pNum ?>)
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>

                                    <!-- Time -->
                                    <td>
                                        <span id="row-time-<?= $idx ?>" style="font-size: 12px; color: #b7c7e0;">
                                            <?php
                                            $st = $r['start_time'] ? date("h:i A", strtotime($r['start_time'])) : '—';
                                            $et = $r['end_time'] ? date("h:i A", strtotime($r['end_time'])) : '—';
                                            echo "{$st} – {$et}";
                                            ?>
                                        </span>
                                    </td>

                                    <!-- Subject -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][subject_id]" class="tt-select-compact <?= $r['subject_id'] === 0 ? 'field-unmatched' : '' ?>" onchange="recalculateKPIs()" required>
                                            <?php if ($r['subject_id'] === 0): ?>
                                                <option value="" selected>-- Select: "<?= htmlspecialchars($r['raw_subject'], ENT_QUOTES, 'UTF-8') ?>" --</option>
                                            <?php else: ?>
                                                <option value="">-- Choose Subject --</option>
                                            <?php endif; ?>
                                            <?php foreach ($subjects as $sId => $sub): ?>
                                                <option value="<?= $sId ?>" <?= (int)$r['subject_id'] === $sId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- Faculty -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][faculty_id]" class="tt-select-compact <?= $r['faculty_id'] === 0 ? 'field-unmatched' : '' ?>" onchange="recalculateKPIs()" required>
                                            <?php if ($r['faculty_id'] === 0): ?>
                                                <option value="" selected>-- Select: "<?= htmlspecialchars($r['raw_faculty'], ENT_QUOTES, 'UTF-8') ?>" --</option>
                                            <?php else: ?>
                                                <option value="">-- Choose Faculty --</option>
                                            <?php endif; ?>
                                            <?php foreach ($faculty as $fId => $fac): ?>
                                                <option value="<?= $fId ?>" <?= (int)$r['faculty_id'] === $fId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($fac['faculty_name'] . ' (' . $fac['faculty_code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- Classroom -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][classroom_id]" class="tt-select-compact <?= $r['classroom_id'] === 0 ? 'field-unmatched' : '' ?>" onchange="recalculateKPIs()" required>
                                            <?php if ($r['classroom_id'] === 0): ?>
                                                <option value="" selected>-- Select: "<?= htmlspecialchars($r['raw_classroom'], ENT_QUOTES, 'UTF-8') ?>" --</option>
                                            <?php else: ?>
                                                <option value="">-- Room --</option>
                                            <?php endif; ?>
                                            <?php foreach ($classrooms as $cId => $rm): ?>
                                                <option value="<?= $cId ?>" <?= (int)$r['classroom_id'] === $cId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($rm['room_number'] . ' (' . $rm['room_type'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][status]" class="tt-select-compact">
                                            <option value="PUBLISHED" <?= $r['status'] === 'PUBLISHED' ? 'selected' : '' ?>>PUBLISHED</option>
                                            <option value="DRAFT" <?= $r['status'] === 'DRAFT' ? 'selected' : '' ?>>DRAFT</option>
                                        </select>
                                    </td>

                                    <!-- Error / Validation Info -->
                                    <td class="status-cell" id="status-cell-<?= $idx ?>">
                                        <?php if ($hasError): ?>
                                            <span class="tt-badge tt-badge-danger" title="<?= htmlspecialchars(implode('; ', $r['errors']), ENT_QUOTES, 'UTF-8') ?>">
                                                ⛔ Conflict Error
                                            </span>
                                            <div class="tt-reason tt-reason-error">
                                                <?= htmlspecialchars($r['errors'][0], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php elseif ($isSkipped): ?>
                                            <span class="tt-badge tt-badge-skipped" title="<?= htmlspecialchars($skipMsg, ENT_QUOTES, 'UTF-8') ?>">
                                                ⏭ SKIPPED
                                            </span>
                                            <div class="tt-reason tt-reason-skipped">
                                                <?= htmlspecialchars($skipMsg, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php elseif ($isReplace): ?>
                                            <span class="tt-badge tt-badge-replace" title="<?= htmlspecialchars($replaceMsg, ENT_QUOTES, 'UTF-8') ?>">
                                                ↻ REPLACE
                                            </span>
                                            <div class="tt-reason tt-reason-replace">
                                                <?= htmlspecialchars($replaceMsg, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php elseif ($hasWarning): ?>
                                            <span class="tt-badge tt-badge-warning" title="<?= htmlspecialchars(implode('; ', $r['warnings']), ENT_QUOTES, 'UTF-8') ?>">
                                                ⚠ Needs Match
                                            </span>
                                            <div class="tt-reason tt-reason-warning">
                                                <?= htmlspecialchars($r['warnings'][0] ?? 'Please select valid subject, faculty, and room.', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="tt-badge tt-badge-success">
                                                ✓ Ready
                                            </span>
                                            <div class="tt-reason tt-reason-success">
                                                New slot ready to import
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Action -->
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-delete-row" onclick="deletePreviewRow(<?= $idx ?>)" title="Remove this row">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ACTIONS BAR -->
                <div class="tt-actions-bar">
                    <div class="tt-actions-left">
                        <a href="timetable-upload.php" class="btn-secondary" onclick="return confirm('Cancel preview and discard extracted entries?')">
                            ← Cancel / Discard
                        </a>
                    </div>
                    <div class="tt-actions-right">
                        <button type="submit" class="btn-primary-gradient" id="btn-confirm-import">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Confirm & Import to Database
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

</main>

<script>
// Times mapped by period number for instant UI reflection
const periodTimes = {
    1: "08:30 AM – 09:15 AM",
    2: "09:15 AM – 10:00 AM",
    3: "10:00 AM – 10:45 AM",
    4: "11:00 AM – 11:45 AM",
    5: "11:45 AM – 12:30 PM",
    6: "12:30 PM – 01:15 PM",
    7: "02:00 PM – 02:45 PM",
    8: "02:45 PM – 03:30 PM",
    9: "03:45 PM – 04:30 PM"
};

// Drag and drop event handling
const dropzone = document.getElementById("tt-dropzone");
if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            const input = document.getElementById('timetable_file');
            input.files = files;
            handleFileSelected(input);
        }
    });
}

function handleFileSelected(input) {
    const badge = document.getElementById('tt-file-badge');
    const nameSpan = document.getElementById('tt-selected-name');
    const sizeSpan = document.getElementById('tt-selected-size');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        nameSpan.textContent = file.name;
        sizeSpan.textContent = "(" + (file.size / 1024).toFixed(1) + " KB)";
        badge.style.display = "inline-flex";
    } else {
        badge.style.display = "none";
    }
}

function showLoading() {
    const overlay = document.getElementById('tt-loading-overlay');
    if (overlay) overlay.style.display = "grid";
}

function updateRowTime(selectElem, rowIndex) {
    const pNum = selectElem.value;
    const timeSpan = document.getElementById('row-time-' + rowIndex);
    if (timeSpan) {
        timeSpan.textContent = periodTimes[pNum] || "—";
    }
}

function deletePreviewRow(rowIndex) {
    const row = document.getElementById('preview-row-' + rowIndex);
    if (row) {
        row.remove();
        recalculateKPIs();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function switchConflictPolicy(newMode) {
    // 1. Update hidden input for confirm_import form
    const hiddenInput = document.getElementById('conflict_mode_input');
    if (hiddenInput) {
        hiddenInput.value = newMode;
    }

    // 2. Update each table row that has an existing slot
    const rows = document.querySelectorAll('#tt-table-body tr');
    rows.forEach(tr => {
        const hasError = tr.getAttribute('data-has-error') === '1';
        const hasExisting = tr.getAttribute('data-has-existing') === '1';
        if (hasError || !hasExisting) {
            return; // Don't modify true errors or rows without existing slots
        }

        const statusCell = tr.querySelector('.status-cell');
        const skipMsg = tr.getAttribute('data-msg-skip') || 'Slot occupied. Will be SKIPPED.';
        const replaceMsg = tr.getAttribute('data-msg-replace') || 'Slot occupied. Will be REPLACED.';

        if (newMode === 'skip') {
            tr.classList.remove('row-is-replace');
            tr.classList.add('row-is-skipped');
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-skipped" title="${escapeHtml(skipMsg)}">
                        ⏭ SKIPPED
                    </span>
                    <div class="tt-reason tt-reason-skipped">
                        ${escapeHtml(skipMsg)}
                    </div>
                `;
            }
        } else {
            tr.classList.remove('row-is-skipped');
            tr.classList.add('row-is-replace');
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-replace" title="${escapeHtml(replaceMsg)}">
                        ↻ REPLACE
                    </span>
                    <div class="tt-reason tt-reason-replace">
                        ${escapeHtml(replaceMsg)}
                    </div>
                `;
            }
        }
    });

    // 3. Recalculate KPI numbers
    recalculateKPIs();
}

function recalculateKPIs() {
    const policySelect = document.getElementById('preview_conflict_mode');
    const currentMode = policySelect ? policySelect.value : 'replace';

    const rows = document.querySelectorAll('#tt-table-body tr');
    let totalExtracted = rows.length;
    let countReady = 0;
    let countSkipped = 0;
    let countWarnings = 0;
    let countErrors = 0;

    rows.forEach(tr => {
        const hasError = tr.getAttribute('data-has-error') === '1';
        const hasExisting = tr.getAttribute('data-has-existing') === '1';
        const hasWarning = tr.getAttribute('data-has-warning') === '1';

        // Check if user has unselected dropdowns
        const subVal = tr.querySelector('select[name*="[subject_id]"]')?.value;
        const facVal = tr.querySelector('select[name*="[faculty_id]"]')?.value;
        const roomVal = tr.querySelector('select[name*="[classroom_id]"]')?.value;
        const periodVal = tr.querySelector('select[name*="[period_number]"]')?.value;
        const isUnmatched = (!subVal || !facVal || !roomVal || !periodVal);

        if (hasError) {
            countErrors++;
        } else if (currentMode === 'skip' && hasExisting) {
            countSkipped++;
            // Excluded from countReady under Skip policy!
        } else if (currentMode === 'replace' && hasExisting) {
            countWarnings++; // notice: slot will be replaced
            if (!isUnmatched) {
                countReady++;
            }
        } else {
            // Normal slot
            if (hasWarning || isUnmatched) {
                countWarnings++;
            }
            if (!isUnmatched) {
                countReady++;
            }
        }
    });

    const elTotal = document.getElementById('kpi-total-val');
    const elReady = document.getElementById('kpi-ready-val');
    const elSkipped = document.getElementById('kpi-skipped-val');
    const elWarning = document.getElementById('kpi-warning-val');
    const elError = document.getElementById('kpi-error-val');

    if (elTotal) elTotal.textContent = totalExtracted;
    if (elReady) elReady.textContent = countReady;
    if (elSkipped) elSkipped.textContent = countSkipped;
    if (elWarning) elWarning.textContent = countWarnings;
    if (elError) elError.textContent = countErrors;
}

let extraRowCounter = 1000;
function addNewPreviewRow() {
    const tbody = document.getElementById('tt-table-body');
    const newIdx = extraRowCounter++;

    const tr = document.createElement('tr');
    tr.id = 'preview-row-' + newIdx;
    tr.setAttribute('data-row-index', newIdx);
    tr.setAttribute('data-has-error', '0');
    tr.setAttribute('data-has-existing', '0');
    tr.setAttribute('data-has-warning', '1');

    tr.innerHTML = `
        <td style="text-align: center; color: #8494ae; font-weight: 750;">+</td>
        <td>
            <select name="rows[${newIdx}][day]" class="tt-select-compact" required>
                <?php foreach ($validDays as $d): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="rows[${newIdx}][period_number]" class="tt-select-compact" onchange="updateRowTime(this, ${newIdx}); recalculateKPIs();" required>
                <option value="">-- Period --</option>
                <?php for ($pNum = 1; $pNum <= 9; $pNum++): ?>
                    <option value="<?= $pNum ?>">Period <?= $pNum ?> (P<?= $pNum ?>)</option>
                <?php endfor; ?>
            </select>
        </td>
        <td>
            <span id="row-time-${newIdx}" style="font-size: 12px; color: #b7c7e0;">—</span>
        </td>
        <td>
            <select name="rows[${newIdx}][subject_id]" class="tt-select-compact" onchange="recalculateKPIs()" required>
                <option value="">-- Choose Subject --</option>
                <?php foreach ($subjects as $sId => $sub): ?>
                    <option value="<?= $sId ?>"><?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="rows[${newIdx}][faculty_id]" class="tt-select-compact" onchange="recalculateKPIs()" required>
                <option value="">-- Choose Faculty --</option>
                <?php foreach ($faculty as $fId => $fac): ?>
                    <option value="<?= $fId ?>"><?= htmlspecialchars($fac['faculty_name'] . ' (' . $fac['faculty_code'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="rows[${newIdx}][classroom_id]" class="tt-select-compact" onchange="recalculateKPIs()" required>
                <option value="">-- Room --</option>
                <?php foreach ($classrooms as $cId => $rm): ?>
                    <option value="<?= $cId ?>"><?= htmlspecialchars($rm['room_number'] . ' (' . $rm['room_type'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="rows[${newIdx}][status]" class="tt-select-compact">
                <option value="PUBLISHED" selected>PUBLISHED</option>
                <option value="DRAFT">DRAFT</option>
            </select>
        </td>
        <td class="status-cell">
            <span class="tt-badge tt-badge-info">New Row</span>
            <div class="tt-reason tt-reason-warning">Please select period, subject, faculty, and room</div>
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn-delete-row" onclick="deletePreviewRow(${newIdx})" title="Remove">✕</button>
        </td>
    `;

    tbody.appendChild(tr);
    recalculateKPIs();
}

function validateBeforeConfirm() {
    const table = document.getElementById('tt-preview-table');
    if (!table) return false;

    const rows = table.querySelectorAll('tbody tr');
    if (rows.length === 0) {
        alert("There are no timetable rows to import. Please add at least one row.");
        return false;
    }

    let hasEmptySubject = false;
    let hasEmptyFaculty = false;
    let hasEmptyRoom = false;
    let hasEmptyPeriod = false;

    rows.forEach(tr => {
        // Skip validation for rows that are skipped under the active conflict policy!
        if (tr.classList.contains('row-is-skipped')) {
            return;
        }

        const subSelect = tr.querySelector('select[name*="[subject_id]"]');
        const facSelect = tr.querySelector('select[name*="[faculty_id]"]');
        const roomSelect = tr.querySelector('select[name*="[classroom_id]"]');
        const periodSelect = tr.querySelector('select[name*="[period_number]"]');

        if (subSelect && !subSelect.value) hasEmptySubject = true;
        if (facSelect && !facSelect.value) hasEmptyFaculty = true;
        if (roomSelect && !roomSelect.value) hasEmptyRoom = true;
        if (periodSelect && !periodSelect.value) hasEmptyPeriod = true;
    });

    if (hasEmptySubject || hasEmptyFaculty || hasEmptyRoom || hasEmptyPeriod) {
        return confirm("Some active rows contain unselected subjects, faculty, classrooms, or periods. Importing will fail if invalid rows remain. Are you sure you wish to submit?");
    }

    showLoading();
    return true;
}
</script>

<?php require_once "../includes/footer.php"; ?>
