<?php
/**
 * scripts/drop-cv-builder-data-column.php
 *
 * One-time migration: Safely drop the `builder_data` column from `cv_infos`.
 *
 * This script:
 *   1. Checks that V3 tables exist (cv_profiles)
 *   2. Counts CVs with builder_data still present
 *   3. Offers a dry-run mode (default) or live execution
 *   4. Drops the column with IF EXISTS safety
 *
 * Usage:
 *   php scripts/drop-cv-builder-data-column.php                    # dry-run
 *   php scripts/drop-cv-builder-data-column.php --execute          # live
 *   php scripts/drop-cv-builder-data-column.php --force            # skip confirmation
 *
 * Requirements:
 *   - ../public_html/_db.php for DB credentials
 */
declare(strict_types=1);
require_once __DIR__ . '/../public_html/_db.php';
$isDryRun = !in_array('--execute', $argv ?? [], true);
$force = in_array('--force', $argv ?? [], true);
echo ">> DROP builder_data COLUMN -- V3 Migration <<\n\n";
echo ($isDryRun ? "[DRY RUN] No changes made.\n" : "[LIVE MODE]\n") . "\n";
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) die("Connection failed\n");
$mysqli->set_charset('utf8mb4');
echo "Connected to database\n\n";
try {
  echo "[1/5] Checking cv_infos table... ";
  $r = $mysqli->query("SHOW TABLES LIKE 'cv_infos'");
  if (!$r || $r->num_rows === 0) die("cv_infos not found\n");
  echo "OK\n";
  echo "[2/5] Checking builder_data column... ";
  $r = $mysqli->query("SHOW COLUMNS FROM cv_infos LIKE 'builder_data'");
  if (!$r || $r->num_rows === 0) { echo "already removed. Exiting.\n"; exit(0); }
  echo "still present\n";
  echo "[3/5] Analyzing data... ";
  $r = $mysqli->query("SELECT COUNT(*) as c FROM cv_infos WHERE builder_data IS NOT NULL AND builder_data != '' AND builder_data != 'null' AND deleted_at IS NULL");
  $count = (int)($r->fetch_assoc()['c'] ?? 0);
  echo "{$count} CV(s) have builder_data\n";
  echo "[4/5] Checking V3 tables... ";
  $missing = [];
  foreach (['cv_profiles','cv_educations','cv_experiences','cv_skills','cv_languages'] as $t) {
    $r = $mysqli->query("SHOW TABLES LIKE '$t'");
    if (!$r || $r->num_rows === 0) $missing[] = $t;
  }
  if (!empty($missing)) { echo "MISSING: " . implode(', ', $missing) . " -- abort\n"; exit(1); }
  echo "OK\n";
  echo "[5/5] " . ($isDryRun ? "Would drop" : "Dropping") . " builder_data... ";
  if (!$isDryRun) {
    if (!$force) {
      echo "\n     Type 'yes' to drop from {$count} CV(s): ";
      $h = fopen('php://stdin','r'); $a = trim(fgets($h)); fclose($h);
      if (strtolower($a) !== 'yes') { echo "Cancelled\n"; exit(0); }
    }
    $mysqli->query("SET FOREIGN_KEY_CHECKS=0");
    $mysqli->query("ALTER TABLE cv_infos DROP COLUMN IF EXISTS builder_data");
    $mysqli->query("ALTER TABLE cv_infos COMMENT = 'Single CV table -- no builder_data (V3 normalized)'");
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1");
    echo "Done!\n";
  } else { echo "(skipped -- dry run)\n"; }
  echo "\n" . ($isDryRun ? "Dry run complete. Pass --execute to apply." : "Migration complete!") . "\n";
} catch (Throwable $e) { echo "\nERROR: {$e->getMessage()}\n"; $mysqli->query("SET FOREIGN_KEY_CHECKS=1"); $mysqli->close(); exit(1); }
$mysqli->close();
