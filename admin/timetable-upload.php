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
$uploadMode = $_POST['upload_mode'] ?? ($_GET['mode'] ?? 'single');
if ($uploadMode !== 'all') {
    $uploadMode = 'single';
}
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
    } elseif ($uploadMode === 'single' && ($selectedSectionId <= 0 || !isset($sections[$selectedSectionId]))) {
        $error = "Please select a valid section for import.";
    } else {
        $submittedRows = $_POST['rows'] ?? [];
        if (empty($submittedRows) || !is_array($submittedRows)) {
            $error = "No timetable rows to import.";
        } else {
            // Begin Transaction
            $conn->begin_transaction();
            try {
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

                // Faculty-subject assignment map for validation
                $facultySubjectMap = [];
                $fsRes = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
                if ($fsRes) {
                    while ($fsRow = $fsRes->fetch_assoc()) {
                        $facultySubjectMap[(int)$fsRow['faculty_id'] . '_' . (int)$fsRow['subject_id']] = true;
                    }
                }

                $seenSectionSlots = []; // "secId_day_pId" => rowIdx
                $seenFacultySlots = []; // "facId_day_pId" => ['sec_name' => X, 'period_num' => Y, 'row_num' => Z]
                $seenRoomSlots    = []; // "roomId_day_pId" => ['sec_name' => X, 'period_num' => Y, 'row_num' => Z]

                $importedBySec = [];
                $skippedBySec = [];
                $replacedBySec = [];
                foreach ($sections as $sId => $sec) {
                    $importedBySec[$sId] = 0;
                    $skippedBySec[$sId] = 0;
                    $replacedBySec[$sId] = 0;
                }

                $totalImported = 0;
                $totalSkipped = 0;
                $totalReplaced = 0;

                // Step A: Parse, sanitize, and validate internal batch consistency
                $parsedRows = [];
                $rowNum = 0;
                foreach ($submittedRows as $rowKey => $row) {
                    $rowNum++;
                    $secId = ($uploadMode === 'all') ? (int)($row['section_id'] ?? 0) : $selectedSectionId;
                    if ($secId <= 0 || !isset($sections[$secId])) {
                        throw new Exception("Row #{$rowNum}: Invalid or unrecognized academic section selected.");
                    }
                    $secName = $sections[$secId]['section_name'];

                    $day = strtoupper(trim($row['day'] ?? ''));
                    if (!in_array($day, $validDays, true)) {
                        throw new Exception("Row #{$rowNum} ({$secName}): Invalid day specified: " . htmlspecialchars($day));
                    }

                    $periodNum = (int)($row['period_number'] ?? 0);
                    if (!isset($periodMapByNumber[$periodNum])) {
                        throw new Exception("Row #{$rowNum} ({$secName}): Invalid period number: P" . $periodNum);
                    }
                    $periodId = $periodMapByNumber[$periodNum];

                    // Check existing timetable entry for this section
                    $checkExistingStmt->bind_param("isi", $secId, $day, $periodId);
                    $checkExistingStmt->execute();
                    $checkRes = $checkExistingStmt->get_result();
                    $isExistingSlot = ($checkRes && $checkRes->num_rows > 0);

                    // Under 'skip' policy, if slot already exists in DB, it is safely bypassed
                    if ($conflictMode === 'skip' && $isExistingSlot) {
                        $parsedRows[] = [
                            'rowNum' => $rowNum,
                            'secId' => $secId,
                            'secName' => $secName,
                            'day' => $day,
                            'periodNum' => $periodNum,
                            'periodId' => $periodId,
                            'subjectId' => 0,
                            'facultyId' => 0,
                            'classroomId' => 0,
                            'status' => 'DRAFT',
                            'isSkipped' => true,
                            'isExisting' => true
                        ];
                        continue;
                    }

                    $subjectId = (int)($row['subject_id'] ?? 0);
                    if (!isset($subjects[$subjectId])) {
                        throw new Exception("Row #{$rowNum} ({$secName}): Please select a valid subject for {$day} P{$periodNum}.");
                    }

                    $facultyId = (int)($row['faculty_id'] ?? 0);
                    if (!isset($faculty[$facultyId])) {
                        throw new Exception("Row #{$rowNum} ({$secName}): Please select a valid faculty for {$day} P{$periodNum}.");
                    }

                    $classroomId = (int)($row['classroom_id'] ?? 0);
                    if (!isset($classrooms[$classroomId])) {
                        throw new Exception("Row #{$rowNum} ({$secName}): Please select a valid classroom for {$day} P{$periodNum}.");
                    }

                    // Enforce faculty-subject mapping
                    if (!empty($facultySubjectMap) && !isset($facultySubjectMap["{$facultyId}_{$subjectId}"])) {
                        $fName = $faculty[$facultyId]['faculty_name'] ?? 'Faculty';
                        $sCode = $subjects[$subjectId]['subject_code'] ?? 'Subject';
                        throw new Exception("Row #{$rowNum} ({$secName}): {$fName} is not assigned to subject {$sCode} in faculty_subjects.");
                    }

                    $status = strtoupper(trim($row['status'] ?? 'PUBLISHED'));
                    if (!in_array($status, ['PUBLISHED', 'DRAFT'], true)) {
                        $status = 'PUBLISHED';
                    }

                    // Duplicate slot check within batch
                    $slotKey = "{$secId}_{$day}_{$periodId}";
                    if (isset($seenSectionSlots[$slotKey])) {
                        $prevRow = $seenSectionSlots[$slotKey];
                        throw new Exception("Duplicate entry in submission for {$secName} {$day} Period {$periodNum} (defined in Row #{$prevRow} and Row #{$rowNum}). Each slot can only have one assigned class.");
                    }
                    $seenSectionSlots[$slotKey] = $rowNum;

                    // Faculty double-booking check within batch
                    $facKey = "{$facultyId}_{$day}_{$periodId}";
                    if (isset($seenFacultySlots[$facKey])) {
                        $fPrev = $seenFacultySlots[$facKey];
                        $fName = $faculty[$facultyId]['faculty_name'] ?? 'Faculty';
                        throw new Exception("Faculty double-booking in submission: {$fName} is assigned to both {$fPrev['sec_name']} (Row #{$fPrev['row_num']}) and {$secName} (Row #{$rowNum}) on {$day} Period {$periodNum}.");
                    }
                    $seenFacultySlots[$facKey] = ['sec_name' => $secName, 'period_num' => $periodNum, 'row_num' => $rowNum];

                    // Classroom double-booking check within batch
                    $roomKey = "{$classroomId}_{$day}_{$periodId}";
                    if (isset($seenRoomSlots[$roomKey])) {
                        $rPrev = $seenRoomSlots[$roomKey];
                        $rNum = $classrooms[$classroomId]['room_number'] ?? 'Room';
                        throw new Exception("Classroom double-booking in submission: Room {$rNum} is assigned to both {$rPrev['sec_name']} (Row #{$rPrev['row_num']}) and {$secName} (Row #{$rowNum}) on {$day} Period {$periodNum}.");
                    }
                    $seenRoomSlots[$roomKey] = ['sec_name' => $secName, 'period_num' => $periodNum, 'row_num' => $rowNum];

                    $parsedRows[] = [
                        'rowNum' => $rowNum,
                        'secId' => $secId,
                        'secName' => $secName,
                        'day' => $day,
                        'periodNum' => $periodNum,
                        'periodId' => $periodId,
                        'subjectId' => $subjectId,
                        'facultyId' => $facultyId,
                        'classroomId' => $classroomId,
                        'status' => $status,
                        'isSkipped' => false,
                        'isExisting' => $isExistingSlot
                    ];
                }

                // Step B: Check existing DB entries & collisions, execute replacements/skips/inserts
                foreach ($parsedRows as $pRow) {
                    $secId = $pRow['secId'];
                    $secName = $pRow['secName'];
                    $day = $pRow['day'];
                    $periodNum = $pRow['periodNum'];
                    $periodId = $pRow['periodId'];
                    $subjectId = $pRow['subjectId'];
                    $facultyId = $pRow['facultyId'];
                    $classroomId = $pRow['classroomId'];
                    $status = $pRow['status'];

                    if (!empty($pRow['isSkipped'])) {
                        $totalSkipped++;
                        if (isset($skippedBySec[$secId])) $skippedBySec[$secId]++;
                        continue; // Skip this slot and leave existing DB record untouched
                    }

                    if (!empty($pRow['isExisting'])) {
                        $deleteExistingStmt->bind_param("isi", $secId, $day, $periodId);
                        if (!$deleteExistingStmt->execute()) {
                            throw new Exception("Failed to replace existing schedule slot for {$secName} {$day} P{$periodNum}.");
                        }
                        $totalReplaced++;
                        if (isset($replacedBySec[$secId])) $replacedBySec[$secId]++;
                    }

                    // Check external DB collisions with other sections (excluding any slots that are being replaced in this batch)
                    if ($checkFacultyCollisionStmt) {
                        $checkFacultyCollisionStmt->bind_param("isii", $facultyId, $day, $periodId, $secId);
                        $checkFacultyCollisionStmt->execute();
                        $facColRes = $checkFacultyCollisionStmt->get_result();
                        if ($facColRes && $facColRes->num_rows > 0) {
                            $facCol = $facColRes->fetch_assoc();
                            $collidedSecId = 0;
                            foreach ($sections as $sId => $s) {
                                if ($s['section_name'] === $facCol['section_name']) {
                                    $collidedSecId = $sId;
                                    break;
                                }
                            }
                            $isReplacedInBatch = ($conflictMode === 'replace' && isset($seenSectionSlots["{$collidedSecId}_{$day}_{$periodId}"]));
                            if (!$isReplacedInBatch) {
                                $facName = $faculty[$facultyId]['faculty_name'] ?? 'Faculty';
                                throw new Exception("Collision with database: {$facName} is already assigned to {$facCol['section_name']} ({$facCol['subject_code']}) at {$day} P{$periodNum}.");
                            }
                        }
                    }

                    if ($checkRoomCollisionStmt) {
                        $checkRoomCollisionStmt->bind_param("isii", $classroomId, $day, $periodId, $secId);
                        $checkRoomCollisionStmt->execute();
                        $roomColRes = $checkRoomCollisionStmt->get_result();
                        if ($roomColRes && $roomColRes->num_rows > 0) {
                            $roomCol = $roomColRes->fetch_assoc();
                            $collidedSecId = 0;
                            foreach ($sections as $sId => $s) {
                                if ($s['section_name'] === $roomCol['section_name']) {
                                    $collidedSecId = $sId;
                                    break;
                                }
                            }
                            $isReplacedInBatch = ($conflictMode === 'replace' && isset($seenSectionSlots["{$collidedSecId}_{$day}_{$periodId}"]));
                            if (!$isReplacedInBatch) {
                                $roomNum = $classrooms[$classroomId]['room_number'] ?? 'Room';
                                throw new Exception("Collision with database: Room {$roomNum} is already assigned to {$roomCol['section_name']} at {$day} P{$periodNum}.");
                            }
                        }
                    }

                    // Insert timetable row
                    $insertStmt->bind_param("siiiiis", $day, $periodId, $secId, $subjectId, $facultyId, $classroomId, $status);
                    if (!$insertStmt->execute()) {
                        throw new Exception("Failed to insert timetable row for {$secName} {$day} P{$periodNum}: " . $insertStmt->error);
                    }
                    $totalImported++;
                    if (isset($importedBySec[$secId])) $importedBySec[$secId]++;
                }

                $checkExistingStmt->close();
                $deleteExistingStmt->close();
                if ($checkFacultyCollisionStmt) $checkFacultyCollisionStmt->close();
                if ($checkRoomCollisionStmt) $checkRoomCollisionStmt->close();
                $insertStmt->close();

                // Commit Transaction
                $conn->commit();

                if ($uploadMode === 'all') {
                    $secBreakdownParts = [];
                    foreach ($sections as $sId => $sec) {
                        $name = $sec['section_name'];
                        $imp = $importedBySec[$sId] ?? 0;
                        $rep = $replacedBySec[$sId] ?? 0;
                        $skp = $skippedBySec[$sId] ?? 0;
                        $detail = "{$name}: {$imp} imported";
                        if ($rep > 0) $detail .= ", {$rep} replaced";
                        if ($skp > 0) $detail .= ", {$skp} skipped";
                        $secBreakdownParts[] = "<strong>{$detail}</strong>";
                    }
                    $notice = [
                        'type' => 'success',
                        'title' => 'All Classes Timetable Import Successful!',
                        'message' => "Successfully imported <strong>{$totalImported}</strong> timetable entries across all 5 sections.<br><span style='display:inline-block; margin-top: 6px; font-size: 13px; color: #a4f5d0;'>" . implode(" &bull; ", $secBreakdownParts) . "</span>" .
                                     ($totalReplaced > 0 ? "<br>Total replaced existing slots: {$totalReplaced}" : "") .
                                     ($totalSkipped > 0 ? "<br>Total skipped slots: {$totalSkipped}" : ""),
                        'section_id' => 0,
                        'section_name' => 'All Sections'
                    ];
                } else {
                    $secName = $sections[$selectedSectionId]['section_name'] ?? "Section #{$selectedSectionId}";
                    $notice = [
                        'type' => 'success',
                        'title' => 'Timetable Import Successful!',
                        'message' => "Successfully imported <strong>{$totalImported}</strong> timetable entries for <strong>{$secName}</strong>." .
                                     ($totalReplaced > 0 ? " (Replaced {$totalReplaced} existing slots)" : "") .
                                     ($totalSkipped > 0 ? " ({$totalSkipped} conflicting slots skipped)" : ""),
                        'section_id' => $selectedSectionId,
                        'section_name' => $secName
                    ];
                }
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
    } elseif ($uploadMode === 'single' && ($selectedSectionId <= 0 || !isset($sections[$selectedSectionId]))) {
        $error = "Please select a target section from the dropdown for Single Class Upload.";
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
                    // Parse file using TimetableParser with selected conflict policy and upload mode
                    $parseResult = $parser->parseFile(
                        $destPath,
                        $ext,
                        ($uploadMode === 'all' ? 0 : $selectedSectionId),
                        $defaultStatus,
                        $conflictMode,
                        $uploadMode
                    );

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
                            'total_rows' => count($previewRows),
                            'upload_mode' => $uploadMode
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
/* Upload Mode Selector */
.tt-mode-selector-wrap {
    margin-bottom: 22px;
}

.tt-mode-section-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #d0c5f0;
    margin-bottom: 10px;
}

.tt-mode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}

.tt-mode-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    background: rgba(23, 34, 53, 0.7);
    border: 1.5px solid #2a364b;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.tt-mode-card:hover {
    border-color: #5d4e9c;
    background: rgba(35, 48, 72, 0.85);
    transform: translateY(-1px);
}

