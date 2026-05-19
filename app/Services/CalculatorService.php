<?php
// app/Services/CalculatorService.php
//
// Pure-computation service for all BroxLab calculators.
// No DB access, no session handling — call the method you need and get
// back a typed result array.  Slugs match the keys used in routes + JS.

declare(strict_types=1);

/**
 * CalculatorService – server-side formulas for BroxLab calculators.
 */
class CalculatorService
{
    // ── supported type validation ────────────────────────────────────────
    private const ALLOWED_TYPES = [
        'standard',
        'scientific',
        'graphing',
        'programmer',
        'date-calculation',
        'simple-interest',
        'compound-interest',
        'loan-amortization',
        'mortgage',
        'percentage',
        'percentage-change',
        'gpa',
        'bmi',
        'currency-converter',
        'volume-converter',
        'length-converter',
        'weight-converter',
        'temperature-converter',
        'energy-converter',
        'area-converter',
        'speed-converter',
        'time-converter',
        'power-converter',
        'data-converter',
        'pressure-converter',
        'angle-converter',
    ];

    /**
     * Returns the list of types that this service knows about.
     * @return string[]
     */
    public static function getSupportedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    /**
     * Full metadata for every calculator (used by listings pages).
     * @return array<string, array<string,mixed>>
     */
    public static function getAllDefinitions(): array
    {
        return [
            // ── Calculator ─────────────────────────────────────────────────
            'standard'              => self::makeDef('standard',              'calculator', 'Standard Calculator',         'bi-calculator'),
            'scientific'            => self::makeDef('scientific',            'calculator', 'Scientific Calculator',       'bi-bezier'),
            'graphing'              => self::makeDef('graphing',              'calculator', 'Graphing Calculator',         'bi-graph-up'),
            'programmer'            => self::makeDef('programmer',            'calculator', 'Programmer Calculator',       'bi-code-slash'),
            'date-calculation'      => self::makeDef('date-calculation',      'calculator', 'Date Calculator',             'bi-calendar-check'),
            'simple-interest'       => self::makeDef('simple-interest',       'calculator', 'Simple Interest',            'bi-cash-stack'),
            'compound-interest'     => self::makeDef('compound-interest',     'calculator', 'Compound Interest',          'bi-arrow-repeat'),
            'loan-amortization'     => self::makeDef('loan-amortization',     'calculator', 'Loan Amortization',          'bi-receipt'),
            'mortgage'              => self::makeDef('mortgage',              'calculator', 'Mortgage Calculator',        'bi-house'),
            'percentage'            => self::makeDef('percentage',            'calculator', 'Percentage Calculator',      'bi-percent'),
            'percentage-change'     => self::makeDef('percentage-change',     'calculator', 'Percentage Change',          'bi-arrow-up-right'),
            'gpa'                   => self::makeDef('gpa',                   'calculator', 'GPA Calculator',             'bi-mortarboard'),
            'bmi'                   => self::makeDef('bmi',                   'calculator', 'BMI Calculator',             'bi-heart-pulse'),
            // ── Converter ──────────────────────────────────────────────────
            'currency-converter'    => self::makeDef('currency-converter',    'converter',  'Currency Converter',         'bi-currency-exchange'),
            'volume-converter'      => self::makeDef('volume-converter',      'converter',  'Volume Converter',           'bi-droplet-half'),
            'length-converter'      => self::makeDef('length-converter',      'converter',  'Length Converter',           'bi-arrows-expand'),
            'weight-converter'      => self::makeDef('weight-converter',      'converter',  'Weight & Mass Converter',    'bi-balance-scale'),
            'temperature-converter' => self::makeDef('temperature-converter', 'converter',  'Temperature Converter',      'bi-thermometer-half'),
            'energy-converter'      => self::makeDef('energy-converter',      'converter',  'Energy Converter',           'bi-lightning-charge'),
            'area-converter'        => self::makeDef('area-converter',        'converter',  'Area Converter',             'bi-grid-1x2'),
            'speed-converter'       => self::makeDef('speed-converter',       'converter',  'Speed Converter',            'bi-speedometer2'),
            'time-converter'        => self::makeDef('time-converter',        'converter',  'Time Converter',             'bi-clock'),
            'power-converter'       => self::makeDef('power-converter',       'converter',  'Power Converter',            'bi-plug'),
            'data-converter'        => self::makeDef('data-converter',        'converter',  'Data Converter',             'bi-hdd-stack'),
            'pressure-converter'    => self::makeDef('pressure-converter',    'converter',  'Pressure Converter',         'bi-arrow-down-up'),
            'angle-converter'       => self::makeDef('angle-converter',       'converter',  'Angle Converter',            'bi-compass'),
        ];
    }

