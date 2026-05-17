<?php
// ============================================================================
// migrate_all.php – Run ALL sql files in Database/ in dependency order
//
// 3-pass strategy
//   Pass 1 – CREATE TABLE  (always idempotent, no data / no FK pressure)
//   Pass 2 – Parent INSERTs – tables with no child-FK dependency
//            (users, pages, tags, roles, categories, posts, cvs, mobiles, …)
//   Pass 3 – Child  INSERTs – tables referencing parents
//            (comments, cv_items, posts, media, notifications, …)
//            FOREIGN_KEY_CHECKS=0 so order doesn't matter for INSERTs
//
//  Extra rules:
//  – ad_placements.sql  &  donation_payments.sql  are skipped (DB-level
//    SQL that requires special run; users should apply these by hand)
//  – All other files are parsed with a string-literal-aware splitter so
//    semicolons inside VALUES never split a statement.
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Use config/db.php's credentials directly (avoids loading the app twice)
$baseDir = __DIR__;
require_once $baseDir . '/Config/Db.php';

// ---------------------------------------------------------------------------
// Collect & sort SQL files
// ---------------------------------------------------------------------------
$sqlDir   = $baseDir . '/Database';
$sqlFiles = glob($sqlDir . '/*.sql');
sort($sqlFiles, SORT_STRING | SORT_FLAG_CASE);

if (empty($sqlFiles)) {
    die("[ERROR] No .sql files found in Database/\n");
}
$skipErrors = in_array('--skip-errors', $argv ?? [], true);

// Files that require special handling (not auto-runnable as plain SQL)
$NEED_MANUAL = [
    'ad_placements.sql',          // ALTER TABLE ADD CONSTRAINT … (MySQL-compat rewrite needed)
    'donation_payments.sql',      // referenced before table was first created in older run
];

// ---------------------------------------------------------------------------
// Exclude files that need manual run from the passes; run them last
// ---------------------------------------------------------------------------
$autoFiles  = array_values(array_filter($sqlFiles, fn($f) => !in_array(basename($f), $NEED_MANUAL)));
$manualFiles = array_values(array_filter($sqlFiles, fn($f) => in_array(basename($f), $NEED_MANUAL)));

// ---------------------------------------------------------------------------
// SQL state-machine splitter  (handles block-comments, line-comments,
//                              single- and double-quoted strings)
// ---------------------------------------------------------------------------
function splitSql(string $sql): array
{
    $len        = strlen($sql);
    $statements = [];
    $cur        = '';
    $i          = 0;
    $inBlock    = false;  // /* … */
    $inSingle   = false;  // '…'
    $inDouble   = false;  // "…"

    while ($i < $len) {
        $ch  = $sql[$i];
        $ch2 = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // ── Block comment ─
        if ($inBlock) {
            if ($ch === '*' && $ch2 === '/') { $inBlock = false; $i += 2; continue; }
            $cur .= $ch; $i++; continue;
        }
        if ($ch === '/' && $ch2 === '*') { $inBlock = true; $i += 2; continue; }

        // ── Line comment ─
        if ($ch === '-' && $ch2 === '-') {
            $cur .= $ch;
            while ($i < $len && $sql[$i] !== "\n") { $i++; if ($i >= $len) break; $cur .= $sql[$i]; }
            $i++; continue;
        }

        // ── Strings (don't split on ';' or commas inside them) ─
        if ($inSingle) {
            if ($ch === '\\' && $i + 1 < $len) { $cur .= $ch . $sql[$i+1]; $i += 2; continue; }
            if ($ch === "'" && $ch2 !== "'") { $inSingle = false; $cur .= $ch; $i++; continue; }
            if ($ch === "'")                    { $cur .= $ch . $ch2; $i += 2; continue; }
            $cur .= $ch; $i++; continue;
        }
        if ($inDouble) {
            if ($ch === '\\' && $i + 1 < $len) { $cur .= $ch . $sql[$i+1]; $i += 2; continue; }
            if ($ch === '"' && $ch2 !== '"') { $inDouble = false; $cur .= $ch; $i++; continue; }
            if ($ch === '"')                    { $cur .= $ch . $ch2; $i += 2; continue; }
            $cur .= $ch; $i++; continue;
        }

        if ($ch === "'") { $inSingle = true; $cur .= $ch; $i++; continue; }
        if ($ch === '"') { $inDouble = true; $cur .= $ch; $i++; continue; }

        // ── Statement terminator ─
        if ($ch === ';') {
            $s = trim($cur);
            if ($s !== '') $statements[] = $s;
            $cur  = '';
            $i++;
            continue;
        }

        $cur .= $ch;
        $i++;
    }
    $t = trim($cur);
    if ($t !== '') $statements[] = $t;
    return array_values(array_filter($statements, fn($s) => strlen($s) > 0));
}

