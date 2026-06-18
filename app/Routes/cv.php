<?php

/**
 * app/Routes/cv.php
 * 
 * CV Management Route Definitions.
 * Handler logic is in app/Controllers/CvController.php (static methods).
 * Admin CV routes are in app/Controllers/DashboardController.php.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$cvModel        = new CvModel($mysqli);
$cvSectionModel = new CvSectionModel($mysqli);
$cvItemModel    = new CvItemModel($mysqli);
$cvShareModel   = new CvShareModel($mysqli);
$cvVersionModel = new CvVersionModel($mysqli);
$cvAnalyticsModel = new CvAnalyticsModel($mysqli);
$cvRateLimitModel = new CvRateLimitModel($mysqli);

// ============================================================================
// USER-FACING CV ROUTES — handlers in CvController
// ============================================================================

// ── Template Marketplace ──
$router->get('/cv-builder/templates', ['CvController', 'marketplace']);

// ── CV Dashboard ──
$router->get('/cv-builder', ['middleware' => ['auth']], ['CvController', 'dashboard']);

// ── Create New CV Page ──
$router->get('/cv-builder/new', ['middleware' => ['auth']], ['CvController', 'createForm']);

// ── Builder Wizard ──
$router->get('/cv-builder/builder/{id}', ['middleware' => ['auth']], ['CvController', 'builder']);

// ── Builder API ──
$router->post('/api/cv/builder/{id}/step', ['middleware' => ['auth', 'csrf']], ['CvBuilderController', 'saveBuilderStep']);
$router->get('/api/cv/builder/{id}/progress', ['middleware' => ['auth']], ['CvBuilderController', 'builderProgress']);
$router->post('/api/cv/builder/{id}/complete', ['middleware' => ['auth', 'csrf']], ['CvBuilderController', 'completeBuilder']);

// ── CRUD ──
$router->post('/cv-builder', ['middleware' => ['auth', 'csrf']], ['CvController', 'store']);
$router->get('/cv-builder/form-data', ['middleware' => ['auth']], ['CvController', 'formData']);
$router->post('/cv-builder/save', ['middleware' => ['auth', 'csrf']], ['CvController', 'save']);

// ── Redirect Shortcuts ──
$router->get('/cv-builder/download', ['middleware' => ['auth']], ['CvController', 'redirectDownload']);
$router->get('/cv-builder/share', ['middleware' => ['auth']], ['CvController', 'redirectShare']);
$router->get('/cv-builder/view', ['middleware' => ['auth']], ['CvController', 'redirectView']);

// ── CV Detail / Update / Duplicate / Delete ──
$router->get('/cv-builder/{id}', ['middleware' => ['auth']], ['CvController', 'redirectToBuilder']);
$router->put('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], ['CvController', 'update']);
$router->post('/cv-builder/{id}/update', ['middleware' => ['auth', 'csrf']], ['CvController', 'updateForm']);
$router->post('/cv-builder/{id}/duplicate', ['middleware' => ['auth', 'csrf']], ['CvController', 'duplicate']);
$router->delete('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], ['CvController', 'delete']);

// ── Personal Info API ──
$router->get('/api/cv/{id}/personal-info', ['middleware'=>['auth']], ['CvBuilderController', 'apiGetPersonalInfo']);
$router->post('/api/cv/{id}/personal-info', ['middleware'=>['auth','csrf']], ['CvBuilderController', 'apiSavePersonalInfo']);

// ── Section Management ──
$router->post('/cv-builder/{cv_id}/sections',['middleware'=>['auth','csrf']], ['CvBuilderController', 'createSection']);
$router->put('/cv-builder/{cv_id}/sections/{section_id}',['middleware'=>['auth','csrf']], ['CvBuilderController', 'updateSection']);
$router->delete('/cv-builder/{cv_id}/sections/{section_id}',['middleware'=>['auth','csrf']], ['CvBuilderController', 'deleteSection']);
$router->patch('/cv-builder/{cv_id}/sections/reorder',['middleware'=>['auth','csrf']], ['CvBuilderController', 'reorderSections']);

// ── Item Management ──
$router->post('/cv-builder/{cv_id}/sections/{section_id}/items',['middleware'=>['auth','csrf']], ['CvBuilderController', 'createItem']);
$router->put('/cv-builder/{cv_id}/sections/{section_id}/items/{item_id}',['middleware'=>['auth','csrf']], ['CvBuilderController', 'updateItem']);
$router->delete('/cv-builder/{cv_id}/sections/{section_id}/items/{item_id}',['middleware'=>['auth','csrf']], ['CvBuilderController', 'deleteItem']);
$router->patch('/cv-builder/{cv_id}/sections/{section_id}/items/reorder',['middleware'=>['auth','csrf']], ['CvBuilderController', 'reorderItems']);

// ── Preview ──
$router->get('/api/cv/{id}/preview', ['middleware'=>['auth']], ['CvExportController', 'apiPreview']);
$router->get('/cv-builder/{id}/preview', ['middleware'=>['auth']], ['CvExportController', 'preview']);

// ── Export ──
$router->get('/cv-builder/{id}/export', ['middleware'=>['auth']], ['CvExportController', 'redirectExport']);
$router->get('/cv-builder/{id}/export/pdf', ['middleware'=>['auth']], ['CvExportController', 'exportPdf']);
$router->get('/cv-builder/{id}/export/docx', ['middleware'=>['auth']], ['CvExportController', 'exportDocx']);

// ── Sharing ──
$router->post('/cv-builder/{id}/share', ['middleware'=>['auth','csrf']], ['CvExportController', 'share']);
$router->delete('/cv-builder/{id}/share', ['middleware'=>['auth','csrf']], ['CvExportController', 'revokeShare']);
$router->get('/cv-builder/view/{token}', ['CvExportController', 'publicView']);

// ── AI Features ──
$router->post('/cv-builder/{id}/ai/cover-letter', ['middleware'=>['auth','csrf']], ['CvAiController', 'aiCoverLetter']);
$router->post('/cv-builder/{id}/ai/improve', ['middleware'=>['auth','csrf']], ['CvAiController', 'aiImprove']);
$router->post('/cv-builder/{id}/ai/ats-score', ['middleware'=>['auth','csrf']], ['CvAiController', 'aiAtsScore']);

// ── Bulk Operations ──
$router->post('/cv-builder/bulk/delete', ['middleware'=>['auth','csrf']], ['CvAiController', 'bulkDelete']);
$router->post('/cv-builder/bulk/export', ['middleware'=>['auth','csrf']], ['CvAiController', 'bulkExport']);

// ── Version History ──
$router->get('/cv-builder/{id}/versions', ['middleware'=>['auth']], ['CvController', 'listVersions']);
$router->get('/cv-builder/{id}/versions/{version}', ['middleware'=>['auth']], ['CvController', 'getVersion']);
$router->post('/cv-builder/{id}/versions/{version}/restore', ['middleware'=>['auth','csrf']], ['CvController', 'restoreVersion']);
$router->get('/cv-builder/{id}/versions/compare/{v1}/{v2}', ['middleware'=>['auth']], ['CvController', 'compareVersions']);

// ── Analytics & Rate Limits ──
$router->get('/cv-builder/{id}/analytics', ['middleware'=>['auth']], ['CvController', 'cvAnalytics']);
$router->get('/cv-builder/analytics/summary', ['middleware'=>['auth']], ['CvController', 'analyticsSummary']);
$router->get('/cv-builder/rate-limits', ['middleware'=>['auth']], ['CvController', 'rateLimits']);

// ── V3 Write-Through Bridge ──
$router->post('/api/cv/{id}/migrate-to-v3', ['middleware' => ['auth', 'csrf']], ['CvPurchaseController', 'migrateToV3']);
$router->post('/api/cv/migrate-all-to-v3', ['middleware' => ['auth', 'csrf']], ['CvPurchaseController', 'migrateAllToV3']);

// ── Photo Upload ──
$router->post('/api/cv/{id}/photo', ['middleware'=>['auth']], ['CvPurchaseController', 'uploadPhoto']);
$router->delete('/api/cv/{id}/photo', ['middleware'=>['auth']], ['CvPurchaseController', 'deletePhoto']);

// ── Template Preview API (public, no auth) ──
$router->get('/api/cv/templates/{slug}/preview', ['CvController', 'templatePreview']);

// ── Premium Template Purchases (User-facing) ──
$router->get('/api/cv/templates/purchased/{slug}', ['middleware' => ['auth']], ['CvPurchaseController', 'checkPurchased']);
$router->get('/api/cv/templates/my-purchases', ['middleware' => ['auth']], ['CvPurchaseController', 'myPurchases']);
$router->post('/api/cv/templates/initiate-purchase', ['middleware' => ['auth', 'csrf']], ['CvPurchaseController', 'initiatePurchase']);
$router->post('/api/cv/templates/verify-purchase', ['middleware' => ['auth', 'csrf']], ['CvPurchaseController', 'verifyPurchase']);

// ── bKash Checkout API ──
$router->post('/api/cv/templates/bkash-checkout', ['middleware' => ['auth', 'csrf']], ['CvPurchaseController', 'bkashCheckout']);
$router->get('/payments/cv/bkash/callback', ['CvPurchaseController', 'bkashCallback']);
$router->post('/payments/cv/bkash/callback', ['CvPurchaseController', 'bkashCallback']);
