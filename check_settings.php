<?php
require_once 'Config/Db.php';
global $mysqli;
$result = $mysqli->query('SELECT setting_key, setting_value FROM ai_settings');
while ($row = $result->fetch_assoc()) {
    echo $row['setting_key'] . ': ' . $row['setting_value'] . "\n";
}