// ---------------------------------------------------------------------------
// Run a single SQL statement; returns true on success, throws on error
// ---------------------------------------------------------------------------
function runStmt(mysqli $mysqli, string $stmtClean): void
{
    @mysqli_query($mysqli, $stmtClean); // suppress warning, check below
    if (mysqli_errno($mysqli) !== 0) {
        throw new mysqli_sql_exception(
            mysqli_error($mysqli),
            mysqli_errno($mysqli)
        );
    }
}

// ---------------------------------------------------------------------------
// Classify a stripped SQL statement into create / insert / ddl / other
// ---------------------------------------------------------------------------
function classifyStmt(string $stmt): string
{
    $up = strtoupper(ltrim($stmt));
    if      (str_starts_with($up, 'CREATE TABLE'))   return 'create';
    elseif  (str_starts_with($up, 'CREATE INDEX'))   return 'create';
    elseif  (str_starts_with($up, 'CREATE UNIQUE'))  return 'create';
    elseif  (str_starts_with($up, 'ALTER TABLE'))    return 'ddl';
    elseif  (str_starts_with($up, 'INSERT INTO'))    return 'insert';
    return 'other';
}

// ---------------------------------------------------------------------------
// Execute a batch of files; returns [success_count, skip_count, fail_errors]
// ---------------------------------------------------------------------------
function runBatch(array $files, mysqli $mysqli, bool $fkOff, bool $commentsOk): array
{
    $success = 0; $skips = 0; $failed = 0; $errors = [];
    $total   = count($files);

    foreach ($files as $idx => $file) {
        $fn     = basename($file);
        $label  = sprintf("%3d/%3d  %-48s", $idx + 1, $total, $fn);
        $sql    = @file_get_contents($file);

        if ($sql === false) {
            echo "$label  FAILED – could not read\n";
            $failed++; $errors[] = "$fn – read error";
            continue;
        }

        $parts = splitSql($sql);
        $fileOk = true;

        foreach ($parts as $stmt) {
            $stmtClean = trim($stmt);
            if ($stmtClean === '' || preg_match('/^--/', $stmtClean)) continue;
            // Skip pure line-comment blocks
            if (preg_match('/^--/', $stmtClean)) continue;

            if ($fkOff && strtoupper(ltrim($stmtClean)) === 'SET FOREIGN_KEY_CHECKS=0') { continue; }
            if ($fkOff && strtoupper(ltrim($stmtClean)) === 'SET FOREIGN_KEY_CHECKS=1') { continue; }

            $type = classifyStmt($stmtClean);

            // In FK-off child pass, skip CREATE/DDL statements (already done)
            if ($fkOff && in_array($type, ['create', 'ddl'])) continue;

            // In non-comment-skip mode, skip comment-only lines
            if (!$commentsOk && preg_match('/^--/', $stmtClean)) continue;

            try {
                runStmt($mysqli, $stmtClean);
            } catch (mysqli_sql_exception $ex) {
                $err = $ex->getMessage();

                // Duplicate-table / duplicate-key → idempotent, count as skip
                $isDupTable  = preg_match('/already exists/i', $err) &&
                               preg_match('/CREATE TABLE/i', $stmtClean);
                $isDupKey    = preg_match("/(Duplicate entry|Duplicate key|Duplicate)/i", $err);
                $isDupIdx    = preg_match('/already exists/i', $err) &&
                               (preg_match('/CREATE INDEX/i', $stmtClean) ||
                                preg_match('/CREATE UNIQUE INDEX/i', $stmtClean));

                if ($isDupTable || $isDupKey || $isDupIdx) {
                    if ($commentsOk) {
                        // These were intentionally skipped by the create-pass
                        // splitter … not really a "skip", just noise
                    }
                    $skips++;
                    continue;
                }

                echo "$label  FAILED\n";
                $failed++;
                $errors[] = "$fn – " . preg_replace("/\n/", ' ', $err);
                $fileOk   = false;
                break;
            }
        }

        if ($fileOk) {
            echo "$label  OK\n";
            $success++;
        }
    }
    return [$success, $skips, $failed, $errors];
}

