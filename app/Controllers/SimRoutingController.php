<?php
/**
 * SimRoutingController.php
 * Admin routes for managing SIM Routing rules.
 */
global $router, $mysqli, $twig;

if (!isset($router, $mysqli, $twig)) {
    return;
}

$router->get('/admin/sim-routing', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli, $twig) {
    $service = new \App\Modules\SmsGateway\SimRoutingService($mysqli);
    $routes  = $service->getAllRoutes();

    // Fetch online devices for the device picker in the form
    $devices = [];
    $res = $mysqli->query("SELECT id, device_name FROM devices ORDER BY device_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $devices[] = $row;
        }
    }

    echo $twig->render('admin/sim_routing.twig', [
        'title'        => 'SIM Routing',
        'current_page' => 'sim-routing',
        'routes'       => $routes,
        'devices'      => $devices,
        'csrf_token'   => generateCsrfToken(),
    ]);
});

$router->post('/admin/sim-routing/save', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token.', 'danger');
        redirect('/admin/sim-routing');
    }

    $service = new \App\Modules\SmsGateway\SimRoutingService($mysqli);
    $ok = $service->saveRoute([
        'id'            => (int)($_POST['id'] ?? 0),
        'label'         => $_POST['label'] ?? '',
        'match_type'    => $_POST['match_type'] ?? 'any',
        'match_value'   => $_POST['match_value'] ?? '',
        'action'        => $_POST['action'] ?? 'forward_telegram',
        'device_id'     => $_POST['device_id'] ?? null,
        'sim_slot'      => $_POST['sim_slot'] ?? 1,
        'reply_message' => $_POST['reply_message'] ?? '',
        'enabled'       => isset($_POST['enabled']) ? 1 : 0,
    ]);

    showMessage($ok ? 'Route saved successfully.' : 'Failed to save route.', $ok ? 'success' : 'danger');
    redirect('/admin/sim-routing');
});

$router->post('/admin/sim-routing/delete', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token.', 'danger');
        redirect('/admin/sim-routing');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        showMessage('Invalid route ID.', 'danger');
        redirect('/admin/sim-routing');
    }

    $service = new \App\Modules\SmsGateway\SimRoutingService($mysqli);
    $ok = $service->deleteRoute($id);

    showMessage($ok ? 'Route deleted.' : 'Failed to delete route.', $ok ? 'success' : 'danger');
    redirect('/admin/sim-routing');
});
