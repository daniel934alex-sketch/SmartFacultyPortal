<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const MAX_UPLOAD_SIZE = 2097152; // 2 MB
const MAX_SECTION_SIZE = 64;

$requiredHeaders = [
    "register_number",
    "student_name",
    "email",
    "phone",
    "section_name",
    "username",
    "password"
];

/* -------------------------------------------------
   DISPLAY A RESULT OR ERROR USING THE EXISTING UI
------------------------------------------------- */

function showImportPage(string $title, string $message, bool $success = false): void
{
    http_response_code($success ? 200 : 400);

    require_once "../includes/header.php";
    require_once "../includes/navbar.php";
    require_once "../includes/sidebar.php";
    ?>
    <div style="margin:105px 25px 35px 270px;color:#e8edf8;">
        <div style="
            background:#172235;
            border:1px solid #3a4962;
            border-radius:14px;
            padding:25px;
            max-width:1100px;
        ">
            <h2 style="color:#eee8ff;">
                <?= htmlspecialchars($title, ENT_QUOTES, "UTF-8") ?>
            </h2>

            <p style="line-height:1.8;">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </p>

            <a href="students.php#upload-students"
               style="color:#c4b5fd;">
                Back to Student Management
            </a>
        </div>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

function importError(string $message): void
{
    showImportPage("Student Import", $message, false);
}

/* -------------------------------------------------
   NORMALISE HEADERS
------------------------------------------------- */

function normaliseHeader(array $headers): array
{
    if (isset($headers[0])) {
        $headers[0] = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            (string)$headers[0]
        );
    }

    return array_map(
        fn($value) => strtolower(trim((string)$value)),
        $headers
    );
}

/* -------------------------------------------------
   PARSE CSV-LIKE TEXT EXTRACTED FROM PDF OR IMAGE

   Note: OCR may not preserve table columns. The text
   must contain a recognisable header and 7 delimited
   columns per student row.
------------------------------------------------- */

function parseDelimitedText(string $text): array
{
    $required = [
        "register_number",
        "student_name",
        "email",
        "phone",
        "section_name",
        "username",
        "password"
    ];

    $lines = preg_split('/\r\n|\r|\n/', trim($text));

    if (!$lines || count($lines) < 2) {
        throw new RuntimeException(
            "The file does not contain a readable header and student rows."
        );
    }

    $headerIndex = null;
    $delimiter = null;

    foreach ($lines as $index => $line) {
        foreach ([",", "\t", "|", ";"] as $candidate) {
            $columns = str_getcsv($line, $candidate);

            if (normaliseHeader($columns) === $required) {
                $headerIndex = $index;
                $delimiter = $candidate;
                break 2;
            }
        }
    }

    if ($headerIndex === null) {
        throw new RuntimeException(
            "Could not recognise the student table. Use a file with these columns in order: " .
            implode(", ", $required) .
            ". PDF/image extraction may not preserve table columns."
        );
    }

    $rows = [];

    for ($i = $headerIndex + 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);

        if ($line === "") {
            continue;
        }

        $values = str_getcsv($line, $delimiter);

        if (count($values) !== count($required)) {
            throw new RuntimeException(
                "Extracted row " . ($i + 1) .
                " does not contain exactly seven columns. Try CSV or Excel instead."
            );
        }

        $rows[] = array_combine(
            $required,
            array_map(
                fn($value) => trim((string)$value),
                $values
            )
        );
    }

    return $rows;
}

/* -------------------------------------------------
   REQUIRE POST AND VALIDATE UPLOADED FILE
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: students.php");
    exit;
}

if (!isset($_FILES["student_file"])) {
    importError("Please select a student file.");
}

$file = $_FILES["student_file"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    $uploadMessages = [
        UPLOAD_ERR_INI_SIZE =>
            "The file exceeds the PHP server upload limit.",
        UPLOAD_ERR_FORM_SIZE =>
            "The file exceeds the form upload limit.",
        UPLOAD_ERR_PARTIAL =>
            "The file was only partially uploaded.",
        UPLOAD_ERR_NO_FILE =>
            "Please choose a file first."
    ];

    importError(
        $uploadMessages[$file["error"]] ??
        "The upload failed. Please try again."
    );
}

if ($file["size"] <= 0 || $file["size"] > MAX_UPLOAD_SIZE) {
    importError("File must be larger than 0 bytes and no bigger than 2 MB.");
}

$extension = strtolower(
    pathinfo($file["name"], PATHINFO_EXTENSION)
);

$allowedExtensions = [
    "csv", "xlsx", "xls",
    "pdf", "png", "jpg", "jpeg", "webp"
];

if (!in_array($extension, $allowedExtensions, true)) {
    importError(
        "Unsupported file type. Use CSV, XLSX, XLS, PDF, PNG, JPG, JPEG, or WEBP."
    );
}

/* -------------------------------------------------
   EXTRACT STUDENT ROWS
------------------------------------------------- */

$rows = [];

