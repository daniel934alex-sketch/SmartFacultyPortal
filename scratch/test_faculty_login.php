<?php
$cookie = tempnam(sys_get_temp_dir(), 'f_');

// 1. Faculty login
$ch = curl_init('http://localhost/SmartFacultyPortal/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'faculty01', 'password' => 'faculty@01']));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login HTTP Code: $code\n";
echo "Headers:\n" . substr($res, 0, 300) . "\n";

// 2. Access faculty timetable
$ch = curl_init('http://localhost/SmartFacultyPortal/faculty/timetable.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$tt = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Timetable HTTP Code: $code\n";
echo "Response Length: " . strlen($tt) . " bytes\n";
echo "Snippet:\n" . substr($tt, 0, 400) . "\n";
