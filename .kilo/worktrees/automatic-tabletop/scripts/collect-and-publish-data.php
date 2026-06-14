#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Deprecated: Use `scripts/cron/scraper-runpipeline.php`.
 *
 * This file previously contained hardcoded DB credentials. It is intentionally
 * kept as a safe stub to avoid reintroducing secrets.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

fwrite(STDERR, "Deprecated. Run:\n  php scripts/cron/scraper-runpipeline.php --type=all --max-sources=20 --max-items=3 --enhance --publish\n");
exit(1);

