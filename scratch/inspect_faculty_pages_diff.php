<?php
$files = [
    'dashboard' => 'faculty/dashboard.php',
    'timetable' => 'faculty/timetable.php',
    'notifications' => 'faculty/notifications.php',
    'profile' => 'faculty/profile.php',
    'availability' => 'faculty/availability.php',
    'requests' => 'faculty/requests.php'
];

foreach ($files as $name => $path) {
    echo "=================== $name ($path) ===================\n";
    $content = file_get_contents($path);
    
    // Check body class
    if (preg_match('/<body[^>]*class=["\']([^"\']*)["\']/', $content, $m)) {
        echo "Body class: {$m[1]}\n";
    }
    
    // Check sidebar class & width
    if (preg_match('/class=["\']([^"\']*(?:sidebar)[^"\']*)["\']/', $content, $m)) {
        echo "Sidebar class in HTML: {$m[1]}\n";
    }
    
    // Check topbar class in HTML
    if (preg_match('/class=["\']([^"\']*(?:topbar|header)[^"\']*)["\']/', $content, $m)) {
        echo "Topbar/header class in HTML: {$m[1]}\n";
    }
    
    // Check main container class in HTML
    if (preg_match('/<main[^>]*class=["\']([^"\']*)["\']/', $content, $m)) {
        echo "Main class in HTML: {$m[1]}\n";
    }
}
