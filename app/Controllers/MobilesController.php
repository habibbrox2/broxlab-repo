<?php
// controllers/MobilesController.php

use App\Models\MobileModel;

global $mysqli;

$mobileModel  = new MobileModel($mysqli);
$commentModel = new CommentModel($mysqli);
$taxonomyModel = new ContentModel($mysqli);

if (!function_exists('normalizeMobileStatus')) {
    function normalizeMobileStatus($rawStatus, $isOfficial = null) {
        $status = strtolower(trim((string)$rawStatus));

        $map = [
            'official' => 'official',
            'unofficial' => 'unofficial',
            'both' => 'both',
            'active' => 'official',
            'inactive' => 'unofficial',
            'published' => 'official',
            'draft' => 'unofficial',
            '1' => 'official',
            '0' => 'unofficial',
            'true' => 'official',
            'false' => 'unofficial',
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        if ($status === '' && $isOfficial !== null) {
            return (int)$isOfficial === 1 ? 'official' : 'unofficial';
        }

        return null;
    }
}

/**
 * -------------------------
 * ADMIN ROUTES
 * -------------------------
 */
$router->group('/admin/mobiles', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $mobileModel, $taxonomyModel, $commentModel, $mysqli) {


    // All mobiles (admin) - paginated full list
    $router->get('/all', function () use ($twig, $mobileModel) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
        $search = sanitize_input($_GET['search'] ?? '');
        $sort = $_GET['sort'] ?? 'brand_name';
        $order = $_GET['order'] ?? 'ASC';

        $total = $mobileModel->getMobilesCount($search, []);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $mobiles = $mobileModel->getMobiles($page, $perPage, $search, $sort, $order, []);

        echo $twig->render('admin/mobiles/all.twig', [
            'mobiles' => $mobiles,
            'title' => 'All Mobiles',
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total' => $total
            ],
            'search' => $search,
            'sort' => $sort,
            'order' => $order
        ]);
    });

    // Show insert form
    $router->get('/insert', function () use ($twig, $mobileModel, $taxonomyModel) {
        $specifications = $mobileModel->fetchAllSpecKeys();
        $tags       = $taxonomyModel->getAllTags();

        // start with session values if any
        $old = null;
        if (!empty($_SESSION['mobile_old'])) {
            $old = $_SESSION['mobile_old'];
            unset($_SESSION['mobile_old']);
        }

        // merge GET query params, overwriting any session values
        if (!empty($_GET)) {
            $params = $_GET;
            // keep only mobile fields we care about
            $allowed = ['brand_name','model_name','official_price','unofficial_price','status','release_date','is_official','tags'];
            $cleaned = [];
            foreach ($allowed as $k) {
                if (isset($params[$k])) {
                    $cleaned[$k] = $params[$k];
                }
            }
            if (isset($cleaned['status'])) {
                $normalizedStatus = normalizeMobileStatus($cleaned['status'], isset($cleaned['is_official']) ? (int)$cleaned['is_official'] : null);
                if ($normalizedStatus !== null) {
                    $cleaned['status'] = $normalizedStatus;
                } else {
                    unset($cleaned['status']);
                }
            }
            if ($old) {
                $old = array_merge($old, $cleaned);
            } else {
                $old = $cleaned;
            }
        }

        echo $twig->render('admin/mobiles/insert_mobile.twig', [
            'title'          => 'Insert Mobile',
            'header_title'   => 'Insert New Mobile',
            'specifications' => $specifications,
            'tags'           => $tags,
            'old'            => $old
        ]);
    });

    // Insert mobile
    $router->post('/insert', function () use ($mysqli, $mobileModel, $taxonomyModel) {
        $brand_name       = trim($_POST['brand_name'] ?? '');
        $model_name       = trim($_POST['model_name'] ?? '');
        $official_price   = (float) ($_POST['official_price'] ?? 0);
        $unofficial_price = (float) ($_POST['unofficial_price'] ?? 0);
        $statusRaw        = $_POST['status'] ?? '';
        $release_date     = $_POST['release_date'] ?? '';
        $is_official      = isset($_POST['is_official']) ? 1 : 0;
        $specifications   = $_POST['specifications'] ?? [];

        $tag_ids      = $_POST['tags'] ?? [];

        $status = normalizeMobileStatus($statusRaw, $is_official);

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (empty($brand_name) || empty($model_name) || empty($release_date)) {
            // preserve input for rerender
            $_SESSION['mobile_old'] = $_POST;
            logActivity("Mobile Creation Failed", "mobile", 0, ['reason' => 'Missing required fields'], 'failure');
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }
            showMessage("Missing required fields", "error");
            header("Location: /admin/mobiles/insert");
            exit;
        }

        if ($status === null) {
            $_SESSION['mobile_old'] = $_POST;
            logActivity("Mobile Creation Failed", "mobile", 0, ['reason' => 'Invalid status', 'status' => $statusRaw], 'failure');
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid status value']);
                exit;
            }
            showMessage("Invalid status value", "error");
            header("Location: /admin/mobiles/insert");
            exit;
        }

        // Insert main record
        $mobile_id = $mobileModel->insertMobile($brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official);

        if ($mobile_id) {
            unset($_SESSION['mobile_old']);
        }

        if (!$mobile_id) {
            // retain the POST values so the form can be prefilled
            $_SESSION['mobile_old'] = $_POST;
            logActivity("Mobile Creation Failed", "mobile", 0, ['brand' => $brand_name, 'model' => $model_name], 'failure');
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create mobile']);
                exit;
            }
            showMessage("Failed to create mobile", "error");
            header("Location: /admin/mobiles/insert");
            exit;
        }

        // Insert specifications
        if (!empty($specifications['key'])) {
            $mobileModel->insertSpecifications($mobile_id, $specifications['key'], $specifications['value']);
        }

        // Handle image uploads using UploadService
        if (!empty($_FILES['images']['name'][0])) {
            $uploadService = new UploadService($mysqli);
            $uploadResults = [];
            foreach ($_FILES['images']['name'] as $key => $fileName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['images']['name'][$key],
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $_FILES['images']['tmp_name'][$key],
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ];
                    $result = $uploadService->upload($file, 'mobile_image', [
                        'mobile_id' => $mobile_id,
                        'brand' => $brand_name,
                        'model' => $model_name
                    ]);
                    if ($result['success']) {
                        $uploadResults[] = $result['url'];
                    }
                }
            }
            if (!empty($uploadResults)) {
                $mobileModel->insertImages($mobile_id, $uploadResults);
            } else {
                showMessage('Image upload failed', 'warning');
            }
        }

        // âœ… Attach tags
        if (!empty($tag_ids)) {
            $taxonomyModel->attachTagsToContent('mobile', $mobile_id, $tag_ids);
        }

        logActivity("Mobile Created", "mobile", $mobile_id, ['brand' => $brand_name, 'model' => $model_name, 'status' => $status], 'success');
        showMessage("Mobile inserted successfully", "success");
        header('Location: /admin/mobiles/list');
        exit;
    });

    // Show edit form
    $router->get('/update/{id}', function ($id) use ($twig, $mobileModel, $taxonomyModel) {
        if (!$id) {
            echo $twig->render('error.twig', ['message' => 'Mobile ID not specified.']);
            return;
        }

        $mobile = $mobileModel->fetchMobileById($id);
        if (!$mobile) {
            echo $twig->render('error.twig', ['message' => 'Mobile not found.']);
            return;
        }

        // allow query params to override mobile fields for prefilling
        if (!empty($_GET)) {
            $allowed = ['brand_name','model_name','official_price','unofficial_price','status','release_date','is_official','tags'];
            foreach ($allowed as $k) {
                if (isset($_GET[$k])) {
                    $mobile[$k] = $_GET[$k];
                }
            }
            if (isset($mobile['status'])) {
                $normalizedStatus = normalizeMobileStatus($mobile['status'], isset($mobile['is_official']) ? (int)$mobile['is_official'] : null);
                if ($normalizedStatus !== null) {
                    $mobile['status'] = $normalizedStatus;
                }
            }
        }

        $mobileSpecs   = $mobileModel->fetchSpecsByMobileId($id);
        $allSpecs      = $mobileModel->fetchAllSpecKeys();
        $images        = $mobileModel->fetchImagesByMobileId($id);

        $tags       = $taxonomyModel->getAllTags();
        $selected_tags       = $taxonomyModel->getTagsForContent('mobile', $id);

        echo $twig->render('admin/mobiles/edit_mobile.twig', [
            'mobile'            => $mobile,
            'mobile_specs'      => $mobileSpecs,
            'specifications'    => $allSpecs,
            'mobile_images'     => $images,
            'tags'              => $tags,
            'mobile_tags'       => $selected_tags
        ]);
    });

    // Update mobile
    $router->post('/update/{id}', function ($id) use ($mysqli, $mobileModel, $taxonomyModel) {
        $brand_name       = trim($_POST['brand_name'] ?? '');
        $model_name       = trim($_POST['model_name'] ?? '');
        $official_price   = (float) ($_POST['official_price'] ?? 0);
        $unofficial_price = (float) ($_POST['unofficial_price'] ?? 0);
        $statusRaw        = $_POST['status'] ?? '';
        $release_date     = $_POST['release_date'] ?? '';
        $is_official      = isset($_POST['is_official']) ? 1 : 0;
        $specifications   = $_POST['specifications'] ?? [];

        $tag_ids      = $_POST['tags'] ?? [];

        if (!$id) {
            logActivity("Mobile Update Failed", "mobile", 0, ['reason' => 'Missing ID'], 'failure');
            showMessage("Missing ID", "error");
            header("Location: /admin/mobiles/list");
            exit;
        }

        $status = normalizeMobileStatus($statusRaw, $is_official);
        if ($status === null) {
            logActivity("Mobile Update Failed", "mobile", (int)$id, ['reason' => 'Invalid status', 'status' => $statusRaw], 'failure');
            showMessage("Invalid status value", "error");
            header("Location: /admin/mobiles/update/$id");
            exit;
        }

        // Update main record
        $result = $mobileModel->updateMobile($id, $brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official);

        if (!$result) {
            logActivity("Mobile Update Failed", "mobile", $id, ['brand' => $brand_name, 'model' => $model_name], 'failure');
            showMessage("Failed to update mobile", "error");
            header("Location: /admin/mobiles/update?id=$id");
            exit;
        }

        // Update specifications
        if (!empty($specifications['key'])) {
            $mobileModel->updateSpecifications($id, $specifications['key'], $specifications['value']);
        }

        // Handle deleted images
        $deleted_image_ids = explode(',', $_POST['deleted_images'] ?? '');
        $deleted_image_ids = array_filter(array_map('intval', $deleted_image_ids));
        if (!empty($deleted_image_ids)) {
            $mobileModel->deleteImages($deleted_image_ids);
        }

        // Update images using UploadService
        if (!empty($_FILES['images']['name'][0])) {
            $uploadService = new UploadService($mysqli);
            $uploadResults = [];
            foreach ($_FILES['images']['name'] as $key => $fileName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['images']['name'][$key],
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $_FILES['images']['tmp_name'][$key],
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ];
                    $result = $uploadService->upload($file, 'mobile_image', [
                        'mobile_id' => $id,
                        'brand' => $brand_name,
                        'model' => $model_name
                    ]);
                    if ($result['success']) {
                        $uploadResults[] = $result['url'];
                    }
                }
            }
            if (!empty($uploadResults)) {
                $mobileModel->insertImages($id, $uploadResults);
            } else {
                showMessage('Image upload failed', 'warning');
            }
        }

        // âœ… Update tags
        $taxonomyModel->updateContentTags('mobile', $id, $tag_ids);

        logActivity("Mobile Updated", "mobile", $id, ['brand' => $brand_name, 'model' => $model_name, 'status' => $status], 'success');
        showMessage("Mobile updated successfully", "success");
        header("Location: /admin/mobiles/update/$id");
        exit;
    });

    // View mobile (admin)
    $router->get('/view/{id}', function ($id) use ($twig, $mobileModel, $taxonomyModel, $commentModel) {
        $mobile = $mobileModel->fetchMobileById($id);
        if (!$mobile) {
            echo $twig->render('error.twig', ['message' => 'Mobile not found.']);
            return;
        }

        $specs     = $mobileModel->fetchSpecsByMobileId($id);
        $images    = $mobileModel->fetchImagesByMobileId($id);
        $comments  = $commentModel->getComments('mobiles', $id);

        $tags       = $taxonomyModel->getTagsForContent('mobile', $id);

        echo $twig->render('admin/mobiles/mobile_details.twig', [
            'mobile'         => $mobile,
            'specifications' => $specs,
            'images'         => $images,
            'comments'       => $comments,
            'tags'           => $tags
        ]);
    });

    // Show delete confirmation
    $router->get('/delete', function () use ($twig, $mobileModel) {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo $twig->render('error.twig', ['message' => 'Mobile ID not specified.']);
            return;
        }

        $mobile = $mobileModel->fetchMobileById($id);
        if (!$mobile) {
            echo $twig->render('error.twig', ['message' => 'Mobile not found.']);
            return;
        }

        echo $twig->render('admin/mobiles/delete_mobile.twig', [
            'mobile' => $mobile
        ]);
    });

    // Delete mobile
    $router->post('/delete', function () use ($mobileModel, $taxonomyModel) {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            logActivity("Mobile Deletion Failed", "mobile", 0, ['reason' => 'Missing ID'], 'failure');
            showMessage("Missing ID", "error");
            header("Location: /admin/mobiles/list");
            exit;
        }

        $mobile = $mobileModel->fetchMobileById($id);
        if (!$mobile) {
            logActivity("Mobile Deletion Failed", "mobile", 0, ['reason' => 'Mobile not found'], 'failure');
            showMessage("Mobile not found", "error");
            header("Location: /admin/mobiles/list");
            exit;
        }

        // Delete associated images
        $images = $mobileModel->fetchImagesByMobileId($id);
        if (!empty($images)) {
            $imageIds = array_map(function ($img) {
                return $img['id'];
            }, $images);
            $mobileModel->deleteImages($imageIds);
        }

        // Delete associated tags
        $taxonomyModel->updateContentTags('mobile', $id, []);

        // Delete mobile record
        $result = $mobileModel->deleteMobile($id);

        if ($result) {
            logActivity("Mobile Deleted", "mobile", $id, ['brand' => $mobile['brand_name'], 'model' => $mobile['model_name']], 'success');
            showMessage("Mobile deleted successfully", "success");
        } else {
            logActivity("Mobile Deletion Failed", "mobile", $id, ['brand' => $mobile['brand_name'], 'model' => $mobile['model_name']], 'failure');
            showMessage("Failed to delete mobile", "error");
        }

        header("Location: /admin/mobiles/list");
        exit;
    });
});

