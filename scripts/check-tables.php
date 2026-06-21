<?php
require_once __DIR__ . '/../public_html/_db.php';
$r = db()->query('SHOW TABLES');
while ($row = $r->fetch_array()) {
    $name = $row[0];
    $count = db()->query("SELECT COUNT(*) FROM `$name`")->fetch_row()[0];
    echo "$name ($count rows)\n";
}