.tt-mode-card.active {
    border-color: #9a83ff;
    background: linear-gradient(135deg, rgba(81, 71, 140, 0.32), rgba(23, 34, 53, 0.95));
    box-shadow: 0 0 0 1px #9a83ff, 0 8px 24px rgba(154, 131, 255, 0.16);
}

.tt-mode-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #5a6b85;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
    transition: all 0.2s ease;
}

.tt-mode-card.active .tt-mode-indicator {
    border-color: #9a83ff;
    background: #9a83ff;
}

.tt-mode-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0c1220;
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.2s ease;
}

.tt-mode-card.active .tt-mode-dot {
    opacity: 1;
    transform: scale(1);
}

.tt-mode-title {
    font-size: 14px;
    font-weight: 750;
    color: #ffffff;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
}

.tt-mode-card.active .tt-mode-title {
    color: #d8cdfb;
}

.tt-mode-desc {
    font-size: 12px;
    color: #9bb0cd;
    line-height: 1.45;
}

/* All Classes Notice */
.tt-all-classes-notice {
    padding: 16px 20px;
    background: rgba(81, 71, 140, 0.15);
    border: 1px solid #51478c;
    border-radius: 12px;
}

.tt-all-classes-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.tt-sec-badge {
    padding: 5px 12px;
    background: #251f49;
    border: 1px solid #6b58b4;
    color: #dfd4ff;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 750;
    letter-spacing: 0.3px;
}