    /**
     * Return definitions grouped and sorted by category label.
     * @return array<string,array<string,mixed>>
     */
    public static function getGroupedDefinitions(): array
    {
        $groupLabels = [
            'calculator' => 'Calculator',
            'converter'  => 'Converters',
        ];

        $all    = self::getAllDefinitions();
        $groups = [];
        foreach ($groupLabels as $key => $label) {
            $groups[$key] = ['label' => $label, 'items' => []];
        }
        foreach ($all as $def) {
            $cat = $def['category'];
            if (!isset($groups[$cat])) {
                $groups[$cat] = ['label' => ucfirst($cat), 'items' => []];
            }
            $groups[$cat]['items'][] = $def;
        }
        foreach ($groups as $cat => &$group) {
            usort($group['items'], fn($a, $b) => strcmp($a['label'], $b['label']));
        }
        return $groups;
    }

    /**
     * Definition for a single calculator type.
     */
    private static function makeDef(string $type, string $category, string $label, string $icon): array
    {
        return compact('type', 'category', 'label', 'icon');
    }

    private static function evaluateMathExpression(string $expression, bool $allowFunctions = false): float
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException('Expression is required.');
        }

        $expression = str_replace(['^', '×', '÷'], ['**', '*', '/'], $expression);
        $expression = preg_replace('/\bln\(/i', 'log(', $expression);
        $expression = preg_replace('/\bPI\b/i', 'pi', $expression);
        $expression = preg_replace('/\s+/', '', $expression);

        if ($allowFunctions) {
            $allowedNames = [
                'sin',
                'cos',
                'tan',
                'asin',
                'acos',
                'atan',
                'sqrt',
                'log',
                'exp',
                'abs',
                'pow',
                'min',
                'max',
                'pi',
                'e',
            ];
            $expression = preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', function ($match) use ($allowedNames) {
                $token = strtolower($match[1]);
                if (!in_array($token, $allowedNames, true)) {
                    throw new InvalidArgumentException('Invalid expression token: ' . $match[1]);
                }
                return $token;
            }, $expression);
        } elseif (preg_match('/[a-zA-Z]/', $expression)) {
            throw new InvalidArgumentException('Only numeric arithmetic expressions are allowed.');
        }

        if (preg_match('/[^0-9\.\+\-\*\/\%\(\),eE]/', $expression)) {
            throw new InvalidArgumentException('Expression contains invalid characters.');
        }

        try {
            $result = eval('return ' . $expression . ';');
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Unable to evaluate expression.');
        }

        if (!is_numeric($result) || !is_finite((float)$result)) {
            throw new InvalidArgumentException('Expression did not return a valid number.');
        }

        return (float)$result;
    }

    public static function standardCalculator(string $expression): array
    {
        return [
            'expression' => $expression,
            'result'     => self::evaluateMathExpression($expression, false),
        ];
    }

    public static function scientificCalculator(string $expression): array
    {
        return [
            'expression' => $expression,
            'result'     => self::evaluateMathExpression($expression, true),
        ];
    }

    public static function graphingCalculator(string $expression): array
    {
        $points = [];
        $samples = [-2, -1, -0.5, 0, 0.5, 1, 2];

        foreach ($samples as $x) {
            $expr = preg_replace('/\bx\b/', '(' . $x . ')', $expression);
            $points[] = [
                'x' => $x,
                'y' => self::evaluateMathExpression($expr, true),
            ];
        }

        return [
            'expression' => $expression,
            'points'     => $points,
        ];
    }

    public static function programmerCalculator(array $input): array
    {
        $decimal = null;

        if (isset($input['decimal']) && $input['decimal'] !== '') {
            if (!preg_match('/^\d+$/', trim((string)$input['decimal']))) {
                throw new InvalidArgumentException('Invalid decimal input.');
            }
            $decimal = (int) trim((string)$input['decimal']);
        }
        if ($decimal === null && isset($input['binary']) && $input['binary'] !== '') {
            $bin = trim((string)$input['binary']);
            if (!preg_match('/^[01]+$/', $bin)) {
                throw new InvalidArgumentException('Invalid binary input.');
            }
            $decimal = bindec($bin);
        }
        if ($decimal === null && isset($input['octal']) && $input['octal'] !== '') {
            $oct = trim((string)$input['octal']);
            if (!preg_match('/^[0-7]+$/', $oct)) {
                throw new InvalidArgumentException('Invalid octal input.');
            }
            $decimal = octdec($oct);
        }
        if ($decimal === null && isset($input['hex']) && $input['hex'] !== '') {
            $hex = trim((string)$input['hex']);
            if (!preg_match('/^[0-9a-fA-F]+$/', $hex)) {
                throw new InvalidArgumentException('Invalid hexadecimal input.');
            }
            $decimal = hexdec($hex);
        }

        if ($decimal === null) {
            throw new InvalidArgumentException('Please enter a decimal, binary, octal, or hexadecimal value.');
        }

        return [
            'decimal' => $decimal,
            'binary'  => decbin($decimal),
            'octal'   => decoct($decimal),
            'hex'     => strtoupper(dechex($decimal)),
        ];
    }

    public static function dateCalculation(string $startDate, ?string $endDate = null, ?int $addDays = null, ?int $subtractDays = null): array
    {
        $start = DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$start) {
            throw new InvalidArgumentException('Start date is required and must use YYYY-MM-DD format.');
        }

        $result = ['start_date' => $start->format('Y-m-d')];

        if ($endDate !== null && $endDate !== '') {
            $end = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$end) {
                throw new InvalidArgumentException('End date must use YYYY-MM-DD format.');
            }
            $interval = $start->diff($end);
            $result['end_date'] = $end->format('Y-m-d');
            $result['difference_days'] = (int)$interval->format('%r%a');
        }

        if ($addDays !== null && $addDays !== 0) {
            $copy = clone $start;
            $copy->modify('+' . $addDays . ' days');
            $result['add_days'] = $addDays;
            $result['add_date'] = $copy->format('Y-m-d');
        }

        if ($subtractDays !== null && $subtractDays !== 0) {
            $copy = clone $start;
            $copy->modify('-' . $subtractDays . ' days');
            $result['subtract_days'] = $subtractDays;
            $result['subtract_date'] = $copy->format('Y-m-d');
        }

        if (count($result) === 1) {
            throw new InvalidArgumentException('Enter an end date or a number of days to add/subtract.');
        }

        return $result;
    }

    private static function convertUnits(float $value, string $from, string $to, array $units): float
    {
        if (!isset($units[$from]) || !isset($units[$to])) {
            throw new InvalidArgumentException('Invalid unit conversion.');
        }
        return $value * $units[$from] / $units[$to];
    }

    public static function currencyConverter(float $value, string $from, string $to): array
    {
        $rates = [
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.81,
            'JPY' => 139.58,
            'BDT' => 109.65,
            'INR' => 83.35,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $rates), 4),
            'rates'      => $rates,
        ];
    }

    public static function volumeConverter(float $value, string $from, string $to): array
    {
        $units = [
            'liter'      => 1.0,
            'milliliter' => 0.001,
            'cubic_meter' => 1000.0,
            'gallon'     => 3.78541,
            'pint'       => 0.473176,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function lengthConverter(float $value, string $from, string $to): array
    {
        $units = [
            'meter'      => 1.0,
            'kilometer'  => 1000.0,
            'centimeter' => 0.01,
            'millimeter' => 0.001,
            'mile'       => 1609.34,
            'yard'       => 0.9144,
            'foot'       => 0.3048,
            'inch'       => 0.0254,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function weightConverter(float $value, string $from, string $to): array
    {
        $units = [
            'kilogram' => 1.0,
            'gram'     => 0.001,
            'pound'    => 0.453592,
            'ounce'    => 0.0283495,
            'tonne'    => 1000.0,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function temperatureConverter(float $value, string $from, string $to): array
    {
        if ($from === $to) {
            return ['value' => $value, 'from_unit' => $from, 'to_unit' => $to, 'converted' => $value];
        }

        $toCelsius = match ($from) {
            'celsius'    => $value,
            'fahrenheit' => ($value - 32) * 5 / 9,
            'kelvin'     => $value - 273.15,
            default      => throw new InvalidArgumentException('Invalid temperature unit.'),
        };

        $converted = match ($to) {
            'celsius'    => $toCelsius,
            'fahrenheit' => ($toCelsius * 9 / 5) + 32,
            'kelvin'     => $toCelsius + 273.15,
            default      => throw new InvalidArgumentException('Invalid temperature unit.'),
        };

        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round($converted, 4),
        ];
    }

    public static function energyConverter(float $value, string $from, string $to): array
    {
        $units = [
            'joule' => 1.0,
            'calorie' => 4.184,
            'kilojoule' => 1000.0,
            'kwh' => 3.6e6,
            'btu' => 1055.06,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function areaConverter(float $value, string $from, string $to): array
    {
        $units = [
            'square_meter' => 1.0,
            'square_kilometer' => 1e6,
            'square_foot' => 0.092903,
            'square_yard' => 0.836127,
            'acre' => 4046.86,
            'hectare' => 10000.0,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function speedConverter(float $value, string $from, string $to): array
    {
        $units = [
            'mps' => 1.0,
            'kph' => 0.277778,
            'mph' => 0.44704,
            'knots' => 0.514444,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function timeConverter(float $value, string $from, string $to): array
    {
        $units = [
            'second' => 1.0,
            'minute' => 60.0,
            'hour'   => 3600.0,
            'day'    => 86400.0,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function powerConverter(float $value, string $from, string $to): array
    {
        $units = [
            'watt' => 1.0,
            'kilowatt' => 1000.0,
            'horsepower' => 745.7,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function dataConverter(float $value, string $from, string $to): array
    {
        $units = [
            'byte' => 1.0,
            'kilobyte' => 1024.0,
            'megabyte' => 1048576.0,
            'gigabyte' => 1073741824.0,
            'terabyte' => 1099511627776.0,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function pressureConverter(float $value, string $from, string $to): array
    {
        $units = [
            'pascal' => 1.0,
            'bar' => 100000.0,
            'psi' => 6894.76,
            'atm' => 101325.0,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    public static function angleConverter(float $value, string $from, string $to): array
    {
        $units = [
            'degree' => 1.0,
            'radian' => pi() / 180.0,
            'grad' => 0.9,
        ];
        return [
            'value'      => $value,
            'from_unit'  => $from,
            'to_unit'    => $to,
            'converted'  => round(self::convertUnits($value, $from, $to, $units), 6),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #1  SIMPLE INTEREST
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @param float $principal
     * @param float $rate        annual %  (e.g. 5 for 5 %)
     * @param float $timeYears   years
     * @return array{interest:float,total:float,currency_symbol:string}
     */
    public static function simpleInterest(float $principal, float $rate, float $timeYears): array
    {
        self::validatePositive($principal, 'principal');
        self::validatePositive($timeYears, 'time');
        if ($rate < 0) {
            throw new InvalidArgumentException('Rate must be >= 0');
        }
        $interest = $principal * ($rate / 100) * $timeYears;
        $total    = $principal + $interest;
        return [
            'interest'       => round($interest, 4),
            'total_after'    => round($total, 4),
            'principal'      => $principal,
            'rate_percent'   => $rate,
            'time_years'     => $timeYears,
            'currency_symbol' => '$',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #2  COMPOUND INTEREST
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @param float $principal   starting investment
     * @param float $rate        annual %  (e.g. 7 for 7 %)
     * @param int   $n           compound frequency per year (1/12/365)
     * @param float $timeYears   years
     * @return array{total:float,interest_earned:float,currency_symbol:string}
     */
    public static function compoundInterest(float $principal, float $rate, int $n, float $timeYears): array
    {
        self::validatePositive($principal, 'principal');
        self::validatePositive($timeYears, 'time');
        self::validatePositiveInteger($n, 'frequency');
        if ($rate < 0) {
            throw new InvalidArgumentException('Rate must be >= 0');
        }
        $r  = $rate / 100;
        $total     = $principal * pow(1 + $r / $n, $n * $timeYears);
        $interestE = $total - $principal;
        return [
            'total_amount'    => round($total, 4),
            'interest_earned' => round($interestE, 4),
            'principal'       => $principal,
            'rate_percent'    => $rate,
            'compounds_per_year' => $n,
            'time_years'      => $timeYears,
            'currency_symbol' => '$',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #3  LOAN AMORTIZATION
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @param float $loanAmount
     * @param float $annualRate    annual %  (e.g. 6.5 for 6.5 %)
     * @param int   $months        loan term in months
     * @return array{monthly_payment:float,total_payment:float,total_interest:float,...
     */
    public static function loanAmortization(float $loanAmount, float $annualRate, int $months): array
    {
        self::validatePositive($loanAmount, 'loan amount');
        self::validatePositiveInteger($months, 'loan term months');
        if ($annualRate < 0) {
            throw new InvalidArgumentException('Rate must be >= 0');
        }

        if ($annualRate == 0) {
            $monthly = $loanAmount / $months;
            $total    = $loanAmount;
            $interest = 0.0;
        } else {
            $monthlyRate = $annualRate / 100 / 12;
            $monthly     = $loanAmount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
            $total       = $monthly * $months;
            $interest    = $total - $loanAmount;
        }

        return [
            'monthly_payment'  => round($monthly, 4),
            'total_payment'    => round($total, 4),
            'total_interest'   => round($interest, 4),
            'loan_amount'      => $loanAmount,
            'annual_rate_pct'  => $annualRate,
            'loan_term_months' => $months,
            'currency_symbol'  => '$',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #4  MORTGAGE
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @param float $homePrice
     * @param float $downPayment
     * @param float $interestRate   annual %
     * @param int   $loanYears      loan term in years
     * @return array{monthly_payment:float,total_payment:float,total_interest:float,...}
     */
    public static function mortgage(
        float $homePrice,
        float $downPayment,
        float $interestRate,
        int   $loanYears,
        float $propertyTax     = 0.0,
        float $homeInsurance   = 0.0,
        float $hoaMonthly      = 0.0,
    ): array {
        self::validatePositive($homePrice, 'home price');
        self::validatePositive($downPayment, 'down payment');
        self::validatePositiveInteger($loanYears, 'loan term years');
        if ($interestRate < 0) {
            throw new InvalidArgumentException('Rate must be >= 0');
        }

        $loanAmount = max(0.0, $homePrice - $downPayment);
        $months     = $loanYears * 12;

        $monthlyRate = $interestRate / 100 / 12;

        if ($loanAmount == 0 || ($interestRate == 0 && $months > 0)) {
            $baseMonthly = $loanAmount / $months;
        } elseif ($interestRate == 0) {
            $baseMonthly = 0;
        } else {
            $baseMonthly = $loanAmount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        }

        $monthlyPI     = round($baseMonthly, 4);
        $monthlyTax    = $propertyTax    / 12;
        $monthlyIns    = $homeInsurance  / 12;
        $monthlyHoa    = $hoaMonthly;
        $monthlyTotal  = $monthlyPI + $monthlyTax + $monthlyIns + $monthlyHoa;

        $totalPayment  = $monthlyPI * $months + $propertyTax + ($homeInsurance * $loanYears) + ($hoaMonthly * $months);
        $totalInterest = $totalPayment - $loanAmount - $propertyTax - ($homeInsurance * $loanYears) - ($hoaMonthly * $months);
        $ltvRatio      = $homePrice > 0 ? max(0.0, min(100.0, $loanAmount / $homePrice * 100)) : 0.0;

        return [
            'monthly_payment_pi'  => $monthlyPI,
            'monthly_tax'         => round($monthlyTax, 4),
            'monthly_insurance'   => round($monthlyIns, 4),
            'monthly_hoa'         => $monthlyHoa,
            'monthly_total'       => round($monthlyTotal, 4),
            'total_payment'       => round($totalPayment, 4),
            'total_interest'      => round(max(0, $totalInterest), 4),
            'loan_amount'         => $loanAmount,
            'down_payment'        => $downPayment,
            'home_price'          => $homePrice,
            'ltv_ratio'           => round($ltvRatio, 2),
            'loan_term_years'     => $loanYears,
            'interest_rate_pct'   => $interestRate,
            'property_tax'        => $propertyTax,
            'home_insurance'      => $homeInsurance,
            'hoa_monthly'         => $hoaMonthly,
            'currency_symbol'     => '$',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #5  PERCENTAGE
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @return array{result:float,description:string}
     */
    public static function percentage(float $value, float $percent): array
    {
        self::validateNumeric($value,    'value');
        self::validateNumeric($percent,  'percent');
        $result = $value * $percent / 100;
        return [
            'result'     => round($result, 6),
            'value'      => $value,
            'percent'    => $percent,
            'description' => self::describePercentage($value, $percent, $result),
        ];
    }

    private static function describePercentage(float $value, float $percent, float $result): string
    {
        if ($percent == 0) {
            return sprintf('%g%% of %g is %g', $percent, $value, $result);
        }
        return sprintf('%g%% of %g = %g', $percent, $result, $value);
    }

    // ══════════════════════════════════════════════════════════════════════
    // #6  PERCENTAGE CHANGE
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @return array{change:float,change_abs:float,direction:string,new_value:float}
     */
    public static function percentageChange(float $from, float $to): array
    {
        self::validateNumeric($from, 'from value');
        self::validateNumeric($to,   'to value');
        if ($from == 0) {
            throw new InvalidArgumentException('"From" value must not be zero');
        }
        $change  = round((($to - $from) / abs($from)) * 100, 6);
        $absChange = round(abs($change), 6);
        $direction = $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'no change');
        return [
            'change'          => $change,
            'absolute_change' => $absChange,
            'direction'       => $direction,
            'from'            => $from,
            'to'              => $to,
            'new_value'       => $to,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // #7  GPA
    // ══════════════════════════════════════════════════════════════════════
    /**
     * Core GPA computation.
     * @param list<array{credit_hours:float,grade_point:float}> $courses
     * @return array{gpa:float,total_credits:float,total_points:float,letter:string}
     */
    public static function gpa(array $courses): array
    {
        $totalCredits = 0.0;
        $totalPoints  = 0.0;

        foreach ($courses as $c) {
            $credits = (float)($c['credit_hours'] ?? 0);
            $gpa     = (float)($c['grade_point'] ?? 0);
            if ($credits <= 0 || $gpa < 0) {
                continue;
            }
            $totalCredits += $credits;
            $totalPoints  += $credits * $gpa;
        }

        if ($totalCredits == 0) {
            return ['gpa' => 0.0, 'total_credits' => 0.0, 'total_points' => 0.0, 'letter' => 'N/A'];
        }

        $gpa = round($totalPoints / $totalCredits, 2);
        return [
            'gpa'           => $gpa,
            'total_credits' => round($totalCredits, 2),
            'total_points'  => round($totalPoints, 4),
            'letter'        => self::gpaToLetter($gpa),
        ];
    }

    private static function gpaToLetter(float $gpa): string
    {
        return match (true) {
            $gpa >= 4.0       => 'A+',
            $gpa >= 3.7       => 'A',
            $gpa >= 3.3       => 'A-',
            $gpa >= 3.0       => 'B+',
            $gpa >= 2.7       => 'B',
            $gpa >= 2.3       => 'B-',
            $gpa >= 2.0       => 'C+',
            $gpa >= 1.7       => 'C',
            $gpa >= 1.3       => 'C-',
            $gpa >= 1.0       => 'D',
            default           => 'F',
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // #8  BMI
    // ══════════════════════════════════════════════════════════════════════
    /**
     * @return array{bmi:float,category:string,description:string,min_healthy:float,max_healthy:float}
     */
    public static function bmi(float $heightCm, float $weightKg): array
    {
        self::validatePositive($heightCm, 'height');
        self::validatePositive($weightKg, 'weight');

        $heightM = $heightCm / 100;
        $bmi     = $weightKg / ($heightM * $heightM);
        $bmi     = round($bmi, 1);
        $cat     = self::bmiCategory($bmi);

        // recommended weight range for the given height
        $minHealthy = round(18.5 * $heightM * $heightM, 1);
        $maxHealthy = round(24.9 * $heightM * $heightM, 1);

        return [
            'bmi'          => $bmi,
            'height_cm'    => $heightCm,
            'height_feet'  => round($heightCm / 30.48, 2),
            'weight_kg'    => $weightKg,
            'weight_lbs'   => round($weightKg * 2.20462, 1),
            'category'     => $cat['label'],
            'category_class' => $cat['class'],
            'description'  => $cat['description'],
            'min_healthy'  => $minHealthy,
            'max_healthy'  => $maxHealthy,
        ];
    }

    private static function bmiCategory(float $bmi): array
    {
        return match (true) {
            $bmi < 18.5  => ['label' => 'Underweight', 'class' => 'text-info',    'description' => 'Below 18.5 is considered underweight.'],
            $bmi < 25    => ['label' => 'Normal weight', 'class' => 'text-success', 'description' => '18.5 – 24.9 is considered healthy.'],
            $bmi < 30    => ['label' => 'Overweight',  'class' => 'text-warning', 'description' => '25 – 29.9 is considered overweight.'],
            $bmi < 35    => ['label' => 'Obesity Class I', 'class' => 'text-danger', 'description' => '30 – 34.9 is considered obese (Class I).'],
            $bmi < 40    => ['label' => 'Obesity Class II', 'class' => 'text-danger', 'description' => '35 – 39.9 is considered obese (Class II).'],
            default      => ['label' => 'Extreme Obesity',  'class' => 'text-danger', 'description' => '40 or above is considered extreme obesity.'],
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ══════════════════════════════════════════════════════════════════════
    private static function validatePositive(float $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$field} must be greater than 0 (got {$value})");
        }
    }

    private static function validatePositiveInteger(int $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$field} must be a positive integer (got {$value})");
        }
    }

    private static function validateNumeric(float $value, string $field): void
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException("{$field} must be a valid number");
        }
    }
}
