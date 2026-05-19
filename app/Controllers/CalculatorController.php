<?php
// app/Controllers/CalculatorController.php
//
// Public calculator suite:
//   GET  /calculators              – directory / landing page
//   GET  /calculators/{slug}       – individual calculator form
//   POST /api/calculator/compute    – worker endpoint (reads form body)

declare(strict_types=1);

/** @var Router     $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli    $mysqli */

require_once BASE_PATH . 'app/Helpers/CalculatorService.php';

// ── helpers ───────────────────────────────────────────────────────────────

/** Send a JSON error response and stop. */
function calculatorJsonError(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Returns the first value of a named input (single scalar), or null if absent.
 */
function inputScalar(string $name, ?float &$out = null): bool
{
    if (!isset($_POST[$name]) && !isset($_GET[$name])) {
        return false;
    }
    $val = ($_POST[$name] ?? $_GET[$name]);
    if (!is_numeric($val)) {
        return false;
    }
    $out = (float) $val;
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
// ROUTES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Calculator Directory Landing Page
 * GET /calculators
 *
 * Shows every available calculator grouped by category with descriptions
 * and a "Start Calculating" button, mirroring the category-gallery style
 * used on Calculator.net.
 */
$router->get('/calculators', ['name' => 'calculators.index'], function () use ($twig) {
    $statisticsModel = new StatisticsModel($mysqli ?? null);
    $stats = method_exists($statisticsModel ?? new class {}, 'getStatistics')
        ? $statisticsModel->getStatistics()
        : [];

    $groups = CalculatorService::getGroupedDefinitions();
    $total  = array_sum(array_map(static fn($g) => count($g['items']), $groups));

    echo $twig->render('public/calculators.twig', [
        'title'             => 'Online Calculators',
        'header_title'      => 'Online Calculators',
        'description'       => 'Free online calculators for finance, math, health and more.',
        'groups'            => $groups,
        'total_calculators' => $total,
        'stats'             => $stats,
    ]);
});

/**
 * Individual Calculator Page
 * GET /calculators/{slug}
 *
 * Renders the form-based calculator defined by $slug.  Uses the generic
 * template (public/calculator/generic.twig) so each type only needs a
 * different `fields` array — no separate Twig file per calculator.
 */
$router->get('/calculators/{slug}', ['name' => 'calculators.show'], function (string $slug) use ($twig) {
    $all = CalculatorService::getAllDefinitions();

    if (!array_key_exists($slug, $all)) {
        http_response_code(404);
        echo $twig->render('public/error.twig', [
            'title'   => 'Not Found',
            'message' => "Calculator '<strong>" . htmlspecialchars($slug) . "</strong>' was not found.",
        ]);
        return;
    }

    $def   = $all[$slug];
    $title = $def['label'] . ' – BroxLab Calculators';

    // Each calculator gets a field-descriptor array passed to the template
    // so the same Twig file can render any calculator type.
    $fields = match ($slug) {
        'simple-interest' => [
            ['name' => 'principal', 'label' => 'Principal',     'type' => 'number', 'step' => '0.01', 'min' => '0',   'placeholder' => 'e.g. 10000',    'icon' => 'bi-cash-stack'],
            ['name' => 'rate',     'label' => 'Annual Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0',   'placeholder' => 'e.g. 5',          'icon' => 'bi-percent'],
            ['name' => 'years',    'label' => 'Time (Years)',     'type' => 'number', 'step' => '0.1',  'min' => '0',   'placeholder' => 'e.g. 3',          'icon' => 'bi-calendar'],
        ],
        'compound-interest' => [
            ['name' => 'principal', 'label' => 'Principal',        'type' => 'number', 'step' => '0.01', 'min' => '0',   'placeholder' => 'e.g. 10000', 'icon' => 'bi-piggy-bank'],
            ['name' => 'rate',     'label' => 'Annual Rate (%)',   'type' => 'number', 'step' => '0.01', 'min' => '0',   'placeholder' => 'e.g. 7',       'icon' => 'bi-percent'],
            ['name' => 'frequency','label' => 'Compound / Year',   'type' => 'select', 'options' => ['1'=>'Yearly','12'=>'Monthly','365'=>'Daily'], 'default' => '12', 'icon' => 'bi-arrow-repeat'],
            ['name' => 'years',    'label' => 'Time (Years)',       'type' => 'number', 'step' => '0.1',  'min' => '0',   'placeholder' => 'e.g. 5',       'icon' => 'bi-calendar'],
        ],
        'loan-amortization' => [
            ['name' => 'loan_amount','label' => 'Loan Amount', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 25000', 'icon' => 'bi-bank2'],
            ['name' => 'rate',      'label' => 'Annual Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 6.5', 'icon' => 'bi-percent'],
            ['name' => 'months',    'label' => 'Loan Term (Months)', 'type' => 'number', 'step' => '1', 'min' => '1', 'placeholder' => 'e.g. 60', 'icon' => 'bi-calendar-month'],
        ],
        'mortgage' => [
            ['name' => 'home_price',    'label' => 'Home Price',    'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 300000', 'icon' => 'bi-house-door'],
            ['name' => 'down_payment',  'label' => 'Down Payment',  'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 60000',  'icon' => 'bi-cash-coin'],
            ['name' => 'interest_rate', 'label' => 'Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 6.5',  'icon' => 'bi-percent'],
            ['name' => 'loan_years',   'label' => 'Loan Term (Years)', 'type' => 'number', 'step' => '1',  'min' => '1', 'placeholder' => 'e.g. 30',     'icon' => 'bi-calendar-check'],
            ['name' => 'property_tax', 'label' => 'Annual Property Tax', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 2400',  'icon' => 'bi-receipt'],
            ['name' => 'home_insurance','label' => 'Annual Home Insurance', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 1200', 'icon' => 'bi-shield-check'],
            ['name' => 'hoa_monthly',  'label' => 'Monthly HOA Fee', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 0',      'icon' => 'bi-building'],
        ],
        'percentage' => [
            ['name' => 'value',    'label' => 'Value',       'type' => 'number', 'step' => 'any',  'placeholder' => 'e.g. 200', 'icon' => 'bi-123'],
            ['name' => 'percent',  'label' => 'Percentage (%)', 'type' => 'number', 'step' => 'any',  'placeholder' => 'e.g. 15', 'icon' => 'bi-percent'],
        ],
        'percentage-change' => [
            ['name' => 'from', 'label' => 'From (Original Value)', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 50',  'icon' => 'bi-arrow-left-short'],
            ['name' => 'to',   'label' => 'To (New Value)',         'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 75',  'icon' => 'bi-arrow-right-short'],
        ],
        'gpa' => [
            ['name' => 'courses', 'label' => 'Courses (JSON)', 'type' => 'textarea',
             'placeholder' => '[{"credit_hours":3,"grade_point":4.0},{"credit_hours":3,"grade_point":3.0}]',
             'icon' => 'bi-clipboard-data',
             'help'  => 'Enter a JSON array of objects with <code>credit_hours</code> and <code>grade_point</code> (0-4 scale).'],
        ],
        'bmi' => [
            ['name' => 'height', 'label' => 'Height (cm)', 'type' => 'number', 'step' => '0.1', 'min' => '1',  'placeholder' => 'e.g. 175', 'icon' => 'bi-ruler-vertical'],
            ['name' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number', 'step' => '0.1', 'min' => '1',  'placeholder' => 'e.g. 70',  'icon' => 'bi-basket'],
        ],
        default => [],
    };

    echo $twig->render('public/calculator/generic.twig', [
        'title'          => $title,
        'header_title'   => $def['label'],
        'calc_type'      => $slug,
        'calc_category'  => $def['category'],
        'calc_icon'      => $def['icon'],
        'calc_label'     => $def['label'],
        'fields'         => $fields,
        'has_results'    => false,
        'result'         => null,
    ]);
});

/**
 * Worker endpoint – receives validated POST body from JS, calls the
 * appropriate formula, and returns a typed JSON result.
 *
 * POST /api/calculator/compute/{type}
 */
$router->post('/api/calculator/compute/{type}', ['name' => 'calculators.compute'], function (string $type) {
    $allowed = CalculatorService::getSupportedTypes();
    if (!in_array($type, $allowed, true)) {
        calculatorJsonError(400, 'Unsupported calculator type: ' . $type);
    }

    // Read raw body to support both JSON and application/x-www-form-urlencoded
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        $result = match ($type) {
            'simple-interest'       => CalculatorService::simpleInterest(
                (float)($input['principal'] ?? 0),
                (float)($input['rate']     ?? 0),
                (float)($input['years']    ?? 0),
            ),
            'compound-interest'     => CalculatorService::compoundInterest(
                (float)($input['principal']  ?? 0),
                (float)($input['rate']       ?? 0),
                intval(  $input['frequency'] ?? 12),
                (float)($input['years']      ?? 0),
            ),
            'loan-amortization'     => CalculatorService::loanAmortization(
                (float)($input['loan_amount'] ?? 0),
                (float)($input['rate']        ?? 0),
                intval(  $input['months']     ?? 0),
            ),
            'mortgage'              => CalculatorService::mortgage(
                (float)($input['home_price']     ?? 0),
                (float)($input['down_payment']   ?? 0),
                (float)($input['interest_rate']  ?? 0),
                intval(  $input['loan_years']    ?? 0),
                (float)($input['property_tax']   ?? 0),
                (float)($input['home_insurance'] ?? 0),
                (float)($input['hoa_monthly']    ?? 0),
            ),
            'percentage'            => CalculatorService::percentage(
                (float)($input['value']   ?? 0),
                (float)($input['percent'] ?? 0),
            ),
            'percentage-change'     => CalculatorService::percentageChange(
                (float)($input['from'] ?? 0),
                (float)($input['to']   ?? 0),
            ),
            'gpa'                   => CalculatorService::gpa(
                is_array($input['courses'] ?? null) ? $input['courses'] : [],
            ),
            'bmi'                   => CalculatorService::bmi(
                (float)($input['height'] ?? 0),
                (float)($input['weight'] ?? 0),
            ),
            default => throw new LogicException('No handler for type ' . $type),
        };

        http_response_code(200);
        echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);

    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        logError('Calculator error [' . $type . ']: ' . $e->getMessage(), 'CALCULATOR', [
            'input' => $input,
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unexpected error. Please try again.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
});