/* Section Breakdown Cards (Preview Step) */
.tt-sec-breakdown-card {
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 16px;
    padding: 22px 24px;
    margin-bottom: 24px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.14);
}

.tt-sec-breakdown-card h3 {
    margin: 0 0 4px;
    color: #ffffff;
    font-size: 16px;
    font-weight: 800;
}

.tt-sec-breakdown-card p {
    margin: 0 0 16px;
    font-size: 12.5px;
    color: #9bb0cd;
}

.tt-sec-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.tt-sec-tile {
    background: #111a2a;
    border: 1px solid #2a364b;
    border-radius: 12px;
    padding: 14px 16px;
    transition: all 0.2s ease;
}

.tt-sec-tile:hover {
    border-color: #51478c;
    background: #152033;
}

.tt-sec-tile.tile-has-error {
    border-color: #ff5277;
    background: rgba(255, 82, 119, 0.08);
}

.tt-sec-tile.tile-is-valid {
    border-color: #274b3d;
}

.tt-sec-tile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #222f44;
}

.tt-sec-tile-title {
    font-size: 14px;
    font-weight: 800;
    color: #ffffff;
}

.tt-sec-tile-badge {
    font-size: 10.5px;
    font-weight: 750;
    padding: 2px 7px;
    border-radius: 4px;
}

.tt-sec-tile-badge.badge-valid {
    background: rgba(62, 207, 142, 0.15);
    color: #3ecf8e;
    border: 1px solid rgba(62, 207, 142, 0.35);
}

.tt-sec-tile-badge.badge-error {
    background: rgba(255, 82, 119, 0.18);
    color: #ff6b8b;
    border: 1px solid rgba(255, 82, 119, 0.4);
}

.tt-sec-tile-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
    font-size: 11.5px;
    color: #9bb0cd;
}

.tt-sec-tile-stats span {
    display: flex;
    justify-content: space-between;
}

.tt-sec-tile-stats strong {
    color: #ffffff;
}

/* Section Filter Bar */
.tt-sec-filter-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 18px;
    padding: 12px 16px;
    background: #111a2a;
    border-radius: 12px;
    border: 1px solid #2a364b;
}

.tt-sec-filter-label {
    font-size: 12px;
    font-weight: 750;
    color: #9bb0cd;
    margin-right: 6px;
}

.tt-sec-tab {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    border: 1px solid #2a364b;
    background: rgba(255, 255, 255, 0.04);
    color: #c0d0ea;
    cursor: pointer;
    transition: all 0.18s ease;
}

.tt-sec-tab:hover {
    background: rgba(255, 255, 255, 0.09);
    color: #ffffff;
    border-color: #556885;
}

.tt-sec-tab.active {
    background: #9a83ff;
    color: #0c1220;
    border-color: #9a83ff;
    box-shadow: 0 4px 12px rgba(154, 131, 255, 0.35);
}

/* Blocking Alert */
.tt-blocking-alert {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    background: rgba(255, 82, 119, 0.12);
    border: 1px solid #ff5277;
    border-radius: 14px;
    margin-bottom: 22px;
    color: #ffb4c5;
}

.tt-blocking-alert svg {
    color: #ff5277;
    flex-shrink: 0;
    margin-top: 2px;
}

.tt-blocking-alert h4 {
    margin: 0 0 4px;
    color: #ff6b8b;
    font-size: 15px;
    font-weight: 800;
}

