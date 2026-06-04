<?php
$uri = $_SERVER["REQUEST_URI"];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== "/" && is_file($file)) { return false; }
require __DIR__ . "/index.php";
