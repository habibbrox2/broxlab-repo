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

require_once BASE_PATH . 'app/Services/CalculatorService.php';

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
$router->get('/calculator', function () {
    redirect('/calculators', 301);
});

$router->get('/calculators', ['name' => 'calculators.index'], function () use ($twig, $mysqli) {
    $stats = [];

    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $statisticsModel = new StatisticsModel($mysqli);
        $stats = $statisticsModel->getStatistics();
    }

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
    $groups = CalculatorService::getGroupedDefinitions();

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
            ['name' => 'frequency', 'label' => 'Compound / Year',   'type' => 'select', 'options' => ['1' => 'Yearly', '12' => 'Monthly', '365' => 'Daily'], 'default' => '12', 'icon' => 'bi-arrow-repeat'],
            ['name' => 'years',    'label' => 'Time (Years)',       'type' => 'number', 'step' => '0.1',  'min' => '0',   'placeholder' => 'e.g. 5',       'icon' => 'bi-calendar'],
        ],
        'loan-amortization' => [
            ['name' => 'loan_amount', 'label' => 'Loan Amount', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 25000', 'icon' => 'bi-bank2'],
            ['name' => 'rate',      'label' => 'Annual Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 6.5', 'icon' => 'bi-percent'],
            ['name' => 'months',    'label' => 'Loan Term (Months)', 'type' => 'number', 'step' => '1', 'min' => '1', 'placeholder' => 'e.g. 60', 'icon' => 'bi-calendar-month'],
        ],
        'mortgage' => [
            ['name' => 'home_price',    'label' => 'Home Price',    'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 300000', 'icon' => 'bi-house-door'],
            ['name' => 'down_payment',  'label' => 'Down Payment',  'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 60000',  'icon' => 'bi-cash-coin'],
            ['name' => 'interest_rate', 'label' => 'Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 6.5',  'icon' => 'bi-percent'],
            ['name' => 'loan_years',   'label' => 'Loan Term (Years)', 'type' => 'number', 'step' => '1',  'min' => '1', 'placeholder' => 'e.g. 30',     'icon' => 'bi-calendar-check'],
            ['name' => 'property_tax', 'label' => 'Annual Property Tax', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 2400',  'icon' => 'bi-receipt'],
            ['name' => 'home_insurance', 'label' => 'Annual Home Insurance', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'e.g. 1200', 'icon' => 'bi-shield-check'],
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
            [
                'name' => 'courses',
                'label' => 'Courses (JSON)',
                'type' => 'textarea',
                'placeholder' => '[{"credit_hours":3,"grade_point":4.0},{"credit_hours":3,"grade_point":3.0}]',
                'icon' => 'bi-clipboard-data',
                'help'  => 'Enter a JSON array of objects with <code>credit_hours</code> and <code>grade_point</code> (0-4 scale).'
            ],
        ],
        'bmi' => [
            ['name' => 'height', 'label' => 'Height (cm)', 'type' => 'number', 'step' => '0.1', 'min' => '1',  'placeholder' => 'e.g. 175', 'icon' => 'bi-ruler-vertical'],
            ['name' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number', 'step' => '0.1', 'min' => '1',  'placeholder' => 'e.g. 70',  'icon' => 'bi-basket'],
        ],
        'standard' => [
            ['name' => 'expression', 'label' => 'Expression', 'type' => 'text', 'placeholder' => 'e.g. 12 + 8 * (3 - 1)', 'icon' => 'bi-calculator'],
        ],
        'scientific' => [
            ['name' => 'expression', 'label' => 'Scientific Expression', 'type' => 'text', 'placeholder' => 'e.g. sin(1.57) + sqrt(16)', 'icon' => 'bi-bezier', 'help' => 'Supported functions: sin, cos, tan, sqrt, log, exp, abs, pow. Use ^ for power.'],
        ],
        'graphing' => [
            ['name' => 'expression', 'label' => 'Function of x', 'type' => 'text', 'placeholder' => 'e.g. sin(x) + x^2', 'icon' => 'bi-graph-up', 'help' => 'Enter a function in x, for example <code>sin(x) + x^2</code>.'],
        ],
        'programmer' => [
            ['name' => 'decimal', 'label' => 'Decimal', 'type' => 'text', 'placeholder' => 'e.g. 42', 'icon' => 'bi-hashtag'],
            ['name' => 'binary', 'label' => 'Binary', 'type' => 'text', 'placeholder' => 'e.g. 101010', 'icon' => 'bi-toggle-on'],
            ['name' => 'octal', 'label' => 'Octal', 'type' => 'text', 'placeholder' => 'e.g. 52', 'icon' => 'bi-circle-half'],
            ['name' => 'hex', 'label' => 'Hexadecimal', 'type' => 'text', 'placeholder' => 'e.g. 2A', 'icon' => 'bi-code'],
        ],
        'date-calculation' => [
            ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'icon' => 'bi-calendar-day'],
            ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'icon' => 'bi-calendar-event'],
            ['name' => 'add_days', 'label' => 'Days to Add', 'type' => 'number', 'step' => '1', 'placeholder' => 'e.g. 30', 'icon' => 'bi-plus-lg'],
            ['name' => 'subtract_days', 'label' => 'Days to Subtract', 'type' => 'number', 'step' => '1', 'placeholder' => 'e.g. 15', 'icon' => 'bi-dash-lg'],
        ],
        'currency-converter' => [
            ['name' => 'value', 'label' => 'Amount', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 100', 'icon' => 'bi-currency-dollar'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'JPY' => 'JPY', 'BDT' => 'BDT', 'INR' => 'INR'], 'default' => 'USD', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP', 'JPY' => 'JPY', 'BDT' => 'BDT', 'INR' => 'INR'], 'default' => 'EUR', 'icon' => 'bi-arrow-left-right'],
        ],
        'volume-converter' => [
            ['name' => 'value', 'label' => 'Amount', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 1.5', 'icon' => 'bi-droplet'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['liter' => 'Liter', 'milliliter' => 'Milliliter', 'cubic_meter' => 'Cubic Meter', 'gallon' => 'Gallon', 'pint' => 'Pint'], 'default' => 'liter', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['liter' => 'Liter', 'milliliter' => 'Milliliter', 'cubic_meter' => 'Cubic Meter', 'gallon' => 'Gallon', 'pint' => 'Pint'], 'default' => 'gallon', 'icon' => 'bi-arrow-left-right'],
        ],
        'length-converter' => [
            ['name' => 'value', 'label' => 'Distance', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 1000', 'icon' => 'bi-arrows-expand'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['meter' => 'Meter', 'kilometer' => 'Kilometer', 'centimeter' => 'Centimeter', 'millimeter' => 'Millimeter', 'mile' => 'Mile', 'yard' => 'Yard', 'foot' => 'Foot', 'inch' => 'Inch'], 'default' => 'meter', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['meter' => 'Meter', 'kilometer' => 'Kilometer', 'centimeter' => 'Centimeter', 'millimeter' => 'Millimeter', 'mile' => 'Mile', 'yard' => 'Yard', 'foot' => 'Foot', 'inch' => 'Inch'], 'default' => 'kilometer', 'icon' => 'bi-arrow-left-right'],
        ],
        'weight-converter' => [
            ['name' => 'value', 'label' => 'Weight', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 70', 'icon' => 'bi-balance-scale'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['kilogram' => 'Kilogram', 'gram' => 'Gram', 'pound' => 'Pound', 'ounce' => 'Ounce', 'tonne' => 'Tonne'], 'default' => 'kilogram', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['kilogram' => 'Kilogram', 'gram' => 'Gram', 'pound' => 'Pound', 'ounce' => 'Ounce', 'tonne' => 'Tonne'], 'default' => 'pound', 'icon' => 'bi-arrow-left-right'],
        ],
        'temperature-converter' => [
            ['name' => 'value', 'label' => 'Temperature', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 25', 'icon' => 'bi-thermometer-half'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['celsius' => 'Celsius', 'fahrenheit' => 'Fahrenheit', 'kelvin' => 'Kelvin'], 'default' => 'celsius', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['celsius' => 'Celsius', 'fahrenheit' => 'Fahrenheit', 'kelvin' => 'Kelvin'], 'default' => 'fahrenheit', 'icon' => 'bi-arrow-left-right'],
        ],
        'energy-converter' => [
            ['name' => 'value', 'label' => 'Energy', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 1000', 'icon' => 'bi-lightning-charge'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['joule' => 'Joule', 'calorie' => 'Calorie', 'kilojoule' => 'Kilojoule', 'kwh' => 'kWh', 'btu' => 'BTU'], 'default' => 'joule', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['joule' => 'Joule', 'calorie' => 'Calorie', 'kilojoule' => 'Kilojoule', 'kwh' => 'kWh', 'btu' => 'BTU'], 'default' => 'kilojoule', 'icon' => 'bi-arrow-left-right'],
        ],
        'area-converter' => [
            ['name' => 'value', 'label' => 'Area', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 100', 'icon' => 'bi-grid-1x2'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['square_meter' => 'Square Meter', 'square_kilometer' => 'Square Kilometer', 'square_foot' => 'Square Foot', 'square_yard' => 'Square Yard', 'acre' => 'Acre', 'hectare' => 'Hectare'], 'default' => 'square_meter', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['square_meter' => 'Square Meter', 'square_kilometer' => 'Square Kilometer', 'square_foot' => 'Square Foot', 'square_yard' => 'Square Yard', 'acre' => 'Acre', 'hectare' => 'Hectare'], 'default' => 'acre', 'icon' => 'bi-arrow-left-right'],
        ],
        'speed-converter' => [
            ['name' => 'value', 'label' => 'Speed', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 60', 'icon' => 'bi-speedometer2'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['mps' => 'm/s', 'kph' => 'km/h', 'mph' => 'mph', 'knots' => 'knots'], 'default' => 'kph', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['mps' => 'm/s', 'kph' => 'km/h', 'mph' => 'mph', 'knots' => 'knots'], 'default' => 'mph', 'icon' => 'bi-arrow-left-right'],
        ],
        'time-converter' => [
            ['name' => 'value', 'label' => 'Time', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 120', 'icon' => 'bi-clock'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['second' => 'Second', 'minute' => 'Minute', 'hour' => 'Hour', 'day' => 'Day'], 'default' => 'minute', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['second' => 'Second', 'minute' => 'Minute', 'hour' => 'Hour', 'day' => 'Day'], 'default' => 'hour', 'icon' => 'bi-arrow-left-right'],
        ],
        'power-converter' => [
            ['name' => 'value', 'label' => 'Power', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 1500', 'icon' => 'bi-plug'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['watt' => 'Watt', 'kilowatt' => 'Kilowatt', 'horsepower' => 'Horsepower'], 'default' => 'watt', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['watt' => 'Watt', 'kilowatt' => 'Kilowatt', 'horsepower' => 'Horsepower'], 'default' => 'kilowatt', 'icon' => 'bi-arrow-left-right'],
        ],
        'data-converter' => [
            ['name' => 'value', 'label' => 'Data', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 5', 'icon' => 'bi-hdd-stack'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['byte' => 'Byte', 'kilobyte' => 'Kilobyte', 'megabyte' => 'Megabyte', 'gigabyte' => 'Gigabyte', 'terabyte' => 'Terabyte'], 'default' => 'gigabyte', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['byte' => 'Byte', 'kilobyte' => 'Kilobyte', 'megabyte' => 'Megabyte', 'gigabyte' => 'Gigabyte', 'terabyte' => 'Terabyte'], 'default' => 'megabyte', 'icon' => 'bi-arrow-left-right'],
        ],
        'pressure-converter' => [
            ['name' => 'value', 'label' => 'Pressure', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 101325', 'icon' => 'bi-arrow-down-up'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['pascal' => 'Pascal', 'bar' => 'Bar', 'psi' => 'PSI', 'atm' => 'Atmosphere'], 'default' => 'pascal', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['pascal' => 'Pascal', 'bar' => 'Bar', 'psi' => 'PSI', 'atm' => 'Atmosphere'], 'default' => 'bar', 'icon' => 'bi-arrow-left-right'],
        ],
        'angle-converter' => [
            ['name' => 'value', 'label' => 'Angle', 'type' => 'number', 'step' => 'any', 'placeholder' => 'e.g. 180', 'icon' => 'bi-compass'],
            ['name' => 'from_unit', 'label' => 'From', 'type' => 'select', 'options' => ['degree' => 'Degree', 'radian' => 'Radian', 'grad' => 'Gradian'], 'default' => 'degree', 'icon' => 'bi-arrow-left-right'],
            ['name' => 'to_unit', 'label' => 'To', 'type' => 'select', 'options' => ['degree' => 'Degree', 'radian' => 'Radian', 'grad' => 'Gradian'], 'default' => 'radian', 'icon' => 'bi-arrow-left-right'],
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
        'groups'         => $groups,
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
            'standard'              => CalculatorService::standardCalculator(
                (string)($input['expression'] ?? ''),
            ),
            'scientific'            => CalculatorService::scientificCalculator(
                (string)($input['expression'] ?? ''),
            ),
            'graphing'              => CalculatorService::graphingCalculator(
                (string)($input['expression'] ?? ''),
            ),
            'programmer'            => CalculatorService::programmerCalculator(
                $input,
            ),
            'date-calculation'      => CalculatorService::dateCalculation(
                (string)($input['start_date'] ?? ''),
                isset($input['end_date']) ? (string)$input['end_date'] : null,
                isset($input['add_days']) ? intval($input['add_days']) : null,
                isset($input['subtract_days']) ? intval($input['subtract_days']) : null,
            ),
            'simple-interest'       => CalculatorService::simpleInterest(
                (float)($input['principal'] ?? 0),
                (float)($input['rate']     ?? 0),
                (float)($input['years']    ?? 0),
            ),
            'compound-interest'     => CalculatorService::compoundInterest(
                (float)($input['principal']  ?? 0),
                (float)($input['rate']       ?? 0),
                intval($input['frequency'] ?? 12),
                (float)($input['years']      ?? 0),
            ),
            'loan-amortization'     => CalculatorService::loanAmortization(
                (float)($input['loan_amount'] ?? 0),
                (float)($input['rate']        ?? 0),
                intval($input['months']     ?? 0),
            ),
            'mortgage'              => CalculatorService::mortgage(
                (float)($input['home_price']     ?? 0),
                (float)($input['down_payment']   ?? 0),
                (float)($input['interest_rate']  ?? 0),
                intval($input['loan_years']    ?? 0),
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
            'currency-converter'    => CalculatorService::currencyConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'USD'),
                (string)($input['to_unit']   ?? 'EUR'),
            ),
            'volume-converter'      => CalculatorService::volumeConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'liter'),
                (string)($input['to_unit']   ?? 'gallon'),
            ),
            'length-converter'      => CalculatorService::lengthConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'meter'),
                (string)($input['to_unit']   ?? 'kilometer'),
            ),
            'weight-converter'      => CalculatorService::weightConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'kilogram'),
                (string)($input['to_unit']   ?? 'pound'),
            ),
            'temperature-converter' => CalculatorService::temperatureConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'celsius'),
                (string)($input['to_unit']   ?? 'fahrenheit'),
            ),
            'energy-converter'      => CalculatorService::energyConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'joule'),
                (string)($input['to_unit']   ?? 'kilojoule'),
            ),
            'area-converter'        => CalculatorService::areaConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'square_meter'),
                (string)($input['to_unit']   ?? 'acre'),
            ),
            'speed-converter'       => CalculatorService::speedConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'kph'),
                (string)($input['to_unit']   ?? 'mph'),
            ),
            'time-converter'        => CalculatorService::timeConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'minute'),
                (string)($input['to_unit']   ?? 'hour'),
            ),
            'power-converter'       => CalculatorService::powerConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'watt'),
                (string)($input['to_unit']   ?? 'kilowatt'),
            ),
            'data-converter'        => CalculatorService::dataConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'gigabyte'),
                (string)($input['to_unit']   ?? 'megabyte'),
            ),
            'pressure-converter'    => CalculatorService::pressureConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'pascal'),
                (string)($input['to_unit']   ?? 'bar'),
            ),
            'angle-converter'       => CalculatorService::angleConverter(
                (float)($input['value']   ?? 0),
                (string)($input['from_unit'] ?? 'degree'),
                (string)($input['to_unit']   ?? 'radian'),
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