.tt-blocking-alert p {
    margin: 0;
    font-size: 12.5px;
    line-height: 1.5;
    color: #fcd5de;
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

                <!-- Settings: Upload Mode, Section, Conflict Mode, Status -->
                <!-- 1. Upload Mode Selector (Above Target Section) -->
                <div class="tt-mode-selector-wrap">
                    <label class="tt-mode-section-label">Upload Mode <span style="color: #ff6b8b;">*</span></label>
                    <div class="tt-mode-grid">
                        <label class="tt-mode-card <?= $uploadMode === 'single' ? 'active' : '' ?>" id="mode-card-single" onclick="setUploadMode('single')">
                            <input type="radio" name="upload_mode" value="single" id="mode_single" <?= $uploadMode === 'single' ? 'checked' : '' ?> style="position: absolute; opacity: 0; pointer-events: none;">
                            <div class="tt-mode-indicator">
                                <span class="tt-mode-dot"></span>
                            </div>
                            <div class="tt-mode-text">
                                <div class="tt-mode-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg>
                                    Single Class Upload
                                </div>
                                <div class="tt-mode-desc">Upload a timetable schedule for one specific section (current behavior)</div>
                            </div>
                        </label>

                        <label class="tt-mode-card <?= $uploadMode === 'all' ? 'active' : '' ?>" id="mode-card-all" onclick="setUploadMode('all')">
                            <input type="radio" name="upload_mode" value="all" id="mode_all" <?= $uploadMode === 'all' ? 'checked' : '' ?> style="position: absolute; opacity: 0; pointer-events: none;">
                            <div class="tt-mode-indicator">
                                <span class="tt-mode-dot"></span>
                            </div>
                            <div class="tt-mode-text">
                                <div class="tt-mode-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    All Classes Upload (5 Sections)
                                </div>
                                <div class="tt-mode-desc">Upload one file containing entries for all 5 sections: CSE-A, CSE-B, CSE-C, CSE-D, and CSE-E</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="tt-upload-grid">
                    <!-- Target Section (for Single Class Upload) -->
                    <div class="tt-form-group" id="section_id_group" style="<?= $uploadMode === 'all' ? 'display: none;' : '' ?>">
                        <label for="section_id">Target Section <span style="color: #ff6b8b;">*</span></label>
                        <select name="section_id" id="section_id" <?= $uploadMode === 'single' ? 'required' : '' ?>>
                            <option value="">-- Choose Academic Section --</option>
                            <?php foreach ($sections as $sId => $sec): ?>
                                <option value="<?= $sId ?>" <?= $selectedSectionId === $sId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Timetable slots will be assigned to this section.</small>
                    </div>

                    <!-- Notice (for All Classes Upload) -->
                    <div class="tt-form-group tt-all-classes-notice" id="all-classes-notice" style="<?= $uploadMode === 'all' ? '' : 'display: none;' ?>">
                        <label style="color: #d8cdfb; font-size: 13px; font-weight: 700; margin-bottom: 6px; display: block;">All 5 Target Sections</label>
                        <div class="tt-all-classes-badges">
                            <span class="tt-sec-badge">CSE-A</span>
                            <span class="tt-sec-badge">CSE-B</span>
                            <span class="tt-sec-badge">CSE-C</span>
                            <span class="tt-sec-badge">CSE-D</span>
                            <span class="tt-sec-badge">CSE-E</span>
                        </div>
                        <small style="color: #a5b5d1; display: block; margin-top: 8px;">
                            Sections are automatically identified per row or spreadsheet sheet.
                        </small>
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
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 11px; color: #a5b5d1; font-weight: 700;">Single Class:</span>
                        <a href="timetable-template.php?format=csv" class="tt-btn-outline" download>
                            CSV (.csv)
                        </a>
                        <a href="timetable-template.php?format=xlsx" class="tt-btn-outline" download>
                            Excel (.xlsx)
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 11px; color: #c4b5f8; font-weight: 700;">All 5 Classes:</span>
                        <a href="timetable-template.php?format=csv&mode=all" class="tt-btn-outline" style="border-color: #7b62cc; color: #dfd4ff;" download>
                            5-Class CSV (.csv)
                        </a>
                        <a href="timetable-template.php?format=xlsx&mode=all" class="tt-btn-outline" style="border-color: #7b62cc; color: #dfd4ff;" download>
                            5-Class Excel (.xlsx)
                        </a>
                    </div>
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

        $sectionStats = [];
        foreach ($sections as $sId => $sec) {
            $sectionStats[$sId] = [
                'id' => $sId,
                'name' => $sec['section_name'],
                'total' => 0,
                'ready' => 0,
                'skipped' => 0,
                'warnings' => 0,
                'errors' => 0
            ];
        }

        foreach ($previewRows as $r) {
            $hasError = !empty($r['errors']);
            $hasExisting = !empty($r['conflict']) && in_array($r['conflict']['type'] ?? '', ['existing_entry_skip', 'existing_entry_replace'], true);
            $hasWarning = !empty($r['warnings']) || ($r['subject_id'] === 0 || $r['faculty_id'] === 0 || $r['classroom_id'] === 0);

            $secId = (int)($r['section_id'] > 0 ? $r['section_id'] : $selectedSectionId);
            if (!isset($sectionStats[$secId])) {
                $sectionStats[$secId] = [
                    'id' => $secId,
                    'name' => $r['raw_section'] ?: ($secId > 0 ? "Sec #{$secId}" : "Unknown Section"),
                    'total' => 0,
                    'ready' => 0,
                    'skipped' => 0,
                    'warnings' => 0,
                    'errors' => 0
                ];
            }
            $sectionStats[$secId]['total']++;

            if ($hasError) {
                $countErrors++;
                $sectionStats[$secId]['errors']++;
            } elseif ($conflictMode === 'skip' && $hasExisting) {
                $countSkipped++;
                $sectionStats[$secId]['skipped']++;
            } elseif ($conflictMode === 'replace' && $hasExisting) {
                $countWarnings++; // notice: slot will be replaced
                $sectionStats[$secId]['warnings']++;
                if (!$hasWarning) {
                    $countReady++;
                    $sectionStats[$secId]['ready']++;
                }
            } else {
                if ($hasWarning) {
                    $countWarnings++;
                    $sectionStats[$secId]['warnings']++;
                }
                if (!$hasWarning) {
                    $countReady++;
                    $sectionStats[$secId]['ready']++;
                }
            }
        }
        $targetSectionName = ($uploadMode === 'all') ? "All 5 Sections (CSE-A to CSE-E)" : ($sections[$selectedSectionId]['section_name'] ?? "Section #{$selectedSectionId}");
        ?>

        <!-- KPI SUMMARY TILES (COMBINED TOTALS) -->
        <div class="tt-kpi-grid">
            <div class="tt-kpi-card kpi-total">
                <span class="tt-kpi-label"><?= $uploadMode === 'all' ? 'Combined Extracted' : 'Total Extracted' ?></span>
                <div class="tt-kpi-val" id="kpi-total-val"><?= $totalExtracted ?></div>
            </div>
            <div class="tt-kpi-card kpi-ready">
                <span class="tt-kpi-label"><?= $uploadMode === 'all' ? 'Combined Ready' : 'Ready to Import' ?></span>
                <div class="tt-kpi-val" id="kpi-ready-val" style="color: #3ecf8e;"><?= $countReady ?></div>
            </div>
            <div class="tt-kpi-card kpi-skipped">
                <span class="tt-kpi-label"><?= $uploadMode === 'all' ? 'Combined Skipped' : 'Skipped (Occupied)' ?></span>
                <div class="tt-kpi-val" id="kpi-skipped-val" style="color: #38bdf8;"><?= $countSkipped ?></div>
            </div>
            <div class="tt-kpi-card kpi-warning">
                <span class="tt-kpi-label"><?= $uploadMode === 'all' ? 'Combined Warnings' : 'Warnings / Notices' ?></span>
                <div class="tt-kpi-val" id="kpi-warning-val" style="color: #f5a623;"><?= $countWarnings ?></div>
            </div>
            <div class="tt-kpi-card kpi-error">
                <span class="tt-kpi-label"><?= $uploadMode === 'all' ? 'Combined Errors' : 'Blocking Errors' ?></span>
                <div class="tt-kpi-val" id="kpi-error-val" style="color: #ff5277;"><?= $countErrors ?></div>
            </div>
        </div>

        <!-- SECTION BREAKDOWN CARD (FOR ALL CLASSES MODE) -->
        <?php if ($uploadMode === 'all'): ?>
            <div class="tt-sec-breakdown-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3>Academic Section Breakdown (5 Classes)</h3>
                        <p>Summary of extracted timetable slots grouped by academic section for CSE-A through CSE-E.</p>
                    </div>
                    <div>
                        <span class="tt-badge <?= $countErrors > 0 ? 'tt-badge-danger' : 'tt-badge-success' ?>">
                            <?= $countErrors > 0 ? "⛔ {$countErrors} Conflict Error(s) in Batch" : "✓ All 5 Sections Validated" ?>
                        </span>
                    </div>
                </div>

                <div class="tt-sec-grid">
                    <?php foreach ($sections as $sId => $sec): ?>
                        <?php
                        $st = $sectionStats[$sId] ?? ['total' => 0, 'ready' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0];
                        $secHasError = $st['errors'] > 0;
                        ?>
                        <div class="tt-sec-tile <?= $secHasError ? 'tile-has-error' : 'tile-is-valid' ?>" id="sec-tile-<?= $sId ?>">
                            <div class="tt-sec-tile-header">
                                <span class="tt-sec-tile-title"><?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="tt-sec-tile-badge <?= $secHasError ? 'badge-error' : 'badge-valid' ?>" id="sec-badge-<?= $sId ?>">
                                    <?= $secHasError ? "{$st['errors']} Error" : ($st['total'] > 0 ? "{$st['ready']} Ready" : "0 Entries") ?>
                                </span>
                            </div>
                            <div class="tt-sec-tile-stats">
                                <span>Total: <strong id="sec-stat-total-<?= $sId ?>"><?= $st['total'] ?></strong></span>
                                <span>Ready: <strong id="sec-stat-ready-<?= $sId ?>" style="color: #3ecf8e;"><?= $st['ready'] ?></strong></span>
                                <span>Skipped: <strong id="sec-stat-skipped-<?= $sId ?>" style="color: #38bdf8;"><?= $st['skipped'] ?></strong></span>
                                <span>Warn/Err: <strong id="sec-stat-issues-<?= $sId ?>" style="color: <?= ($st['warnings'] + $st['errors'] > 0) ? '#ff5277' : '#9bb0cd' ?>;"><?= $st['warnings'] + $st['errors'] ?></strong></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- BLOCKING CONFLICT ALERT -->
        <?php if ($countErrors > 0): ?>
            <div class="tt-blocking-alert" id="tt-blocking-alert">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    <h4>Blocking Conflicts Detected (<?= $countErrors ?>)</h4>
                    <p>
                        Import is locked to protect timetable integrity. The file contains duplicate section slots, cross-section double-bookings, or invalid entries.
                        Review the highlighted red rows below to reassign periods, subjects, faculty, classrooms, or use <strong>✕</strong> to remove invalid rows before importing.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="tt-confirm-form" onsubmit="return validateBeforeConfirm();">
            <input type="hidden" name="action" value="confirm_import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="upload_mode" value="<?= htmlspecialchars($uploadMode, ENT_QUOTES, 'UTF-8') ?>">
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

                <!-- SECTION FILTER TABS (FOR ALL CLASSES MODE) -->
                <?php if ($uploadMode === 'all'): ?>
                    <div class="tt-sec-filter-bar">
                        <span class="tt-sec-filter-label">Filter View by Section:</span>
                        <button type="button" class="tt-sec-tab active" data-filter="all" onclick="filterSection('all', this)">
                            All Sections (<?= $totalExtracted ?>)
                        </button>
                        <?php foreach ($sections as $sId => $sec): ?>
                            <?php $sCount = $sectionStats[$sId]['total'] ?? 0; ?>
                            <button type="button" class="tt-sec-tab" data-filter="<?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?>" onclick="filterSection('<?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?>', this)">
                                <?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?> (<?= $sCount ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- EDITABLE TABLE -->
                <div class="tt-table-container">
                    <table class="tt-preview-table" id="tt-preview-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <?php if ($uploadMode === 'all'): ?>
                                    <th style="width: 130px;">Section</th>
                                <?php endif; ?>
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
                                $rSecId = (int)($r['section_id'] > 0 ? $r['section_id'] : $selectedSectionId);
                                $rSecName = $sections[$rSecId]['section_name'] ?? ($r['raw_section'] ?: "CSE-A");
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
                                    data-section-id="<?= $rSecId ?>"
                                    data-section-name="<?= htmlspecialchars($rSecName, ENT_QUOTES, 'UTF-8') ?>"
                                    data-has-error="<?= $hasError ? '1' : '0' ?>"
                                    data-has-existing="<?= $isExisting ? '1' : '0' ?>"
                                    data-has-warning="<?= $hasWarning ? '1' : '0' ?>"
                                    data-msg-skip="<?= htmlspecialchars($skipMsg, ENT_QUOTES, 'UTF-8') ?>"
                                    data-msg-replace="<?= htmlspecialchars($replaceMsg, ENT_QUOTES, 'UTF-8') ?>">
                                    <!-- Index -->
                                    <td style="text-align: center; color: #8494ae; font-weight: 750;">
                                        <?= $idx + 1 ?>
                                    </td>

                                    <?php if ($uploadMode === 'all'): ?>
                                        <!-- Section Selector -->
                                        <td>
                                            <select name="rows[<?= $idx ?>][section_id]" class="tt-select-compact <?= $r['section_id'] === 0 ? 'field-unmatched' : '' ?>" onchange="onRowSectionChange(<?= $idx ?>, this)" required>
                                                <?php if ($r['section_id'] === 0): ?>
                                                    <option value="" selected>-- Unmatched: "<?= htmlspecialchars($r['raw_section'] ?? '', ENT_QUOTES, 'UTF-8') ?>" --</option>
                                                <?php endif; ?>
                                                <?php foreach ($sections as $sId => $sec): ?>
                                                    <option value="<?= $sId ?>" <?= $rSecId === $sId ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($sec['section_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php else: ?>
                                        <input type="hidden" name="rows[<?= $idx ?>][section_id]" value="<?= $selectedSectionId ?>">
                                    <?php endif; ?>

                                    <!-- Day -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][day]" class="tt-select-compact" onchange="recalculateKPIs()" required>
                                            <?php foreach ($validDays as $d): ?>
                                                <option value="<?= $d ?>" <?= $r['day'] === $d ? 'selected' : '' ?>>
                                                    <?= $d ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- Period -->
                                    <td>
                                        <select name="rows[<?= $idx ?>][period_number]" class="tt-select-compact" onchange="updateRowTime(this, <?= $idx ?>); recalculateKPIs();" required>
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
                                        <select name="rows[<?= $idx ?>][status]" class="tt-select-compact" onchange="recalculateKPIs()">
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
                        <button type="submit" class="btn-primary-gradient" id="btn-confirm-import" <?= $countErrors > 0 ? 'disabled style="opacity: 0.55; cursor: not-allowed;" title="Cannot import while blocking errors exist"' : '' ?>>
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

<?php
// Build compact references for reactive client-side collision validation
$dbBookings = [
    'faculty' => [],
    'rooms' => [],
    'sections' => []
];
$exRes = $conn->query("
    SELECT t.timetable_id, t.section_id, sec.section_name, t.day, p.period_number, 
           t.subject_id, s.subject_code, t.faculty_id, f.faculty_name, 
           t.classroom_id, c.room_number, t.status
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
");
if ($exRes) {
    while ($er = $exRes->fetch_assoc()) {
        $d = strtoupper($er['day']);
        $p = (int)$er['period_number'];
        $sId = (int)$er['section_id'];
        $fId = (int)$er['faculty_id'];
        $cId = (int)$er['classroom_id'];
        $dbBookings['faculty']["{$fId}_{$d}_{$p}"] = [
            'sec_id' => $sId,
            'sec_name' => $er['section_name'],
            'sub_code' => $er['subject_code']
        ];
        $dbBookings['rooms']["{$cId}_{$d}_{$p}"] = [
            'sec_id' => $sId,
            'sec_name' => $er['section_name'],
            'sub_code' => $er['subject_code']
        ];
        $dbBookings['sections']["{$sId}_{$d}_{$p}"] = [
            'sub_code' => $er['subject_code'],
            'fac_name' => $er['faculty_name'],
            'room_num' => $er['room_number'],
            'status' => $er['status']
        ];
    }
}

$clientFacultySubjectMap = [];
$fsRes = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
if ($fsRes) {
    while ($fsRow = $fsRes->fetch_assoc()) {
        $clientFacultySubjectMap[(int)$fsRow['faculty_id'] . '_' . (int)$fsRow['subject_id']] = true;
    }
}

$clientFacultyData = [];
foreach ($faculty as $fId => $f) {
    $clientFacultyData[$fId] = $f['faculty_name'];
}
$clientClassroomsData = [];
foreach ($classrooms as $cId => $c) {
    $clientClassroomsData[$cId] = $c['room_number'];
}
$clientSubjectsData = [];
foreach ($subjects as $sId => $s) {
    $clientSubjectsData[$sId] = $s['subject_code'];
}
?>

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

// Reference data for reactive validation
const sectionsData = <?= json_encode(array_values(array_map(function($s) {
    return ['id' => (int)$s['section_id'], 'name' => $s['section_name']];
}, $sections))) ?>;

const dbBookings = <?= json_encode($dbBookings) ?>;
const facultySubjectsMap = <?= json_encode($clientFacultySubjectMap) ?>;
const facultyNames = <?= json_encode($clientFacultyData) ?>;
const classroomNames = <?= json_encode($clientClassroomsData) ?>;
const subjectCodes = <?= json_encode($clientSubjectsData) ?>;
const defaultTargetSecId = <?= (int)$selectedSectionId ?>;
const isAllClassesMode = <?= ($uploadMode === 'all') ? 'true' : 'false' ?>;

// Upload Mode Switcher (Step 1)
function setUploadMode(mode) {
    const radioSingle = document.getElementById('mode_single');
    const radioAll = document.getElementById('mode_all');
    const cardSingle = document.getElementById('mode-card-single');
    const cardAll = document.getElementById('mode-card-all');
    const secGroup = document.getElementById('section_id_group');
    const secSelect = document.getElementById('section_id');
    const allNotice = document.getElementById('all-classes-notice');

    if (mode === 'all') {
        if (radioAll) radioAll.checked = true;
        if (cardAll) cardAll.classList.add('active');
        if (cardSingle) cardSingle.classList.remove('active');
        if (secGroup) secGroup.style.display = 'none';
        if (secSelect) {
            secSelect.removeAttribute('required');
            secSelect.disabled = true;
        }
        if (allNotice) allNotice.style.display = 'block';
    } else {
        if (radioSingle) radioSingle.checked = true;
        if (cardSingle) cardSingle.classList.add('active');
        if (cardAll) cardAll.classList.remove('active');
        if (secGroup) secGroup.style.display = 'block';
        if (secSelect) {
            secSelect.setAttribute('required', 'required');
            secSelect.disabled = false;
        }
        if (allNotice) allNotice.style.display = 'none';
    }
}

// Section Filter Bar (Step 2)
function filterSection(secName, btn) {
    document.querySelectorAll('.tt-sec-tab').forEach(t => t.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const rows = document.querySelectorAll('#tt-table-body tr');
    rows.forEach(tr => {
        const rowSec = tr.getAttribute('data-section-name');
        if (secName === 'all' || rowSec === secName) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });
}

function onRowSectionChange(rowIndex, selectElem) {
    const tr = document.getElementById('preview-row-' + rowIndex);
    if (!tr) return;
    const optText = selectElem.options[selectElem.selectedIndex]?.text?.trim() || '';
    tr.setAttribute('data-section-id', selectElem.value);
    tr.setAttribute('data-section-name', optText);
    recalculateKPIs();
}

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
    const hiddenInput = document.getElementById('conflict_mode_input');
    if (hiddenInput) {
        hiddenInput.value = newMode;
    }
    const policySelect = document.getElementById('preview_conflict_mode');
    if (policySelect && policySelect.value !== newMode) {
        policySelect.value = newMode;
    }
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

    const secStats = {};
    sectionsData.forEach(s => {
        secStats[s.id] = { total: 0, ready: 0, skipped: 0, warnings: 0, errors: 0 };
    });

    // Pass 1: Parse rows & detect internal batch duplicates
    const rowDataList = [];
    const seenSectionSlots = {}; // "secId_day_pNum" => [rowItem]
    const seenFacultySlots = {}; // "facId_day_pNum" => [rowItem]
    const seenRoomSlots = {};    // "roomId_day_pNum" => [rowItem]

    rows.forEach(tr => {
        const rowIdx = tr.getAttribute('data-row-index');
        const secSelect = tr.querySelector('select[name*="[section_id]"]');
        const secId = secSelect ? parseInt(secSelect.value || '0', 10) : (parseInt(tr.getAttribute('data-section-id') || '0', 10) || defaultTargetSecId);
        const secName = (sectionsData.find(s => s.id === secId)?.name) || tr.getAttribute('data-section-name') || 'Section';

        const daySelect = tr.querySelector('select[name*="[day]"]');
        const day = (daySelect?.value || '').toUpperCase().trim();

        const periodSelect = tr.querySelector('select[name*="[period_number]"]');
        const periodNum = parseInt(periodSelect?.value || '0', 10);

        const subSelect = tr.querySelector('select[name*="[subject_id]"]');
        const subId = parseInt(subSelect?.value || '0', 10);

        const facSelect = tr.querySelector('select[name*="[faculty_id]"]');
        const facId = parseInt(facSelect?.value || '0', 10);

        const roomSelect = tr.querySelector('select[name*="[classroom_id]"]');
        const roomId = parseInt(roomSelect?.value || '0', 10);

        // Check if DB slot exists for this section
        const secSlotKey = `${secId}_${day}_${periodNum}`;
        const dbSecSlot = (day && periodNum > 0) ? dbBookings.sections[secSlotKey] : null;
        const isExisting = !!dbSecSlot;
        const isSkipped = (isExisting && currentMode === 'skip');
        const isReplace = (isExisting && currentMode === 'replace');

        const rowItem = {
            tr,
            rowIdx,
            secId,
            secName,
            day,
            periodNum,
            subId,
            facId,
            roomId,
            isExisting,
            isSkipped,
            isReplace,
            dbSecSlot,
            errors: [],
            warnings: []
        };
        rowDataList.push(rowItem);

        if (secStats[secId]) {
            secStats[secId].total++;
        }

        // If not skipped, track for internal batch collisions
        if (!isSkipped && day && periodNum > 0) {
            if (secId > 0) {
                if (!seenSectionSlots[secSlotKey]) seenSectionSlots[secSlotKey] = [];
                seenSectionSlots[secSlotKey].push(rowItem);
            }
            if (facId > 0) {
                const facKey = `${facId}_${day}_${periodNum}`;
                if (!seenFacultySlots[facKey]) seenFacultySlots[facKey] = [];
                seenFacultySlots[facKey].push(rowItem);
            }
            if (roomId > 0) {
                const roomKey = `${roomId}_${day}_${periodNum}`;
                if (!seenRoomSlots[roomKey]) seenRoomSlots[roomKey] = [];
                seenRoomSlots[roomKey].push(rowItem);
            }
        }
    });

    // Pass 2: Evaluate collisions and validation
    rowDataList.forEach(r => {
        const tr = r.tr;
        const statusCell = tr.querySelector('.status-cell');

        if (r.isSkipped) {
            tr.className = 'row-is-skipped';
            tr.setAttribute('data-has-error', '0');
            tr.setAttribute('data-has-existing', '1');
            tr.setAttribute('data-has-warning', '0');

            const ex = r.dbSecSlot;
            const skipMsg = ex ? `Slot occupied: Section already has ${ex.sub_code} (${ex.fac_name}, Rm ${ex.room_num}). Will be SKIPPED.` : 'Slot occupied. Will be SKIPPED.';
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
            countSkipped++;
            if (secStats[r.secId]) secStats[r.secId].skipped++;
            return;
        }

        // Check required fields
        if (!r.day) r.errors.push("Please select a valid day.");
        if (!r.periodNum) r.errors.push("Please select a period.");
        if (isAllClassesMode && !r.secId) r.errors.push("Please select a section.");

        if (!r.subId) r.warnings.push("Please choose a subject.");
        if (!r.facId) r.warnings.push("Please choose a faculty.");
        if (!r.roomId) r.warnings.push("Please choose a classroom.");

        // Internal batch duplicate section slot
        const secSlotKey = `${r.secId}_${r.day}_${r.periodNum}`;
        if (seenSectionSlots[secSlotKey] && seenSectionSlots[secSlotKey].length > 1) {
            r.errors.push(`Duplicate slot in table: ${r.secName} ${r.day} Period ${r.periodNum} assigned multiple times.`);
        }

        // Internal batch faculty double-booking
        if (r.facId > 0 && r.day && r.periodNum > 0) {
            const facKey = `${r.facId}_${r.day}_${r.periodNum}`;
            if (seenFacultySlots[facKey] && seenFacultySlots[facKey].length > 1) {
                const other = seenFacultySlots[facKey].find(o => o.rowIdx !== r.rowIdx);
                const fName = facultyNames[r.facId] || 'Faculty';
                r.errors.push(`Faculty double-booked in table: ${fName} assigned to both ${r.secName} and ${other ? other.secName : 'another class'} at ${r.day} P${r.periodNum}.`);
            }
        }

        // Internal batch room collision
        if (r.roomId > 0 && r.day && r.periodNum > 0) {
            const roomKey = `${r.roomId}_${r.day}_${r.periodNum}`;
            if (seenRoomSlots[roomKey] && seenRoomSlots[roomKey].length > 1) {
                const other = seenRoomSlots[roomKey].find(o => o.rowIdx !== r.rowIdx);
                const rNum = classroomNames[r.roomId] || 'Room';
                r.errors.push(`Classroom double-booked in table: Room ${rNum} assigned to both ${r.secName} and ${other ? other.secName : 'another class'} at ${r.day} P${r.periodNum}.`);
            }
        }

        // Database collisions against other sections
        if (r.facId > 0 && r.day && r.periodNum > 0) {
            const dbFac = dbBookings.faculty[`${r.facId}_${r.day}_${r.periodNum}`];
            if (dbFac && dbFac.sec_id !== r.secId) {
                const isReplacedInBatch = (currentMode === 'replace' && seenSectionSlots[`${dbFac.sec_id}_${r.day}_${r.periodNum}`]);
                if (!isReplacedInBatch) {
                    const fName = facultyNames[r.facId] || 'Faculty';
                    r.errors.push(`Faculty double-booked: ${fName} is already teaching ${dbFac.sub_code} for Section ${dbFac.sec_name} at ${r.day} P${r.periodNum}.`);
                }
            }
        }

        if (r.roomId > 0 && r.day && r.periodNum > 0) {
            const dbRoom = dbBookings.rooms[`${r.roomId}_${r.day}_${r.periodNum}`];
            if (dbRoom && dbRoom.sec_id !== r.secId) {
                const isReplacedInBatch = (currentMode === 'replace' && seenSectionSlots[`${dbRoom.sec_id}_${r.day}_${r.periodNum}`]);
                if (!isReplacedInBatch) {
                    const rNum = classroomNames[r.roomId] || 'Room';
                    r.errors.push(`Classroom collision: Room ${rNum} is already occupied by Section ${dbRoom.sec_name} (${dbRoom.sub_code}) at ${r.day} P${r.periodNum}.`);
                }
            }
        }

        // Faculty-to-subject validity check
        if (r.subId > 0 && r.facId > 0 && Object.keys(facultySubjectsMap).length > 0) {
            if (!facultySubjectsMap[`${r.facId}_${r.subId}`]) {
                const fName = facultyNames[r.facId] || 'Faculty';
                const sCode = subjectCodes[r.subId] || 'Subject';
                r.errors.push(`${fName} is not assigned to subject ${sCode} in faculty_subjects.`);
            }
        }

        // Apply visual state to row
        const hasErr = r.errors.length > 0;
        const hasWarn = r.warnings.length > 0;

        tr.setAttribute('data-has-error', hasErr ? '1' : '0');
        tr.setAttribute('data-has-warning', hasWarn ? '1' : '0');
        tr.setAttribute('data-has-existing', r.isExisting ? '1' : '0');

        if (hasErr) {
            tr.className = 'row-has-error';
            countErrors++;
            if (secStats[r.secId]) secStats[r.secId].errors++;
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-danger" title="${escapeHtml(r.errors.join('; '))}">
                        ⛔ Conflict Error
                    </span>
                    <div class="tt-reason tt-reason-error">
                        ${escapeHtml(r.errors[0])}
                    </div>
                `;
            }
        } else if (r.isReplace) {
            tr.className = 'row-is-replace';
            const ex = r.dbSecSlot;
            const repMsg = ex ? `Will replace existing ${ex.sub_code} (${ex.fac_name}, Rm ${ex.room_num}).` : 'Will replace existing slot.';
            countWarnings++;
            if (secStats[r.secId]) secStats[r.secId].warnings++;
            if (!hasWarn) {
                countReady++;
                if (secStats[r.secId]) secStats[r.secId].ready++;
            }
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-replace" title="${escapeHtml(repMsg)}">
                        ↻ REPLACE
                    </span>
                    <div class="tt-reason tt-reason-replace">
                        ${escapeHtml(repMsg)}
                    </div>
                `;
            }
        } else if (hasWarn) {
            tr.className = 'row-has-warning';
            countWarnings++;
            if (secStats[r.secId]) secStats[r.secId].warnings++;
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-warning" title="${escapeHtml(r.warnings.join('; '))}">
                        ⚠ Needs Match
                    </span>
                    <div class="tt-reason tt-reason-warning">
                        ${escapeHtml(r.warnings[0])}
                    </div>
                `;
            }
        } else {
            tr.className = '';
            countReady++;
            if (secStats[r.secId]) secStats[r.secId].ready++;
            if (statusCell) {
                statusCell.innerHTML = `
                    <span class="tt-badge tt-badge-success">
                        ✓ Ready
                    </span>
                    <div class="tt-reason tt-reason-success">
                        New slot ready to import
                    </div>
                `;
            }
        }
    });

    // Update KPI counters
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

    // Update Section Breakdown tiles (for all classes mode)
    sectionsData.forEach(s => {
        const st = secStats[s.id] || { total: 0, ready: 0, skipped: 0, warnings: 0, errors: 0 };
        const elSTotal = document.getElementById('sec-stat-total-' + s.id);
        const elSReady = document.getElementById('sec-stat-ready-' + s.id);
        const elSSkipped = document.getElementById('sec-stat-skipped-' + s.id);
        const elSIssues = document.getElementById('sec-stat-issues-' + s.id);
        const elBadge = document.getElementById('sec-badge-' + s.id);
        const elTile = document.getElementById('sec-tile-' + s.id);

        if (elSTotal) elSTotal.textContent = st.total;
        if (elSReady) elSReady.textContent = st.ready;
        if (elSSkipped) elSSkipped.textContent = st.skipped;
        if (elSIssues) {
            const issues = st.warnings + st.errors;
            elSIssues.textContent = issues;
            elSIssues.style.color = (issues > 0) ? '#ff5277' : '#9bb0cd';
        }
        if (elBadge) {
            if (st.errors > 0) {
                elBadge.className = 'tt-sec-tile-badge badge-error';
                elBadge.textContent = st.errors + ' Error' + (st.errors > 1 ? 's' : '');
            } else {
                elBadge.className = 'tt-sec-tile-badge badge-valid';
                elBadge.textContent = st.total > 0 ? (st.ready + ' Ready') : '0 Entries';
            }
        }
        if (elTile) {
            elTile.className = 'tt-sec-tile ' + (st.errors > 0 ? 'tile-has-error' : 'tile-is-valid');
        }
    });

    // Control Confirm Button & Alert Banner
    const confirmBtn = document.getElementById('btn-confirm-import');
    const alertBox = document.getElementById('tt-blocking-alert');

    if (confirmBtn) {
        if (countErrors > 0) {
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.55';
            confirmBtn.style.cursor = 'not-allowed';
            confirmBtn.title = 'Cannot import while blocking errors exist';
        } else {
            confirmBtn.disabled = false;
            confirmBtn.style.opacity = '1';
            confirmBtn.style.cursor = 'pointer';
            confirmBtn.title = 'Confirm & Import to Database';
        }
    }

    if (alertBox) {
        alertBox.style.display = (countErrors > 0) ? 'flex' : 'none';
        const h4 = alertBox.querySelector('h4');
        if (h4) h4.textContent = `Blocking Conflicts Detected (${countErrors})`;
    }
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
    tr.setAttribute('data-section-id', sectionsData[0]?.id || '1');
    tr.setAttribute('data-section-name', sectionsData[0]?.name || 'CSE-A');

    let sectionColHtml = '';
    if (isAllClassesMode) {
        let secOpts = '';
        sectionsData.forEach(s => {
            secOpts += `<option value="${s.id}">${escapeHtml(s.name)}</option>`;
        });
        sectionColHtml = `
            <td>
                <select name="rows[${newIdx}][section_id]" class="tt-select-compact" onchange="onRowSectionChange(${newIdx}, this)" required>
                    ${secOpts}
                </select>
            </td>
        `;
    }

    tr.innerHTML = `
        <td style="text-align: center; color: #8494ae; font-weight: 750;">+</td>
        ${sectionColHtml}
        <td>
            <select name="rows[${newIdx}][day]" class="tt-select-compact" onchange="recalculateKPIs()" required>
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
            <select name="rows[${newIdx}][status]" class="tt-select-compact" onchange="recalculateKPIs()">
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

    // Force recalculation before validating submission
    recalculateKPIs();

    let hasBlockingError = false;
    let hasEmptySubject = false;
    let hasEmptyFaculty = false;
    let hasEmptyRoom = false;
    let hasEmptyPeriod = false;
    let hasEmptySection = false;

    rows.forEach(tr => {
        // Skip validation for rows that are skipped under the active conflict policy!
        if (tr.classList.contains('row-is-skipped')) {
            return;
        }

        if (tr.getAttribute('data-has-error') === '1') {
            hasBlockingError = true;
        }

        const subSelect = tr.querySelector('select[name*="[subject_id]"]');
        const facSelect = tr.querySelector('select[name*="[faculty_id]"]');
        const roomSelect = tr.querySelector('select[name*="[classroom_id]"]');
        const periodSelect = tr.querySelector('select[name*="[period_number]"]');
        const secSelect = tr.querySelector('select[name*="[section_id]"]');

        if (subSelect && !subSelect.value) hasEmptySubject = true;
        if (facSelect && !facSelect.value) hasEmptyFaculty = true;
        if (roomSelect && !roomSelect.value) hasEmptyRoom = true;
        if (periodSelect && !periodSelect.value) hasEmptyPeriod = true;
        if (isAllClassesMode && secSelect && !secSelect.value) hasEmptySection = true;
    });

    if (hasBlockingError) {
        alert("Cannot import: The timetable contains blocking errors or collisions (highlighted in red). Please resolve or remove conflicting rows before proceeding.");
        return false;
    }

    if (hasEmptySubject || hasEmptyFaculty || hasEmptyRoom || hasEmptyPeriod || hasEmptySection) {
        return confirm("Some active rows contain unselected sections, subjects, faculty, classrooms, or periods. Importing will fail if invalid rows remain. Are you sure you wish to submit?");
    }

    showLoading();
    return true;
}
</script>

<?php require_once "../includes/footer.php"; ?>