// ---------------------------------------------------------------------------
// RUN
// ---------------------------------------------------------------------------
echo "\n";
echo str_repeat('=', 72) . "\n";
echo "  Migrate All – starting\n";
echo str_repeat('=', 72) . "\n\n";

$totalFiles = count($autoFiles) + count($manualFiles);

/// PASS 1 : CREATE TABLE + DDL (CREATE INDEX / ALTER TABLE / SET FK)
/// ====================================================================
echo "\n─── PASS 1 – CREATE TABLE & DDL ───────────────────────────────────\n";
mysqli_query($mysqli, "SET SESSION FOREIGN_KEY_CHECKS=1");

$createOnly = function (string $stmt): bool {
    $up = strtoupper(ltrim($stmt));
    return str_starts_with($up, 'CREATE TABLE')
        || str_starts_with($up, 'CREATE INDEX')
        || str_starts_with($up, 'CREATE UNIQUE')
        || str_starts_with($up, 'ALTER TABLE');
};

$oldFileno  = 0;
$oldSuccess = 0; $oldSkips = 0; $oldFails = 0; $oldErr = [];

foreach ($autoFiles as $fIdx => $file) {
    $fn    = basename($file);
    $label = sprintf("%3d/%3d  %-48s", $fIdx + 1, $totalFiles, $fn);
    $sql   = @file_get_contents($file);
    if ($sql === false) { echo "$label  FAILED – read error\n"; $oldFails++; continue; }

    $parts = array_values(array_filter(splitSql($sql), fn($s) => strlen(trim($s)) > 0));
    $ok    = true;

    foreach ($parts as $stmt) {
        $stmtClean = trim($stmt);
        if ($stmtClean === '' || preg_match('/^--/', $stmtClean)) continue;
        if (!preg_match('/^(CREATE TABLE|CREATE INDEX|CREATE UNIQUE INDEX|ALTER TABLE)\s/i', $stmtClean)) continue;

        @mysqli_query($mysqli, $stmtClean);
        if (mysqli_errno($mysqli) !== 0 && !preg_match('/already exists/i', mysqli_error($mysqli))) {
            echo "$label  FAILED – DDL error\n";
            $oldFails++;
            $oldErr[] = "$fn – " . mysqli_error($mysqli);
            $ok = false;
            break;
        }
    }
    if ($ok) { echo "$label  OK\n"; $oldSuccess++; }
}
$oldFileno = count($autoFiles);

/// PASS 2 : PARENT INSERTs  (no child-FK dependency, comments-only skipped)
/// ==========================================================================
echo "\n─── PASS 2 – Parent INSERTs ───────────────────────────────────────\n";
mysqli_query($mysqli, "SET SESSION FOREIGN_KEY_CHECKS=0");

// Parent = any file whose INSERTs don't reference a FK to a NON-USER table
// heuristically: if the file contains fewer than 6 FK references in INSERTs,
// treat it as parent; otherwise as child.
// A hard list is clearer and matches the actual schema:
$PARENT_FILES = array_filter($autoFiles, function(string $f): bool {
    $n = basename($f);
    // Child tables (insert ordering matters): skip here
    $CHILDREN = [
        'ai_messages.sql', 'comment_likes.sql', 'comment_reactions.sql',
        'content_ratings.sql', 'cv_items.sql', 'cv_shares.sql',
        'device_control_commands.sql', 'device_sync_logs.sql',
        'donation_payments.sql',
        'email_templates.sql',
        'fcm_tokens.sql',
        'media.sql', 'mobile_images.sql', 'mobile_specs.sql',
        'notification_logs.sql',
        'password_resets.sql', 'post_tags.sql', 'push_incoming_items.sql',
        'role_permissions.sql',
        'scraping_articles.sql', 'scraping_mobiles.sql',
        'user_linked_accounts.sql', 'user_recovery_emails.sql',
        'user_roles.sql', 'user_sessions.sql',
    ];
    return !in_array($n, $CHILDREN, true);
});

list($p2ok, $p2skips, $p2fails, $p2errs) = runBatch(
    array_values($PARENT_FILES), $mysqli, false, true   // fkOff = false (already set), commentsOk = true
);

