<?php
require_once "config/database.php";

$res = $conn->query("SHOW TABLES");
echo "--- ALL TABLES ---\n";
while ($r = $res->fetch_row()) {
    echo $r[0] . "\n";
}