try {
    /* CSV */

    if ($extension === "csv") {
        $handle = fopen($file["tmp_name"], "r");

        if (!$handle) {
            throw new RuntimeException("Unable to open the CSV file.");
        }

        try {
            $headers = fgetcsv($handle);

            if (!$headers) {
                throw new RuntimeException("The CSV file is empty.");
            }

            if (normaliseHeader($headers) !== $requiredHeaders) {
                throw new RuntimeException(
                    "CSV columns do not match the template. Required order: " .
                    implode(", ", $requiredHeaders)
                );
            }

            $line = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $line++;

                if (count(array_filter(
                    $data,
                    fn($value) => trim((string)$value) !== ""
                )) === 0) {
                    continue;
                }

                if (count($data) !== count($requiredHeaders)) {
                    throw new RuntimeException(
                        "CSV row $line has an incorrect number of columns."
                    );
                }

                $rows[] = array_combine(
                    $requiredHeaders,
                    array_map(
                        fn($value) => trim((string)$value),
                        $data
                    )
                );
            }
        } finally {
            fclose($handle);
        }

    /* EXCEL */

    } elseif (in_array($extension, ["xlsx", "xls"], true)) {
        $autoload = __DIR__ . "/../vendor/autoload.php";

        if (!is_file($autoload)) {
            throw new RuntimeException(
                "Excel support is not installed yet. Install PhpSpreadsheet using Composer."
            );
        }

        require_once $autoload;

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(
            $file["tmp_name"]
        );

        try {
            $data = $spreadsheet
                ->getActiveSheet()
                ->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        unset($spreadsheet);

        if (!$data) {
            throw new RuntimeException("The Excel file is empty.");
        }

        if (normaliseHeader(array_shift($data)) !== $requiredHeaders) {
            throw new RuntimeException(
                "Excel columns do not match the template. Required order: " .
                implode(", ", $requiredHeaders)
            );
        }

        foreach ($data as $index => $values) {
            if (count(array_filter(
                $values,
                fn($value) => trim((string)$value) !== ""
            )) === 0) {
                continue;
            }

            if (count($values) !== count($requiredHeaders)) {
                throw new RuntimeException(
                    "Excel row " . ($index + 2) . " has incorrect columns."
                );
            }

            $rows[] = array_combine(
                $requiredHeaders,
                array_map(
                    fn($value) => trim((string)$value),
                    $values
                )
            );
        }

    /* PDF */

    } elseif ($extension === "pdf") {
        if (!function_exists("exec")) {
            throw new RuntimeException(
                "PDF extraction requires PHP exec to be enabled."
            );
        }

        // Set this to the actual pdftotext.exe location if needed.
        $pdftotextPath = "pdftotext";

        $outputFile = tempnam(
            sys_get_temp_dir(),
            "student_pdf_"
        );

        if ($outputFile === false) {
            throw new RuntimeException(
                "Could not create a temporary PDF extraction file."
            );
        }

        try {
            $command =
                escapeshellarg($pdftotextPath) .
                " -layout " .
                escapeshellarg($file["tmp_name"]) . " " .
                escapeshellarg($outputFile) .
                " 2>&1";

            $commandOutput = [];
            exec($command, $commandOutput, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    "PDF extraction failed. Install Poppler pdftotext and ensure it is available in PATH."
                );
            }

            $text = file_get_contents($outputFile);

            if ($text === false || trim($text) === "") {
                throw new RuntimeException(
                    "No readable text was found in the PDF. Scanned PDFs require OCR."
                );
            }

            $rows = parseDelimitedText($text);

        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }

    /* IMAGE: PNG, JPG, JPEG, WEBP */

    } else {
        if (!function_exists("exec")) {
            throw new RuntimeException(
                "Image extraction requires PHP exec to be enabled."
            );
        }

        if (getimagesize($file["tmp_name"]) === false) {
            throw new RuntimeException(
                "The uploaded file is not a valid image."
            );
        }

        // Full path to the Tesseract installation.
        $tesseractPath =
            "C:\\Program Files\\Tesseract-OCR\\tesseract.exe";

        if (!is_file($tesseractPath)) {
            throw new RuntimeException(
                "Tesseract was not found. Check the installation path."
            );
        }

        $outputBase = tempnam(
            sys_get_temp_dir(),
            "student_ocr_"
        );

        if ($outputBase === false) {
            throw new RuntimeException(
                "Could not create a temporary OCR file."
            );
        }

        // Tesseract appends .txt to the output base.
        unlink($outputBase);

        try {
            $command =
                escapeshellarg($tesseractPath) . " " .
                escapeshellarg($file["tmp_name"]) . " " .
                escapeshellarg($outputBase) .
                " -l eng 2>&1";

            $commandOutput = [];
            exec($command, $commandOutput, $exitCode);

            $textFile = $outputBase . ".txt";

            if ($exitCode !== 0 || !is_file($textFile)) {
                error_log(
                    "Tesseract error: " .
                    implode("\n", $commandOutput)
                );

                throw new RuntimeException(
                    "OCR failed. Check that Tesseract English language data is installed and PHP can run exec."
                );
            }

            $text = file_get_contents($textFile);

            if ($text === false || trim($text) === "") {
                throw new RuntimeException(
                    "OCR could not recognise text in this image."
                );
            }

            $rows = parseDelimitedText($text);

        } finally {
            foreach ([$outputBase, $outputBase . ".txt"] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

} catch (Throwable $e) {
    error_log("Student file extraction error: " . $e->getMessage());
    importError($e->getMessage());
}

if (!$rows) {
    importError("No student rows were found in this file.");
}

/* -------------------------------------------------
   LOAD EXISTING SECTIONS
------------------------------------------------- */

$sections = [];
$result = $conn->query(
    "SELECT section_id, section_name FROM sections"
);

while ($section = $result->fetch_assoc()) {
    $sections[strtolower(trim($section["section_name"]))] =
        (int)$section["section_id"];
}

/* -------------------------------------------------
   VALIDATE ALL ROWS BEFORE INSERTING ANYTHING
------------------------------------------------- */

$errors = [];
$seenRegisters = [];
$seenUsernames = [];
$sectionAdds = [];

foreach ($rows as $index => &$row) {
    $line = $index + 2;

    foreach ($requiredHeaders as $header) {
        $row[$header] = trim((string)($row[$header] ?? ""));
    }

    $register = $row["register_number"];
    $name = $row["student_name"];
    $email = $row["email"];
    $phone = $row["phone"];
    $sectionName = strtolower($row["section_name"]);
    $username = $row["username"];
    $password = $row["password"];

    if (
        $register === "" ||
        $name === "" ||
        $username === "" ||
        $password === "" ||
        !isset($sections[$sectionName])
    ) {
        $errors[] =
            "Row $line: required field missing or section not found.";
        continue;
    }

    if (
        strlen($register) > 30 ||
        strlen($name) > 100 ||
        strlen($email) > 100 ||
        strlen($phone) > 20 ||
        strlen($username) > 50 ||
        strlen($password) < 6
    ) {
        $errors[] =
            "Row $line: a field is too long or the password is shorter than 6 characters.";
        continue;
    }

    if (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = "Row $line: invalid email address.";
        continue;
    }

    if (
        isset($seenRegisters[$register]) ||
        isset($seenUsernames[$username])
    ) {
        $errors[] =
            "Row $line: duplicate register number or username in the file.";
        continue;
    }

    $seenRegisters[$register] = true;
    $seenUsernames[$username] = true;

    $row["section_id"] = $sections[$sectionName];

    $sectionId = $row["section_id"];

    $sectionAdds[$sectionId] =
        ($sectionAdds[$sectionId] ?? 0) + 1;
}
unset($row);

if ($errors) {
    importError(implode(" ", $errors));
}

/* -------------------------------------------------
   DATABASE IMPORT
------------------------------------------------- */

$successCount = 0;

try {
    $conn->begin_transaction();

    // Check section capacities, including all rows in this upload.
    foreach ($sectionAdds as $sectionId => $adding) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM students
             WHERE section_id = ?
             FOR UPDATE"
        );

        $stmt->bind_param("i", $sectionId);
        $stmt->execute();

        $total = (int)$stmt
            ->get_result()
            ->fetch_assoc()["total"];

        $stmt->close();

        if ($total + $adding > MAX_SECTION_SIZE) {
            throw new RuntimeException(
                "Section capacity exceeded. Each section can contain at most 64 students."
            );
        }
    }

    foreach ($rows as $index => $row) {
        $register = $row["register_number"];
        $username = $row["username"];
        $name = $row["student_name"];
        $email = $row["email"];
        $phone = $row["phone"];
        $sectionId = $row["section_id"];

        $stmt = $conn->prepare(
            "SELECT student_id
             FROM students
             WHERE register_number = ?"
        );

        $stmt->bind_param("s", $register);
        $stmt->execute();

        $registerExists =
            $stmt->get_result()->num_rows > 0;

        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?"
        );

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $usernameExists =
            $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if ($registerExists || $usernameExists) {
            throw new RuntimeException(
                "Row " . ($index + 2) .
                ": register number or username already exists. No rows were imported."
            );
        }

        $hash = password_hash(
            $row["password"],
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            "INSERT INTO users
                (username, password, role, status)
             VALUES (?, ?, 'STUDENT', 'ACTIVE')"
        );

        $stmt->bind_param("ss", $username, $hash);
        $stmt->execute();

        $userId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO students
                (user_id, register_number, student_name,
                 email, phone, section_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issssi",
            $userId,
            $register,
            $name,
            $email,
            $phone,
            $sectionId
        );

        $stmt->execute();
        $stmt->close();

        $successCount++;
    }

    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        "Student import database error: " .
        $e->getMessage()
    );

    importError($e->getMessage());
}

/* -------------------------------------------------
   SUCCESS PAGE
------------------------------------------------- */

showImportPage(
    "Student Import Results",
    "Students imported successfully: " . (int)$successCount,
    true
);