/// PASS 3 : CHILD INSERTs  (FK checks disabled, comment-only lines skipped)
/// ==========================================================================
echo "\n─── PASS 3 – Child INSERTs ────────────────────────────────────────\n";
mysqli_query($mysqli, "SET SESSION FOREIGN_KEY_CHECKS=0");

$CHILD_FILES = array_filter($autoFiles, function(string $f): bool {
    $n = basename($f);
    $CHILDREN = [
        'ai_messages.sql', 'comment_likes.sql', 'comment_reactions.sql',
        'content_ratings.sql', 'cv_items.sql', 'cv_shares.sql',
        'device_control_commands.sql', 'device_sync_logs.sql',
        'donation_payments.sql',
        'email_templates.sql',
        'fcm_tokens.sql',
        'media.sql', 'mobile_images.sql', 'mobile_specs.sql',
        'notification_logs.sql',
        'password_resets.sql', 'post_tags.sql', 'push_incoming_items.sql',
        'role_permissions.sql',
        'scraping_articles.sql', 'scraping_mobiles.sql',
        'user_linked_accounts.sql', 'user_recovery_emails.sql',
        'user_roles.sql', 'user_sessions.sql',
    ];
    return in_array($n, $CHILDREN, true);
});

list($p3ok, $p3skips, $p3fails, $p3errs) = runBatch(
    array_values($CHILD_FILES), $mysqli, true, false  // fkOff = true, commentsOk = false
);

/// PASS 4 – Manual / special files
/// ======================================
echo "\n─── PASS 4 – Special files (report only) ──────────────────────────\n";
if (empty($manualFiles)) {
    echo "  (none)\n";
} else {
    foreach ($manualFiles as $mIdx => $mf) {
        $mn  = basename($mf);
        $lbl = sprintf("%3d/%3d  %-48s", $mIdx + 1, count($manualFiles), $mn);
        echo "$lbl  SKIPPED – apply manually\n";
    }
    echo "\n  ⚠ Requires manual SQL review:\n";
    foreach ($manualFiles as $mf) {
        echo "      • " . basename($mf) . "\n";
    }
}

/// Re-enable FK checks
/// ====================
@mysqli_query($mysqli, "SET SESSION FOREIGN_KEY_CHECKS=1");

/// SUMMARY
/// =======
$total  = $oldSuccess + $oldFails + $p2ok + $p2fails + $p3ok + $p3fails;
$ok     = $oldSuccess + $p2ok + $p3ok;
$skips  = $oldSkips + $p2skips + $p3skips;
$fails  = $oldFails + $p2fails + $p3fails;
$errors = array_merge($oldErr, $p2errs, $p3errs);

echo "\n";
echo str_repeat('=', 72) . "\n";
echo "  MIGRATION COMPLETE\n";
echo str_repeat('=', 72) . "\n";
echo "  Auto-run SQL files  : " . count($autoFiles) . "\n";
echo "  Manual-review files : " . count($manualFiles) . " (see PASS 4)\n";
echo "  ──────────────────────────────────────────────────────────────────\n";
echo "  Pass 1 (CREATE/DDL) : ok=$oldSuccess  skips=$oldSkips  fails=$oldFails\n";
echo "  Pass 2 (Parents)    : ok=$p2ok  skips=$p2skips  fails=$p2fails\n";
echo "  Pass 3 (Children)   : ok=$p3ok  skips=$p3skips  fails=$p3fails\n";
echo "  ──────────────────────────────────────────────────────────────────\n";
echo "  Total OK    : " . ($oldSuccess + $p2ok + $p3ok) . "\n";
echo "  Total skip  : " . ($oldSkips + $p2skips + $p3skips) . "  (table/key already existed)\n";
echo "  Total FAIL  : " . ($oldFails + $p2fails + $p3fails) . "\n";

$allErrors = array_merge($oldErr, $p2errs, $p3errs);
if ($allErrors) {
    echo "\n  Errors:\n";
    foreach ($allErrors as $e) echo("  • $e\n");
}
echo str_repeat('=', 72) . "\n\n";
echo "  Files needing manual review are listed in PASS 4 above.\n";

if ($fails) {
    echo "  • $e\n";
}
echo str_repeat('=', 72) . "\n\n";
echo "  ⚠ Files needing manual review are listed in PASS 4.\n";
echo "  Total auto-run files: " . count($autoFiles) . "\n";

mysqli_close($mysqli);
