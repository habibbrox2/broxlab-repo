<?php
// Admin setup endpoint for database migrations
// Located at /admin/setup-linked-emails (protected)

$router->get('/admin/setup-linked-emails', ['middleware' => ['auth', 'admin_only']], function() use ($mysqli) {
    try {
        // Read and execute the migration SQL
        $sqlPath = __DIR__ . '/../../Database/user_recovery_emails.sql';
        
        if (!file_exists($sqlPath)) {
            throw new Exception("Migration file not found");
        }

        $sql = file_get_contents($sqlPath);
        
        // Execute the SQL
        if (!$mysqli->query($sql)) {
            // If table already exists, that's OK
            if (strpos($mysqli->error, 'already exists') === false) {
                throw new Exception("Database error: " . $mysqli->error);
            }
        }

        // Verify table exists
        $result = $mysqli->query("SHOW TABLES LIKE 'user_recovery_emails'");
        if ($result && $result->num_rows > 0) {
            $message = "✓ user_recovery_emails table is ready!";
            $success = true;
        } else {
            throw new Exception("Table creation verification failed");
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);

    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});
