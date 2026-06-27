<?php
declare(strict_types = 1)
;

namespace App\Controllers;

use mysqli;
use App\FeatureFlags\FeatureManager;

/**
 * FeatureFlagController.php
 * Controller for managing feature flags in the Admin Panel.
 */
class FeatureFlagController
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function index(): array
    {
        $result = $this->mysqli->query("SELECT * FROM feature_flags");
        $features = [];
        while ($row = $result->fetch_assoc()) {
            $features[] = $row;
        }
        return ['features' => $features];
    }

    public function toggle(): void
    {
        $id = (int)$_POST['id'];
        $this->mysqli->query("UPDATE feature_flags SET enabled = NOT enabled WHERE id = $id");
    // Redirect or return JSON
    }
}

// ============================================================
// Procedural routes for admin feature flags UI
// ============================================================

/** @var \Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$router->get('/admin/feature-flags', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    $features = [];
    $result = $mysqli->query("SELECT * FROM feature_flags ORDER BY feature_key ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $features[] = $row;
        }
    }
    echo $twig->render('admin/settings/feature_flags.twig', [
        'features' => $features,
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ]);
});

$router->post('/admin/feature-flags/toggle', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    $id = (int)($_POST['id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        showMessage('CSRF token invalid', 'danger');
        header('Location: /admin/feature-flags');
        exit;
    }
    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE feature_flags SET enabled = NOT enabled WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        showMessage('Feature flag toggled successfully', 'success');
    } else {
        showMessage('Invalid feature flag ID', 'danger');
    }
    header('Location: /admin/feature-flags');
    exit;
});
