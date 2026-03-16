<?php

/**
 * AI Feedback Analysis Script
 * Analyzes user feedback to improve the AI system
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AIFeedback.php';

$feedbackModel = new AIFeedback($mysqli);

echo "=== AI Feedback Analysis ===\n\n";

// Get average rating for last 30 days
$avgRating = $feedbackModel->getAverageRating();
echo "Average Rating (Last 30 days): " . ($avgRating['avg_rating'] ?? 'No data') . " (" . ($avgRating['total_feedback'] ?? 0) . " feedbacks)\n\n";

// Get trends
$trends = $feedbackModel->getFeedbackTrends();
echo "Rating Trends (Last 30 days):\n";
foreach ($trends as $trend) {
    echo "  " . $trend['date'] . ": " . $trend['avg_rating'] . " (" . $trend['count'] . " feedbacks)\n";
}
echo "\n";

// Suggestions
if ($avgRating['avg_rating'] < 3) {
    echo "Suggestions:\n";
    echo "- Consider updating system prompts for better responses.\n";
    echo "- Review low-rated conversations for common issues.\n";
    echo "- Test alternative models or providers.\n";
} else {
    echo "AI performance looks good! Continue monitoring.\n";
}

echo "\n=== End Analysis ===\n";
