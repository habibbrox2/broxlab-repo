<?php
// app/Helpers/CalculatorService.php
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
        'percentage',
        'percentage-change',
        'bmi',
        'gpa',
        'simple-interest',
        'compound-interest',
        'loan-amortization',
        'mortgage',
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
            // ── Financial ──────────────────────────────────────────────────
            'simple-interest'       => self::makeDef('simple-interest',       'financial',  'Simple Interest',            'fa-calculator'),
            'compound-interest'     => self::makeDef('compound-interest',     'financial',  'Compound Interest',          'fa-chart-line'),
            'loan-amortization'     => self::makeDef('loan-amortization',     'financial',  'Loan Amortization',          'fa-receipt'),
            'mortgage'              => self::makeDef('mortgage',              'financial',  'Mortgage Calculator',        'fa-house-chimney'),
            // ── Math ───────────────────────────────────────────────────────
            'percentage'            => self::makeDef('percentage',            'math',       'Percentage Calculator',      'fa-percent'),
            'percentage-change'     => self::makeDef('percentage-change',     'math',       'Percentage Change',          'fa-arrow-trend-up'),
            'gpa'                   => self::makeDef('gpa',                   'math',       'GPA Calculator',             'fa-graduation-cap'),
            // ── Health ─────────────────────────────────────────────────────
            'bmi'                   => self::makeDef('bmi',                   'health',     'BMI Calculator',             'fa-heart-pulse'),
        ];
    }

    /**
     * Return definitions grouped and sorted by category label.
     * @return array<string,array<string,mixed>>
     */
    public static function getGroupedDefinitions(): array
    {
        $groupLabels = [
            'financial' => 'Financial',
            'math'      => 'Math & Academic',
            'health'    => 'Health & Fitness',
        ];

        $all    = self::getAllDefinitions();
        $groups = [];
        foreach ($groupLabels as $key => $label) {
            $groups[$key] = ['label' => $label, 'items' => []];
        }
        foreach ($all as $def) {
            $cat = $def['category'];
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
            'description'=> self::describePercentage($value, $percent, $result),
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
            'category_class'=> $cat['class'],
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
