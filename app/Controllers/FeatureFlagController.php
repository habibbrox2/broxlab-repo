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
