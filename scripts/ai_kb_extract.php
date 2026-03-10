<?php
// scripts/ai_kb_extract.php
// Extract text from uploaded PDFs (if pdftotext available) and create chunks in ai_knowledge_chunks table.

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
require_once __DIR__ . '/../Config/Db.php';

echo "Starting AI KB extraction...\n";

// Ensure ai_knowledge_chunks table exists
$create = "CREATE TABLE IF NOT EXISTS ai_knowledge_chunks (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kb_id INT(11) NOT NULL,
    chunk_text LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (kb_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$mysqli->query($create);

// Select KB entries that are PDFs or contain FILEPATH marker
$sql = "SELECT id, title, content, source_type FROM ai_knowledge_base WHERE source_type = 'pdf' OR content LIKE 'FILEPATH:%'";
$res = $mysqli->query($sql);
if (!$res) {
    echo "DB error: " . $mysqli->error . "\n";
    exit(1);
}

$processed = 0;
while ($row = $res->fetch_assoc()) {
    $kbId = (int)$row['id'];
    $content = $row['content'] ?? '';
    $text = '';

    if (str_starts_with($content, 'FILEPATH:')) {
        $path = substr($content, strlen('FILEPATH:'));
        // Map public path to filesystem path
        $fsPath = __DIR__ . '/..' . $path;
        if (file_exists($fsPath)) {
            // Try pdftotext
            $cmd = 'pdftotext -layout ' . escapeshellarg($fsPath) . ' -';
            $out = null;
            $ret = null;
            exec($cmd, $out, $ret);
            if ($ret === 0) {
                $text = implode("\n", $out);
            } else {
                echo "pdftotext failed for $fsPath (exit $ret)\n";
            }
        } else {
            echo "File not found: $fsPath\n";
        }
    }

    // If source_type is pdf but content not FILEPATH, try to treat content as raw pdf binary path (unlikely)
    if ($text === '' && $row['source_type'] === 'pdf' && !str_starts_with($content, 'FILEPATH:')) {
        // No extraction available
        echo "No extractable content for KB id $kbId\n";
        continue;
    }

    if (trim($text) === '') continue;

    // Normalize whitespace
    $clean = preg_replace('/\s+/', ' ', strip_tags($text));
    // Chunk into ~800-char segments by words
    $max = 800;
    $pos = 0;
    $len = mb_strlen($clean);
    while ($pos < $len) {
        $chunk = mb_substr($clean, $pos, $max);
        // try to break on last space
        if (mb_strlen($chunk) === $max) {
            $lastSpace = mb_strrpos($chunk, ' ');
            if ($lastSpace !== false) {
                $chunk = mb_substr($chunk, 0, $lastSpace);
                $pos += $lastSpace + 1;
            } else {
                $pos += $max;
            }
        } else {
            $pos += mb_strlen($chunk);
        }

        // Insert chunk
        $stmt = $mysqli->prepare("INSERT INTO ai_knowledge_chunks (kb_id, chunk_text) VALUES (?, ?)");
        $stmt->bind_param('is', $kbId, $chunk);
        $stmt->execute();
        $stmt->close();
    }

    $processed++;
    echo "Processed KB id $kbId\n";
}

echo "Done. Processed: $processed entries.\n";
