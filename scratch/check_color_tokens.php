<?php
$cookieFile = __DIR__ . "/hod_cookie.txt";
$ch = curl_init("http://localhost/SmartFacultyPortal/admin/my-timetable.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
curl_close($ch);

echo "--- CHECKING DARK THEME STYLES IN OUTPUT ---\n";
$checks = [
    'background: #172235' => 'Panel background dark navy (#172235)',
    'background: #24203f' => 'Day header purple/navy tint (#24203f)',
    'background: #1d273a' => 'Period header dark navy (#1d273a)',
    'background: #162032' => 'Class cell dark navy (#162032)',
    'background: #1b1935' => 'Lab cell purple tint (#1b1935)',
    'color: #b4a0ff'      => 'Purple accent (#b4a0ff)',
    'color: #c4b5fd'      => 'Lunch vertical purple text (#c4b5fd)',
    '@media print'        => 'Clean paper print style'
];

foreach ($checks as $pattern => $label) {
    if (strpos($html, $pattern) !== false) {
        echo "✓ $label: FOUND\n";
    } else {
        echo "✗ $label: MISSING\n";
    }
}
