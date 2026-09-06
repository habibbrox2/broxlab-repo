<?php
/**
 * One-time migration: backfill missing cv_profiles rows from cv_infos.
 *
 * This script:
 *   1. Finds user-owned CV rows in cv_infos
 *   2. Creates a matching cv_profiles row when one does not exist
 *   3. Imports the structured CV payload into the normalized V3 child tables
 *   4. Skips rows that already have a linked profile
 *
 * Safe to re-run. Existing profiles are not duplicated.
 *
 * Usage:
 *   php scripts/backfill-cv-profiles.php           # dry run
 *   php scripts/backfill-cv-profiles.php --execute # apply changes
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/Config/Db.php';
require_once BASE_PATH . '/app/Services/CvSchemaBootstrapService.php';
require_once BASE_PATH . '/app/Services/CvProfileService.php';
require_once BASE_PATH . '/app/Models/CvModel.php';

$isDryRun = !in_array('--execute', $argv ?? [], true);

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli || $mysqli->connect_error) {
    echo "Connection failed.\n";
    exit(1);
}

$mysqli->set_charset('utf8mb4');

$cvModel = new CvModel($mysqli);
$profileService = new CvProfileService($mysqli);

echo ">> BACKFILL CV PROFILES <<\n\n";
echo $isDryRun ? "[DRY RUN] No changes will be written.\n\n" : "[LIVE MODE] Changes will be written.\n\n";

$result = $mysqli->query(
    "SELECT id, user_id, title, template
     FROM cv_infos
     WHERE deleted_at IS NULL
     ORDER BY id ASC"
);

if (!$result) {
    echo "Failed to read cv_infos: " . $mysqli->error . "\n";
    exit(1);
}

$processed = 0;
$created = 0;
$imported = 0;
$skippedExisting = 0;
$skippedGuest = 0;
$errors = [];

while ($row = $result->fetch_assoc()) {
    $processed++;
    $cvId = (int)($row['id'] ?? 0);
    $userId = (int)($row['user_id'] ?? 0);

    if ($cvId <= 0) {
        $errors[] = 'Encountered invalid CV id in cv_infos.';
        continue;
    }

    if ($userId <= 0) {
        $skippedGuest++;
        continue;
    }

    $existingProfileId = $profileService->getProfileIdByCvId($cvId);
    $builderData = $cvModel->getBuilderData($cvId);

    if ($existingProfileId !== null) {
        $skippedExisting++;
        continue;
    }

    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        $title = 'My CV';
    }
    $template = trim((string)($row['template'] ?? ''));
    if ($template === '') {
        $template = 'modern';
    }

    echo "CV #{$cvId}: creating profile for user #{$userId} ... ";

    if ($isDryRun) {
        echo "dry run\n";
        $created++;
        continue;
    }

    $profileId = $profileService->create($userId, $title, $template, $cvId);
    if ($profileId === null) {
        $errors[] = "CV #{$cvId}: failed to create cv_profiles row.";
        echo "failed\n";
        continue;
    }

    $created++;

    if (!empty($builderData)) {
        if ($profileService->saveAllBuilderDataToV3($profileId, $builderData)) {
            $profileService->calculateCompletionScore($profileId);
            $imported++;
            echo "created + imported\n";
        } else {
            $errors[] = "CV #{$cvId}: created profile #{$profileId}, but failed to import structured data.";
            echo "created + import failed\n";
        }
    } else {
        echo "created\n";
    }
}

echo "\n--- BACKFILL REPORT ---\n";
echo "Processed:        {$processed}\n";
echo "Profiles created:  {$created}\n";
echo "Payload imported:  {$imported}\n";
echo "Skipped existing:  {$skippedExisting}\n";
echo "Skipped guest:     {$skippedGuest}\n";
echo "Errors:            " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nError details:\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
}

echo "\n";
exit(!empty($errors) ? 1 : 0);
