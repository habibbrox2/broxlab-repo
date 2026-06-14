<?php

/**
 * app/Controllers/AdminCvPersonalInfoController.php
 * 
 * Admin CV Personal Info Management — view all records from the
 * structured cv_personal_info table.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$router->get('/admin/cv-personal-info', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $search  = trim((string)($_GET['search'] ?? ''));
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;
    $orderBy = in_array($_GET['sort'] ?? '', ['id', 'full_name', 'email', 'created_at']) ? $_GET['sort'] : 'updated_at';
    $orderDir = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // Stats
    $stats = [];
    $result = $mysqli->query("SELECT COUNT(*) as total FROM cv_personal_info");
    $stats['total'] = (int)($result->fetch_assoc()['total'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(*) as has_email FROM cv_personal_info WHERE email != ''");
    $stats['has_email'] = (int)($result->fetch_assoc()['has_email'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(DISTINCT user_id) as users FROM cv_personal_info");
    $stats['users'] = (int)($result->fetch_assoc()['users'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(DISTINCT cv_id) as cvs FROM cv_personal_info");
    $stats['cvs'] = (int)($result->fetch_assoc()['cvs'] ?? 0);

    // Build query with search
    $where = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $where = "WHERE (pi.full_name LIKE ? OR pi.email LIKE ? OR pi.phone LIKE ? OR pi.nationality LIKE ? OR pi.national_id_no LIKE ? OR pi.passport_no LIKE ?)";
        $likeSearch = "%{$search}%";
        $params = [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch];
        $types = 'ssssss';
    }

    // Count total for pagination
    $countSql = "SELECT COUNT(*) as total FROM cv_personal_info pi {$where}";
    if (!empty($params)) {
        $stmt = $mysqli->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $totalRecords = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    } else {
        $totalRecords = $stats['total'];
    }

    $totalPages = max(1, (int)ceil($totalRecords / $limit));

    // Main query with JOINs
    $sql = "SELECT pi.*, c.title as cv_title, c.is_active as cv_is_active,
                   u.username, u.first_name, u.last_name, u.email as user_email
            FROM cv_personal_info pi
            LEFT JOIN cvs c ON pi.cv_id = c.id
            LEFT JOIN users u ON pi.user_id = u.id
            {$where}
            ORDER BY pi.{$orderBy} {$orderDir}
            LIMIT ? OFFSET ?";

    $records = [];
    $stmt = $mysqli->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo $twig->render('admin/cv-personal-info/list.twig', [
        'records' => $records,
        'stats' => $stats,
        'filters' => [
            'search' => $search,
            'sort' => $orderBy,
            'order' => $orderDir === 'DESC' ? 'desc' : 'asc',
            'limit' => $limit,
            'page' => $page,
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $totalRecords,
            'per_page' => $limit,
        ],
        'page_title' => 'CV Personal Info',
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ]);
});
