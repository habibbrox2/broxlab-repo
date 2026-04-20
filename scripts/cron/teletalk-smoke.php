#!/usr/bin/env php
<?php

declare(strict_types=1);

$base = dirname(__DIR__, 2);
$cmd = PHP_BINARY . ' ' . escapeshellarg($base . '/scripts/cron/teletalk-scraper.php') . ' --max-pages=1 --verbose';

passthru($cmd, $exitCode);
exit($exitCode);
