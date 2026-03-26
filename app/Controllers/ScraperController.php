<?php

declare(strict_types=1);

/**
 * ScraperController.php
 * Controller for managing Teletalk government jobs scraper
 */

use App\Modules\Scraper\TeletalkScraperService;
use App\Modules\Scraper\HttpClientService;
use App\Models\TeletalkJobModel;
use App\Helpers\JsonResponse;
use App\Helpers\ErrorLogging;

// ================== DASHBOARD ==================

/**
 * Teletalk Scraper Dashboard
 * GET /admin/scraper/teletalk
 */
$router->get('/admin/scraper/teletalk', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        
        // Get statistics
        $totalJobs = $model->getTotalCount();
        $recentJobs = $model->getRecentJobs(10);
        $organizations = $model->getOrganizations();
        
        // Get last scrape info (from a settings table or file)
        $lastScrape = null;
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/teletalk_last_scrape.json';
        if (file_exists($lastScrapeFile)) {
            $lastScrape = json_decode(file_get_contents($lastScrapeFile), true);
        }

        return $twig->render('admin/scraper/teletalk.twig', [
            'total_jobs' => $totalJobs,
            'recent_jobs' => $recentJobs,
            'organizations' => $organizations,
            'last_scrape' => $lastScrape,
            'page_title' => 'Teletalk Jobs Scraper',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Dashboard error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load scraper dashboard',
            'page_title' => 'Error',
        ]);
    }
});

// ================== SCRAPE ACTIONS ==================

/**
 * Trigger manual scrape
 * POST /admin/scraper/teletalk/scrape
 */
$router->post('/admin/scraper/teletalk/scrape', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        // Validate CSRF token
        if (!validateCsrfToken()) {
            return JsonResponse::error('Invalid CSRF token', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $maxPages = (int)($input['max_pages'] ?? 3);
        $maxPages = min(max($maxPages, 1), 10); // Limit to 1-10 pages

        // Initialize services
        $httpClient = new HttpClientService();
        $scraper = new TeletalkScraperService($httpClient);
        $model = new TeletalkJobModel($mysqli);

        // Scrape with progress tracking
        $results = $scraper->scrapeAllPages($maxPages, function ($page, $totalPages, $success, $data) use ($model, $scraper) {
            if ($success) {
                // Save jobs to database
                foreach ($data as $job) {
                    $saveResult = $model->saveJob($job);
                    if ($saveResult['success']) {
                        $scraper->updateStats('new_jobs', 1);
                    } else {
                        if (strpos($saveResult['error'], 'already exists') !== false) {
                            $scraper->updateStats('duplicates', 1);
                        } else {
                            $scraper->updateStats('errors', 1);
                        }
                    }
                }
            }
        });

        // Save last scrape info
        $lastScrapeFile = __DIR__ . '/../Modules/Scraper/logs/teletalk_last_scrape.json';
        $lastScrapeDir = dirname($lastScrapeFile);
        if (!is_dir($lastScrapeDir)) {
            mkdir($lastScrapeDir, 0755, true);
        }
        file_put_contents($lastScrapeFile, json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'pages_scraped' => $maxPages,
            'stats' => $scraper->getStats(),
        ]));

        return JsonResponse::success([
            'message' => 'Scraping completed successfully',
            'stats' => $scraper->getStats(),
            'jobs_count' => count($results['jobs']),
        ]);

    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Scrape error: " . $e->getMessage());
        return JsonResponse::error('Failed to scrape jobs: ' . $e->getMessage(), 500);
    }
});

// ================== JOB LISTING ==================

/**
 * List scraped jobs
 * GET /admin/scraper/teletalk/jobs
 */
$router->get('/admin/scraper/teletalk/jobs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        $organization = $_GET['organization'] ?? '';
        
        if ($search) {
            $jobs = $model->searchJobs($search, $limit, $offset);
            $total = count($model->searchJobs($search, 1000, 0)); // Approximate total
        } elseif ($organization) {
            $jobs = $model->getJobsByOrganization($organization, $limit, $offset);
            $total = $model->getCountByOrganization($organization);
        } else {
            $jobs = $model->getRecentJobs($limit, $offset);
            $total = $model->getTotalCount();
        }
        
        $organizations = $model->getOrganizations();
        
        return $twig->render('admin/scraper/teletalk-jobs.twig', [
            'jobs' => $jobs,
            'organizations' => $organizations,
            'current_page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
            'search' => $search,
            'selected_organization' => $organization,
            'page_title' => 'Teletalk Jobs',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Jobs list error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load jobs',
            'page_title' => 'Error',
        ]);
    }
});

