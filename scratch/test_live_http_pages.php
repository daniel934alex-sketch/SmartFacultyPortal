<?php
/**
 * Test live HTTP pages with authentic session sessions
 */

ini_set('session.save_path', 'C:/laragon/tmp');

function createTestSession($userId, $username, $role, $prefix) {
    $sessId = substr(md5($prefix . microtime()), 0, 26);
    session_id($sessId);
    @session_start();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    session_write_close();
    return $sessId;
}

$hodSess = createTestSession(1, 'hod.cse', 'HOD', 'hod');
$facSess = createTestSession(2, 'faculty01', 'FACULTY', 'fac');
$stuSess = createTestSession(1, 'student01', 'STUDENT', 'stu');

echo "Created test sessions in C:/laragon/tmp:\n";
echo "  HOD Session:     $hodSess\n";
echo "  Faculty Session: $facSess\n";
echo "  Student Session: $stuSess\n\n";

function testUrl($url, $sessId, $description) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: PHPSESSID=$sessId\r\nUser-Agent: PHP-Test-Client\r\n",
            'ignore_errors' => true
        ]
    ];
    $ctx = stream_context_create($opts);
    $html = @file_get_contents($url, false, $ctx);
    $statusLine = $http_response_header[0] ?? 'HTTP/1.0 500 Unknown';
    
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $m);
    $code = (int)($m[1] ?? 500);

    echo "Testing: $description\n";
    echo "  URL: $url\n";
    echo "  Response Code: $code\n";
    echo "  Bytes returned: " . strlen($html) . "\n";

    if ($code === 200) {
        echo "  Result: SUCCESS (HTTP 200 OK)\n";
    } else {
        echo "  Result: FAILED ($statusLine)\n";
    }
    echo "--------------------------------------------------------\n";
    return ['code' => $code, 'body' => $html];
}

$baseUrl = "http://localhost/SmartFacultyPortal";

// Test 1: Admin Timetable (Grid & List)
testUrl("$baseUrl/admin/timetable.php?section=1&view=grid", $hodSess, "Admin Timetable (CSE-A Grid View)");
testUrl("$baseUrl/admin/timetable.php?section=all&view=grid", $hodSess, "Admin Timetable (All Sections Grid View)");
testUrl("$baseUrl/admin/timetable.php?section=all&view=list", $hodSess, "Admin Timetable (List View)");

// Test 2: Admin Timetable Download Endpoints
testUrl("$baseUrl/admin/timetable-download.php?section=CSE-A&format=xlsx", $hodSess, "Admin Download CSE-A Excel (.xlsx)");
testUrl("$baseUrl/admin/timetable-download.php?section=CSE-B&format=csv", $hodSess, "Admin Download CSE-B CSV (.csv)");

// Test 3: Faculty Timetable
testUrl("$baseUrl/faculty/timetable.php", $facSess, "Faculty Timetable View (Prof. Priya Sharma)");

// Test 4: Student Timetable
testUrl("$baseUrl/student/timetable.php", $stuSess, "Student Timetable View (student01)");
testUrl("$baseUrl/admin/timetable-download.php?section=CSE-A&format=xlsx", $stuSess, "Student Download CSE-A Excel (.xlsx)");