/**
 * -------------------------
 * PUBLIC ROUTES
 * -------------------------
 */
$router->group('/mobiles', [], function ($router) use ($twig, $mobileModel, $taxonomyModel, $commentModel) {



    // List mobiles (public) with pagination
    $router->get('', function () use ($twig, $mobileModel) {
        $search = sanitize_input($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(6, min(48, (int)($_GET['per_page'] ?? 12)));
        $offset = ($page - 1) * $perPage;
        $sort = $_GET['sort'] ?? 'id';
        $order = $_GET['order'] ?? 'DESC';

        $total = $mobileModel->countMobiles();
        $totalPages = (int)ceil($total / $perPage);

        $mobiles = $mobileModel->fetchMobiles($perPage, $offset, $sort, $order);

        echo $twig->render('mobiles/list.twig', [
            'mobiles' => $mobiles,
            'search' => $search,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_mobiles' => $total,
            'sort' => $sort,
            'order' => $order,
            'available_per_page' => [6, 12, 18, 24, 36, 48]
        ]);
    });

    // View single mobile (à¦…à¦ªà§à¦Ÿà¦¿à¦®à¦¾à¦‡à¦œà¦¡ - à¦à¦• à¦«à¦¾à¦‚à¦¶à¦¨à§‡ à¦¸à¦¬ à¦¡à§‡à¦Ÿà¦¾)
    $router->get('/view/{id}', function ($id) use ($twig, $mobileModel, $taxonomyModel, $commentModel) {
        // getMobileComplete() à¦à¦•à¦Ÿà¦¿ à¦«à¦¾à¦‚à¦¶à¦¨à§‡ à¦¸à¦¬ à¦¡à§‡à¦Ÿà¦¾ à¦¨à¦¿à¦¯à¦¼à§‡ à¦†à¦¸à§‡ (à¦¬à¦¾à¦¦à§‡ à¦•à¦®à§‡à¦¨à§à¦Ÿ à¦à¦¬à¦‚ à¦°à¦¿à¦²à§‡à¦Ÿà§‡à¦¡)
        $mobile = $mobileModel->getMobileComplete($id);

        if (!$mobile) {
            echo $twig->render('error.twig', ['message' => 'Mobile not found.']);
            return;
        }

        // à¦¶à§à¦§à§à¦®à¦¾à¦¤à§à¦° à¦•à¦®à§‡à¦¨à§à¦Ÿ à¦à¦¬à¦‚ à¦°à¦¿à¦²à§‡à¦Ÿà§‡à¦¡ à¦®à§‹à¦¬à¦¾à¦‡à¦² à¦†à¦²à¦¾à¦¦à¦¾ à¦•à§‹à¦¯à¦¼à§‡à¦°à¦¿à¦¤à§‡ (à¦à¦—à§à¦²à§‹ à¦¸à¦¬à¦¸à¦®à¦¯à¦¼ à¦ªà§à¦°à¦¯à¦¼à§‹à¦œà¦¨ à¦¹à¦¯à¦¼ à¦¨à¦¾)
        $comments  = $commentModel->getComments('mobiles', $id);
        $related_mobiles = $mobileModel->getRelatedMobiles($id, 3);

        echo $twig->render('mobiles/view.twig', [
            'mobile'         => $mobile,
            'specifications' => $mobile['specifications'] ?? [],
            'images'         => $mobile['images'] ?? [],
            'comments'       => $comments,
            'tags'           => $mobile['tags'] ?? [],
            'related_mobiles' => $related_mobiles
        ]);
    });
});
