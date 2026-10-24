<?php
$cookie = tempnam(sys_get_temp_dir(), 's_');

// 1. Student login
$ch = curl_init('http://localhost/SmartFacultyPortal/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'student01', 'password' => 'Student@123']));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
curl_close($ch);

// 2. Access student timetable
$ch = curl_init('http://localhost/SmartFacultyPortal/student/timetable.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$tt = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Has CSE-A: " . (strpos($tt, 'CSE-A') !== false ? 'YES' : 'NO') . "\n";
echo "Has Student 01: " . (strpos($tt, 'Student 01') !== false ? 'YES' : 'NO') . "\n";
echo "Has DBMS: " . (strpos($tt, 'DBMS') !== false ? 'YES' : 'NO') . "\n";
echo "Has Access denied: " . (strpos($tt, 'Access denied') !== false ? 'YES' : 'NO') . "\n";