/**
 * View job details
 * GET /admin/scraper/teletalk/jobs/{id}
 */
$router->get('/admin/scraper/teletalk/jobs/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        $job = $model->getJobById((int)$id);
        
        if (!$job) {
            return $twig->render('error.twig', [
                'error' => 'Job not found',
                'page_title' => 'Error',
            ]);
        }
        
        return $twig->render('admin/scraper/teletalk-job-detail.twig', [
            'job' => $job,
            'page_title' => 'Job Details',
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Job detail error: " . $e->getMessage());
        return $twig->render('error.twig', [
            'error' => 'Failed to load job details',
            'page_title' => 'Error',
        ]);
    }
});

// ================== JOB MANAGEMENT ==================

/**
 * Delete a job
 * DELETE /admin/scraper/teletalk/jobs/{id}
 */
$router->delete('/admin/scraper/teletalk/jobs/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        // Validate CSRF token
        if (!validateCsrfToken()) {
            return JsonResponse::error('Invalid CSRF token', 403);
        }

        $model = new TeletalkJobModel($mysqli);
        $result = $model->deleteJob((int)$id);
        
        if ($result['success']) {
            return JsonResponse::success(['message' => 'Job deleted successfully']);
        } else {
            return JsonResponse::error($result['error'] ?? 'Failed to delete job', 500);
        }
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Delete job error: " . $e->getMessage());
        return JsonResponse::error('Failed to delete job: ' . $e->getMessage(), 500);
    }
});

// ================== EXPORT ==================

/**
 * Export jobs to JSON
 * GET /admin/scraper/teletalk/export/json
 */
$router->get('/admin/scraper/teletalk/export/json', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        $jobs = $model->getRecentJobs(1000, 0); // Get up to 1000 jobs
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="teletalk-jobs-' . date('Y-m-d') . '.json"');
        
        echo json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Export JSON error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to export jobs']);
        exit;
    }
});

/**
 * Export jobs to CSV
 * GET /admin/scraper/teletalk/export/csv
 */
$router->get('/admin/scraper/teletalk/export/csv', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        $jobs = $model->getRecentJobs(1000, 0); // Get up to 1000 jobs
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="teletalk-jobs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, ['ID', 'Job ID', 'Title', 'Organization', 'Openings', 'URL', 'Image URL', 'Scraped At']);
        
        // CSV rows
        foreach ($jobs as $job) {
            fputcsv($output, [
                $job['id'],
                $job['job_id'],
                $job['title'],
                $job['organization'],
                $job['openings'],
                $job['url'],
                $job['image_url'],
                $job['scraped_at'],
            ]);
        }
        
        fclose($output);
        exit;
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: Export CSV error: " . $e->getMessage());
        header('Content-Type: text/plain');
        echo 'Failed to export jobs';
        exit;
    }
});

// ================== API ENDPOINTS ==================

/**
 * API: Get jobs (JSON)
 * GET /api/teletalk/jobs
 */
$router->get('/api/teletalk/jobs', function () use ($mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $search = $_GET['search'] ?? '';
        
        if ($search) {
            $jobs = $model->searchJobs($search, $limit, $offset);
        } else {
            $jobs = $model->getRecentJobs($limit, $offset);
        }
        
        return JsonResponse::success([
            'jobs' => $jobs,
            'page' => $page,
            'limit' => $limit,
        ]);
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: API jobs error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch jobs', 500);
    }
});

/**
 * API: Get job by ID
 * GET /api/teletalk/jobs/{id}
 */
$router->get('/api/teletalk/jobs/(\d+)', function ($id) use ($mysqli) {
    try {
        $model = new TeletalkJobModel($mysqli);
        $job = $model->getJobById((int)$id);
        
        if (!$job) {
            return JsonResponse::error('Job not found', 404);
        }
        
        return JsonResponse::success(['job' => $job]);
    } catch (\Exception $e) {
        ErrorLogging::logError("ScraperController: API job detail error: " . $e->getMessage());
        return JsonResponse::error('Failed to fetch job', 500);
    }
});
