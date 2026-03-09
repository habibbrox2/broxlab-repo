<?php
// /tmp/verify_tables.php
$mysqli = new mysqli("localhost", "tdhuedhn_broxbhai", "EnTio1PtqI-&M&D", "tdhuedhn_broxbhai");
$res = $mysqli->query("SHOW TABLES LIKE 'ai_%'");
while($row = $res->fetch_array()) echo $row[0]."\n";
$mysqli->close();
