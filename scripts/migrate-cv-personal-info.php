<?php
/**
 * Migration Script: Extract CV personal info from builder_data JSON
 * into the new structured cv_personal_info table.
 *
 * Usage: php scripts/migrate-cv-personal-info.php
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/Config/Db.php';
require_once BASE_PATH . '/app/Models/CvPersonalInfoModel.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli || $mysqli->connect_error) {
    echo "DB connection failed.\n";
    exit(1);
}
echo "Connected.\n";

// Step 1: Create table using multi-query for the full SQL file
$sql = file_get_contents(BASE_PATH . '/Database/cv_personal_info.sql');
if ($mysqli->multi_query($sql)) {
    // Consume all result sets (required after multi_query)
    do { if ($result = $mysqli->store_result()) $result->free(); } while ($mysqli->next_result());
}
if ($mysqli->errno) {
    echo "Table creation error: " . $mysqli->error . "\n";
    exit(1);
}
if ($mysqli->query("SELECT 1 FROM cv_personal_info LIMIT 1") !== false) {
    echo "Table cv_personal_info ready.\n";
} else {
    echo "Table creation failed: " . $mysqli->error . "\n";
    exit(1);
}

// Step 1b: Add new columns if they don't exist (safe to re-run)
$newColumns = [
    'national_id_no' => "ALTER TABLE cv_personal_info ADD COLUMN `national_id_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'National ID / Aadhaar / SSN' AFTER `portfolio`",
    'passport_no' => "ALTER TABLE cv_personal_info ADD COLUMN `passport_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Passport number' AFTER `national_id_no`",
    'birth_certificate_no' => "ALTER TABLE cv_personal_info ADD COLUMN `birth_certificate_no` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Birth certificate number' AFTER `passport_no`",
    'religion' => "ALTER TABLE cv_personal_info ADD COLUMN `religion` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Religion' AFTER `birth_certificate_no`",
];
foreach ($newColumns as $colName => $alterSql) {
    $chk = $mysqli->query("SHOW COLUMNS FROM cv_personal_info LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        if ($mysqli->query($alterSql)) {
            echo "Column `$colName` added.\n";
        } else {
            echo "Column `$colName` error: " . $mysqli->error . "\n";
        }
    } else {
        echo "Column `$colName` already exists.\n";
    }
}

// Step 2: Migrate existing data
$piModel = new CvPersonalInfoModel($mysqli);
$result = $mysqli->query("SELECT c.id, c.user_id, c.builder_data FROM cvs c WHERE c.builder_data IS NOT NULL AND c.builder_data != '' AND c.builder_data != 'null'");
$total = $result->num_rows;
$migrated = 0;
$skipped = 0;
$errors = [];

echo "Found $total CV(s) with builder_data.\n";

while ($row = $result->fetch_assoc()) {
    $cvId = (int)$row['id'];
    $userId = (int)$row['user_id'];
    $bd = json_decode($row['builder_data'], true);
    if (!is_array($bd)) { $skipped++; continue; }
    if (empty($bd['personal'])) { $skipped++; continue; }
    try {
        $extracted = CvPersonalInfoModel::extractFromBuilderData($bd);
        if ($piModel->save($cvId, $userId, $extracted)) {
            $migrated++;
            if ($migrated <= 5) echo "  CV #$cvId migrated.\n";
        } else {
            $errors[] = $cvId;
        }
    } catch (Throwable $e) {
        echo "  Error CV #$cvId: " . $e->getMessage() . "\n";
        $errors[] = $cvId;
    }
}

echo "\nComplete: $total processed, $migrated migrated, $skipped skipped, " . count($errors) . " errors.\n";
exit(count($errors) > 0 ? 1 : 0);
