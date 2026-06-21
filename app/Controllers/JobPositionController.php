<?php

/**
 * app/Controllers/JobPositionController.php
 *
 * Job Position Content Library Controller
 * Manages job positions, professional summaries, career objectives,
 * bullet points, and skill suggestions for the CV Builder system.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$jobPositionModel = new JobPositionModel($mysqli);

// ========================================================================
// ADMIN ROUTES
// ========================================================================

if (!defined('BROX_JOB_POSITION_ADMIN_ROUTES_HANDLED')) {
    define('BROX_JOB_POSITION_ADMIN_ROUTES_HANDLED', true);

    // LIST POSITIONS
    $router->get('/admin/job-positions', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $jobPositionModel) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = sanitize_input($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';
        $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $positions = $jobPositionModel->getAllPositions($limit, $offset, $search, $status);
        $total = $jobPositionModel->countAllPositions($search, $status);
        $totalPages = max(1, (int)ceil($total / $limit));
        $stats = $jobPositionModel->getStatistics();
        echo $twig->render('admin/job-positions/list.twig', [
            'positions' => $positions, 'stats' => $stats,
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'total_pages' => $totalPages, 'search' => $search, 'status' => $status,
            'page_title' => 'Job Positions Library', 'current_page' => 'job-positions'
        ]);
    });

    // CREATE POSITION FORM
    $router->get('/admin/job-positions/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
        echo $twig->render('admin/job-positions/form.twig', [
            'mode' => 'create', 'page_title' => 'Create Job Position', 'current_page' => 'job-positions'
        ]);
    });

    // CREATE POSITION
    $router->post('/admin/job-positions', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig, $jobPositionModel) {
        $title = sanitize_input($_POST['title'] ?? '');
        $slug = sanitize_input($_POST['slug'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $errors = [];
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($slug)) { $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($title))); $slug = trim($slug, '-'); }
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) $errors[] = 'Slug must be lowercase alphanumeric with hyphens only.';
        if (empty($errors) && $jobPositionModel->getPositionBySlug($slug)) $errors[] = 'A position with this slug already exists.';
        if (!empty($errors)) {
            echo $twig->render('admin/job-positions/form.twig', [
                'mode' => 'create', 'page_title' => 'Create Job Position',
                'current_page' => 'job-positions', 'errors' => $errors,
                'form_data' => ['title' => $title, 'slug' => $slug, 'category' => $category, 'description' => $description]
            ]);
            return;
        }
        $positionId = $jobPositionModel->createPosition($title, $slug, !empty($category) ? $category : null, !empty($description) ? $description : null);
        if ($positionId) {
            logActivity("Job Position Created", "job-position", $positionId, ['title' => $title, 'slug' => $slug], 'success');
            showMessage("Job position '{$title}' created successfully.", 'success');
            header('Location: /admin/job-positions/' . $positionId . '/edit');
            exit;
        }
        showMessage('Failed to create job position.', 'danger');
        header('Location: /admin/job-positions');
        exit;
    });

    // EDIT POSITION FORM
    $router->get('/admin/job-positions/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $jobPositionModel) {
        $id = (int)$id;
        $position = $jobPositionModel->getPositionWithContent($id);
        if (!$position) { showMessage('Job position not found.', 'danger'); header('Location: /admin/job-positions'); exit; }
        echo $twig->render('admin/job-positions/form.twig', [
            'mode' => 'edit', 'position' => $position, 'position_id' => $id,
            'page_title' => 'Edit: ' . $position['title'], 'current_page' => 'job-positions'
        ]);
    });

    // UPDATE POSITION
    $router->post('/admin/job-positions/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($twig, $jobPositionModel) {
        $id = (int)$id;
        $position = $jobPositionModel->getPositionById($id);
        if (!$position) { showMessage('Job position not found.', 'danger'); header('Location: /admin/job-positions'); exit; }
        $title = sanitize_input($_POST['title'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $errors = [];
        if (empty($title)) $errors[] = 'Title is required.';
        if (!empty($errors)) {
            $position = $jobPositionModel->getPositionWithContent($id);
            $position['title'] = $title; $position['category'] = $category; $position['description'] = $description;
            echo $twig->render('admin/job-positions/form.twig', [
                'mode' => 'edit', 'position' => $position, 'position_id' => $id,
                'page_title' => 'Edit: ' . $title, 'current_page' => 'job-positions', 'errors' => $errors
            ]);
            return;
        }
        $updated = $jobPositionModel->updatePosition($id, [
            'title' => $title, 'category' => !empty($category) ? $category : null,
            'description' => !empty($description) ? $description : null, 'is_active' => $isActive
        ]);
        handlePositionContentUpdates($id, $jobPositionModel);
        if ($updated) {
            logActivity("Job Position Updated", "job-position", $id, ['title' => $title], 'success');
            showMessage("Job position '{$title}' updated successfully.", 'success');
        } else { showMessage('No changes were made.', 'info'); }
        header('Location: /admin/job-positions/' . $id . '/edit');
        exit;
    });

    // TOGGLE STATUS
    $router->post('/admin/job-positions/{id}/toggle', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($jobPositionModel) {
        $id = (int)$id;
        if ($jobPositionModel->togglePositionStatus($id)) {
            $position = $jobPositionModel->getPositionById($id);
            $status = $position['is_active'] ? 'activated' : 'deactivated';
            logActivity("Job Position {$status}", 'job-position', $id, [], 'success');
            showMessage("Job position {$status} successfully.", 'success');
        } else { showMessage('Failed to toggle job position status.', 'danger'); }
        header('Location: /admin/job-positions');
        exit;
    });

    // DELETE POSITION
    $router->post('/admin/job-positions/{id}/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($jobPositionModel) {
        $id = (int)$id;
        if ($jobPositionModel->deletePosition($id)) {
            logActivity("Job Position Deleted", 'job-position', $id, [], 'success');
            showMessage('Job position deleted successfully.', 'success');
        } else { showMessage('Failed to delete job position.', 'danger'); }
        header('Location: /admin/job-positions');
        exit;
    });
}

// ========================================================================
// PUBLIC API ROUTES (no auth required)
// ========================================================================

$router->get('/api/job-positions', function () use ($jobPositionModel) {
    $positions = $jobPositionModel->getActivePositions();
    json_response(['success' => true, 'positions' => $positions]);
});

$router->get('/api/job-positions/{id}', function ($id) use ($jobPositionModel) {
    $id = (int)$id;
    $position = $jobPositionModel->getPositionWithContent($id);
    if (!$position || !$position['is_active']) { json_response(['success' => false, 'error' => 'Position not found'], 404); return; }
    json_response(['success' => true, 'position' => $position]);
});

$router->get('/api/job-positions/slug/{slug}', function ($slug) use ($jobPositionModel) {
    $slug = sanitize_input($slug);
    $position = $jobPositionModel->getPositionBySlug($slug);
    if (!$position || !$position['is_active']) { json_response(['success' => false, 'error' => 'Position not found'], 404); return; }
    $content = $jobPositionModel->getPositionWithContent((int)$position['id']);
    json_response(['success' => true, 'position' => $content]);
});

$router->get('/api/job-positions/categories', function () use ($jobPositionModel) {
    $categories = $jobPositionModel->getCategories();
    json_response(['success' => true, 'categories' => $categories]);
});

// ========================================================================
// ADMIN API ROUTES (auth required)
// ========================================================================

$router->get('/api/admin/job-positions/stats', ['middleware' => ['auth', 'admin_only']], function () use ($jobPositionModel) {
    $stats = $jobPositionModel->getStatistics();
    json_response(['success' => true, 'stats' => $stats]);
});

$router->post('/api/admin/job-positions/{id}/summaries', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($jobPositionModel) {
    $id = (int)$id;
    $data = json_decode(file_get_contents('php://input'), true);
    $content = sanitize_input($data['content'] ?? '');
    $type = in_array($data['type'] ?? '', ['professional_summary', 'career_objective']) ? $data['type'] : 'professional_summary';
    if (empty($content)) { json_response(['success' => false, 'error' => 'Content is required'], 400); return; }
    $summaryId = $jobPositionModel->addSummary($id, $content, $type);
    $summaryId ? json_response(['success' => true, 'id' => $summaryId]) : json_response(['success' => false, 'error' => 'Failed to add summary'], 500);
});

$router->post('/api/admin/job-positions/{id}/bullets', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($jobPositionModel) {
    $id = (int)$id;
    $data = json_decode(file_get_contents('php://input'), true);
    $content = sanitize_input($data['content'] ?? '');
    $category = in_array($data['category'] ?? '', ['responsibilities', 'achievements']) ? $data['category'] : 'responsibilities';
    if (empty($content)) { json_response(['success' => false, 'error' => 'Content is required'], 400); return; }
    $bulletId = $jobPositionModel->addBullet($id, $content, $category);
    $bulletId ? json_response(['success' => true, 'id' => $bulletId]) : json_response(['success' => false, 'error' => 'Failed to add bullet'], 500);
});

$router->post('/api/admin/job-positions/{id}/skills', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($jobPositionModel) {
    $id = (int)$id;
    $data = json_decode(file_get_contents('php://input'), true);
    $skillName = sanitize_input($data['skill_name'] ?? '');
    $category = in_array($data['category'] ?? '', ['technical', 'soft', 'language']) ? $data['category'] : 'technical';
    if (empty($skillName)) { json_response(['success' => false, 'error' => 'Skill name is required'], 400); return; }
    $skillId = $jobPositionModel->addSkill($id, $skillName, $category);
    $skillId ? json_response(['success' => true, 'id' => $skillId]) : json_response(['success' => false, 'error' => 'Failed to add skill'], 500);
});

// ========================================================================
// HELPERS
// ========================================================================

if (!function_exists('handlePositionContentUpdates')) {
    function handlePositionContentUpdates(int $positionId, JobPositionModel $model): void
    {
        // Extract all POST arrays
        $summaryIds = $_POST['summary_id'] ?? [];
        $summaryContents = $_POST['summary_content'] ?? [];
        $summaryTypes = $_POST['summary_type'] ?? [];
        $summaryOrders = $_POST['summary_order'] ?? [];
        $summaryDelete = $_POST['summary_delete'] ?? [];

        $bulletIds = $_POST['bullet_id'] ?? [];
        $bulletContents = $_POST['bullet_content'] ?? [];
        $bulletCategories = $_POST['bullet_category'] ?? [];
        $bulletOrders = $_POST['bullet_order'] ?? [];
        $bulletDelete = $_POST['bullet_delete'] ?? [];

        $skillIds = $_POST['skill_id'] ?? [];
        $skillNames = $_POST['skill_name'] ?? [];
        $skillCategories = $_POST['skill_category'] ?? [];
        $skillOrders = $_POST['skill_order'] ?? [];
        $skillDelete = $_POST['skill_delete'] ?? [];

        $newSummaryContents = $_POST['new_summary_content'] ?? [];
        $newSummaryTypes = $_POST['new_summary_type'] ?? [];
        $newBulletContents = $_POST['new_bullet_content'] ?? [];
        $newBulletCategories = $_POST['new_bullet_category'] ?? [];
        $newSkillNames = $_POST['new_skill_name'] ?? [];
        $newSkillCategories = $_POST['new_skill_category'] ?? [];

        // Update existing summaries
        if (is_array($summaryIds)) {
            foreach ($summaryIds as $index => $sid) {
                $sid = (int)$sid;
                if (isset($summaryDelete[$index]) && $summaryDelete[$index]) {
                    $model->deleteSummary($sid);
                } elseif (!empty($summaryContents[$index])) {
                    $model->updateSummary($sid, [
                        'content' => sanitize_input($summaryContents[$index]),
                        'type' => $summaryTypes[$index] ?? 'professional_summary',
                        'sort_order' => (int)($summaryOrders[$index] ?? $index)
                    ]);
                }
            }
        }

        // Add new summaries
        if (is_array($newSummaryContents)) {
            foreach ($newSummaryContents as $index => $content) {
                if (!empty($content)) {
                    $model->addSummary($positionId, sanitize_input($content), $newSummaryTypes[$index] ?? 'professional_summary', $index);
                }
            }
        }

        // Update existing bullets
        if (is_array($bulletIds)) {
            foreach ($bulletIds as $index => $bid) {
                $bid = (int)$bid;
                if (isset($bulletDelete[$index]) && $bulletDelete[$index]) {
                    $model->deleteBullet($bid);
                } elseif (!empty($bulletContents[$index])) {
                    $model->updateBullet($bid, [
                        'content' => sanitize_input($bulletContents[$index]),
                        'category' => $bulletCategories[$index] ?? 'responsibilities',
                        'sort_order' => (int)($bulletOrders[$index] ?? $index)
                    ]);
                }
            }
        }

        // Add new bullets
        if (is_array($newBulletContents)) {
            foreach ($newBulletContents as $index => $content) {
                if (!empty($content)) {
                    $model->addBullet($positionId, sanitize_input($content), $newBulletCategories[$index] ?? 'responsibilities', $index);
                }
            }
        }

        // Update existing skills
        if (is_array($skillIds)) {
            foreach ($skillIds as $index => $sid) {
                $sid = (int)$sid;
                if (isset($skillDelete[$index]) && $skillDelete[$index]) {
                    $model->deleteSkill($sid);
                } elseif (!empty($skillNames[$index])) {
                    $model->updateSkill($sid, [
                        'skill_name' => sanitize_input($skillNames[$index]),
                        'category' => $skillCategories[$index] ?? 'technical',
                        'sort_order' => (int)($skillOrders[$index] ?? $index)
                    ]);
                }
            }
        }

        // Add new skills
        if (is_array($newSkillNames)) {
            foreach ($newSkillNames as $index => $name) {
                if (!empty($name)) {
                    $model->addSkill($positionId, sanitize_input($name), $newSkillCategories[$index] ?? 'technical', $index);
                }
            }
        }
    }
}
