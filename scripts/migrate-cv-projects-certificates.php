<?php
/**
 * Migration Script: Extract projects & certificates from builder_data JSON
 * into the V3 normalized cv_projects and cv_certifications tables.
 *
 * The builder UI no longer has steps for projects/certificates, but existing
 * CVs may still have this data in their builder_data JSON column. This script
 * ensures that data is preserved in the normalized V3 child tables so it
 * continues to render in templates, exports, and previews.
 *
 * Safe to re-run (idempotent — skips rows that already have V3 data).
 *
 * Usage: php scripts/migrate-cv-projects-certificates.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/Config/Db.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli || $mysqli->connect_error) {
    echo "Connection failed.\n";
    exit(1);
}
echo "Connected.\n\n";

// Step 1: Ensure V3 child tables exist
$tables = ['cv_profiles', 'cv_certifications', 'cv_projects'];
foreach ($tables as $table) {
    $chk = $mysqli->query("SHOW TABLES LIKE '$table'");
    if (!$chk || $chk->num_rows === 0) {
        $sql = file_get_contents(BASE_PATH . "/Database/{$table}.sql");
        if ($sql && $mysqli->query($sql)) {
            echo "  Table `{$table}` created.\n";
        } else {
            echo "  Failed to create `{$table}`: " . $mysqli->error . "\n";
            exit(1);
        }
    } else {
        echo "  Table `{$table}` exists.\n";
    }
}
echo "\n";

// Step 2: Find CVs with builder_data containing projects or certificates
$result = $mysqli->query(
    "SELECT c.id, c.user_id, c.builder_data
     FROM cvs c
     WHERE c.builder_data IS NOT NULL
       AND c.builder_data != ''
       AND c.builder_data != 'null'
       AND (
         c.builder_data LIKE '%\"projects\"%'
         OR c.builder_data LIKE '%\"certificates\"%'
       )"
);

if (!$result) {
    echo "Query error: " . $mysqli->error . "\n";
    exit(1);
}

$total = $result->num_rows;
echo "Found {$total} CV(s) with projects/certificates in builder_data.\n\n";

$migratedCertificates = 0;
$migratedProjects = 0;
$skipped = 0;
$errors = [];

while ($row = $result->fetch_assoc()) {
    $cvId = (int)$row['id'];
    $userId = (int)$row['user_id'];
    $bd = json_decode($row['builder_data'], true);

    if (!is_array($bd)) {
        $skipped++;
        continue;
    }

    $certificates = $bd['certificates'] ?? [];
    $projects = $bd['projects'] ?? [];

    if (empty($certificates) && empty($projects)) {
        $skipped++;
        continue;
    }

    // Step 3: Find or create the cv_profiles record linked to this cv
    $stmt = $mysqli->prepare("SELECT id FROM cv_profiles WHERE cv_id = ? LIMIT 1");
    $stmt->bind_param('i', $cvId);
    $stmt->execute();
    $profileRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profileRow) {
        $cvStmt = $mysqli->prepare("SELECT title FROM cvs WHERE id = ?");
        $cvStmt->bind_param('i', $cvId);
        $cvStmt->execute();
        $cvInfo = $cvStmt->get_result()->fetch_assoc();
        $cvStmt->close();

        $title = $cvInfo['title'] ?? 'My CV';
        $slugBase = 'cv-' . $userId . '-' . preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($title));
        $slugBase = trim(preg_replace('/-+/', '-', $slugBase), '-');
        $slugBase = substr($slugBase, 0, 200);
        $slug = $slugBase;
        $counter = 0;
        do {
            $chkStmt = $mysqli->prepare("SELECT id FROM cv_profiles WHERE slug = ? LIMIT 1");
            $chkStmt->bind_param('s', $slug);
            $chkStmt->execute();
            $exists = $chkStmt->get_result()->num_rows > 0;
            $chkStmt->close();
            if ($exists) {
                $counter++;
                $slug = $slugBase . '-' . $counter;
            }
        } while ($exists);

        $createStmt = $mysqli->prepare(
            "INSERT INTO cv_profiles (user_id, title, slug, is_active, cv_id, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, NOW(), NOW())"
        );
        $createStmt->bind_param('issi', $userId, $title, $slug, $cvId);
        if (!$createStmt->execute()) {
            $errors[] = "CV #{$cvId}: failed to create profile - " . $createStmt->error;
            $createStmt->close();
            continue;
        }
        $profileId = (int)$createStmt->insert_id;
        $createStmt->close();
        echo "  Created cv_profiles #{$profileId} for CV #{$cvId}\n";
    } else {
        $profileId = (int)$profileRow['id'];
    }

    // Step 4: Migrate certificates (skip if already migrated)
    if (!empty($certificates)) {
        $chkStmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM cv_certifications WHERE profile_id = ?");
        $chkStmt->bind_param('i', $profileId);
        $chkStmt->execute();
        $existingCount = (int)$chkStmt->get_result()->fetch_assoc()['cnt'];
        $chkStmt->close();

        if ($existingCount === 0) {
            $insertStmt = $mysqli->prepare(
                "INSERT INTO cv_certifications (profile_id, name, organization, date, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $order = 0;
            foreach ($certificates as $cert) {
                if (empty($cert['name'])) continue;
                $name = $cert['name'] ?? '';
                $org = $cert['organization'] ?? $cert['issuer'] ?? '';
                $date = $cert['issue_date'] ?? $cert['date'] ?? '';
                $insertStmt->bind_param('isssi', $profileId, $name, $org, $date, $order);
                if ($insertStmt->execute()) {
                    $migratedCertificates++;
                } else {
                    $errors[] = "CV #{$cvId}: cert insert error - " . $insertStmt->error;
                }
                $order++;
            }
            $insertStmt->close();
        } else {
            echo "  Profile #{$profileId} already has {$existingCount} certification(s), skipping.\n";
        }
    }

    // Step 5: Migrate projects (skip if already migrated)
    if (!empty($projects)) {
        $chkStmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM cv_projects WHERE profile_id = ?");
        $chkStmt->bind_param('i', $profileId);
        $chkStmt->execute();
        $existingCount = (int)$chkStmt->get_result()->fetch_assoc()['cnt'];
        $chkStmt->close();

        if ($existingCount === 0) {
            $insertStmt = $mysqli->prepare(
                "INSERT INTO cv_projects (profile_id, name, description, technologies, url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $order = 0;
            foreach ($projects as $proj) {
                if (empty($proj['name'])) continue;
                $name = $proj['name'] ?? '';
                $desc = $proj['description'] ?? '';
                $tech = $proj['technologies'] ?? '';
                $url = $proj['url'] ?? '';
                $insertStmt->bind_param('issssi', $profileId, $name, $desc, $tech, $url, $order);
                if ($insertStmt->execute()) {
                    $migratedProjects++;
                } else {
                    $errors[] = "CV #{$cvId}: project insert error - " . $insertStmt->error;
                }
                $order++;
            }
            $insertStmt->close();
        } else {
            echo "  Profile #{$profileId} already has {$existingCount} project(s), skipping.\n";
        }
    }
}

// Report
echo "\n";
echo "--- MIGRATION REPORT ---\n";
echo "  CVs processed:   {$total}\n";
echo "  Certificates:    {$migratedCertificates} rows inserted\n";
echo "  Projects:        {$migratedProjects} rows inserted\n";
echo "  Skipped (empty): {$skipped}\n";
echo "  Errors:          " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nError details:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
}

if (count($errors) === 0 && ($migratedCertificates > 0 || $migratedProjects > 0)) {
    echo "\nMigration complete. Projects and certificates are now preserved in V3 tables.\n";
    echo "Old builder_data JSON remains unchanged as a backup.\n";
} elseif (count($errors) === 0) {
    echo "\nNo orphaned data found - everything was already migrated.\n";
} else {
    echo "\nCompleted with " . count($errors) . " error(s). Review above.\n";
}

echo "\n";
exit(count($errors) > 0 ? 1 : 0);
