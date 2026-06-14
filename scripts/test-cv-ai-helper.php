<?php
/**
 * Unit tests for CvAiHelper::generateCoverLetter()
 *
 * Tests both the AI generation path (via mock AgentClient)
 * and the local fallback (when AI throws an exception).
 *
 * Usage: php scripts/test-cv-ai-helper.php
 */

// ─── Bootstrap ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/../app/Helpers/CvAiHelper.php';

$testsPassed = 0;
$testsFailed = 0;

// ─── Test Assertion Helpers ─────────────────────────────────────────────────

function assert_eq($expected, $actual, string $label): void
{
    global $testsPassed, $testsFailed;
    if ($expected === $actual) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Expected: " . var_export($expected, true) . "\n";
        echo "     Actual:   " . var_export($actual, true) . "\n";
    }
}

function assert_not_eq($expected, $actual, string $label): void
{
    global $testsPassed, $testsFailed;
    if ($expected !== $actual) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Not expected: " . var_export($expected, true) . "\n";
    }
}

function assert_contains(string $haystack, string $needle, string $label): void
{
    global $testsPassed, $testsFailed;
    if (str_contains($haystack, $needle)) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Expected to contain: " . var_export($needle, true) . "\n";
    }
}

function assert_has_keys(array $arr, array $keys, string $label): void
{
    global $testsPassed, $testsFailed;
    $missing = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $arr)) {
            $missing[] = $key;
        }
    }
    if (empty($missing)) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Missing keys: " . implode(', ', $missing) . "\n";
    }
}

function assert_is_string($value, string $label): void
{
    global $testsPassed, $testsFailed;
    if (is_string($value)) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Expected string, got: " . gettype($value) . "\n";
    }
}

function assert_true($value, string $label): void
{
    global $testsPassed, $testsFailed;
    if ($value === true) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Expected true, got: " . var_export($value, true) . "\n";
    }
}

function assert_gt(int $expectedMin, $actual, string $label): void
{
    global $testsPassed, $testsFailed;
    if ($actual > $expectedMin) {
        $testsPassed++;
        echo "  PASS {$label}\n";
    } else {
        $testsFailed++;
        echo "  FAIL {$label}\n";
        echo "     Expected > {$expectedMin}, got: " . var_export($actual, true) . "\n";
    }
}

// ─── Mock AgentClient ───────────────────────────────────────────────────────

class MockAgentClient
{
    public bool $shouldThrow = false;
    private string $mockResponse;

    public function __construct(string $mockResponse = '')
    {
        $this->mockResponse = $mockResponse ?: self::defaultResponse();
    }

    public function chat(array $messages, ?string $provider = null, ?string $model = null, ...$extra): string
    {
        if ($this->shouldThrow) {
            throw new Exception('Simulated AI API failure');
        }
        return $this->mockResponse;
    }

    public static function defaultResponse(): string
    {
        return "Dear Hiring Manager,\n\n"
            . "I am writing to express my strong interest in the Senior Developer position at Acme Corp. "
            . "With over 8 years of experience in full-stack web development and a proven track record of "
            . "delivering scalable applications, I am confident that my skills align perfectly with this role.\n\n"
            . "In my previous role at TechStart Inc., I led the development of a microservices architecture "
            . "that reduced API response times by 40%. I also mentored a team of 4 junior developers and "
            . "implemented CI/CD pipelines that cut deployment time by 60%.\n\n"
            . "I am particularly drawn to Acme Corp's commitment to innovation and would welcome the "
            . "opportunity to contribute to your engineering team. Thank you for considering my application.\n\n"
            . "Sincerely,\nJohn Doe";
    }
}

// ─── Test Helper Factory ────────────────────────────────────────────────────

function createTestHelper(?MockAgentClient $mockAgent = null): CvAiHelper
{
    $refClass = new ReflectionClass(CvAiHelper::class);
    $helper = $refClass->newInstanceWithoutConstructor();

    $mysqliProp = $refClass->getProperty('mysqli');
    $mysqliProp->setAccessible(true);
    $mysqliProp->setValue($helper, null);

    $agentProp = $refClass->getProperty('agentClient');
    $agentProp->setAccessible(true);
    $agentProp->setValue($helper, $mockAgent ?? new MockAgentClient());

    return $helper;
}

function invokePrivateMethod(object $obj, string $methodName, array $args = []): mixed
{
    $refClass = new ReflectionClass($obj);
    $method = $refClass->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invoke($obj, ...$args);
}

// ─── Test Data ──────────────────────────────────────────────────────────────

function sampleCvData(): array
{
    return [
        'personal' => [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1-555-0123',
            'location' => 'San Francisco, CA',
        ],
        'summary' => 'Senior full-stack developer with 8+ years of experience building scalable web applications using PHP, JavaScript, and cloud technologies.',
        'experience' => [
            [
                'position' => 'Senior Developer',
                'company' => 'TechStart Inc.',
                'start_date' => '2020-01',
                'end_date' => '2024-12',
                'description' => 'Led development of microservices architecture. Mentored 4 junior developers. Built CI/CD pipelines.',
            ],
            [
                'position' => 'Web Developer',
                'company' => 'WebCo',
                'start_date' => '2016-03',
                'end_date' => '2019-12',
                'description' => 'Built and maintained customer-facing web applications using PHP and React.',
            ],
        ],
        'education' => [
            [
                'institution' => 'State University',
                'degree' => 'B.S. Computer Science',
                'start_date' => '2012-09',
                'end_date' => '2016-06',
            ],
        ],
        'skills' => ['PHP', 'JavaScript', 'MySQL', 'React', 'Docker', 'AWS', 'Git', 'REST APIs'],
    ];
}

// ─── Test Cases ─────────────────────────────────────────────────────────────

function testGenerateCoverLetterAiPath(): void
{
    echo "\n== Test: generateCoverLetter() -- AI Generation Path ==\n";

    $helper = createTestHelper();
    $cvData = sampleCvData();
    $result = $helper->generateCoverLetter($cvData, 'Acme Corp', 'Senior Developer', 'We need a skilled developer...');

    assert_has_keys($result, ['cover_letter', 'word_count', 'generated_at'], 'Result has cover_letter, word_count, generated_at keys');
    assert_is_string($result['cover_letter'], 'cover_letter is a string');
    assert_not_eq('', $result['cover_letter'], 'cover_letter is not empty');
    assert_gt(0, $result['word_count'], 'word_count is positive (' . $result['word_count'] . ')');
    assert_true(strtotime($result['generated_at']) !== false, 'generated_at is a valid date string');
    assert_contains($result['cover_letter'], 'Acme Corp', 'Letter mentions Acme Corp');
    assert_contains($result['cover_letter'], 'Senior Developer', 'Letter mentions Senior Developer');

    echo "   (letter excerpt): " . substr($result['cover_letter'], 0, 100) . "...\n";
}

function testGenerateCoverLetterLocalFallback(): void
{
    echo "\n== Test: generateCoverLetter() -- Local Fallback (AI throws exception) ==\n";

    $mockAgent = new MockAgentClient();
    $mockAgent->shouldThrow = true;
    $helper = createTestHelper($mockAgent);
    $cvData = sampleCvData();
    $result = $helper->generateCoverLetter($cvData, 'Acme Corp', 'Senior Developer', '');

    assert_has_keys($result, ['cover_letter', 'word_count', 'generated_at'], 'Result has cover_letter, word_count, generated_at keys');
    assert_is_string($result['cover_letter'], 'cover_letter is a string');
    assert_not_eq('', $result['cover_letter'], 'cover_letter is not empty');
    assert_gt(0, $result['word_count'], 'word_count is positive (' . $result['word_count'] . ')');
    assert_contains($result['cover_letter'], 'Acme Corp', 'Local letter mentions company name');
    assert_contains($result['cover_letter'], 'Senior Developer', 'Local letter mentions job title');
    assert_contains($result['cover_letter'], 'John Doe', 'Local letter includes applicant full name');
    assert_contains($result['cover_letter'], 'Sincerely', 'Local letter has Sincerely closing');
}

function testLocalCoverLetterDirect(): void
{
    echo "\n== Test: localCoverLetter() -- Direct via Reflection ==\n";

    $helper = createTestHelper();
    $cvData = sampleCvData();

    $result = invokePrivateMethod($helper, 'localCoverLetter', [
        $cvData,
        'StartupXYZ',
        'Junior Developer',
        'Entry level position for web developers.',
    ]);

    assert_has_keys($result, ['cover_letter', 'word_count', 'generated_at'], 'Result has expected keys');
    assert_contains($result['cover_letter'], 'StartupXYZ', 'Letter mentions company');
    assert_contains($result['cover_letter'], 'Junior Developer', 'Letter mentions title');
    assert_contains($result['cover_letter'], 'John Doe', 'Letter mentions applicant name');
    assert_contains($result['cover_letter'], 'Senior full-stack developer', 'Letter includes summary text');
    assert_gt(0, $result['word_count'], 'word_count is positive (' . $result['word_count'] . ')');
}

/**
 * Test: empty CV data via local fallback path (mock throws).
 * The local template uses input data, so company/job title should appear.
 */
function testGenerateCoverLetterEmptyCv(): void
{
    echo "\n== Test: generateCoverLetter() -- Empty CV Data (local fallback) ==\n";

    $cvData = [
        'personal' => [],
        'summary' => '',
        'experience' => [],
        'education' => [],
        'skills' => [],
    ];

    // Use a mock that throws so the local fallback is invoked
    $mockAgent = new MockAgentClient();
    $mockAgent->shouldThrow = true;
    $helper = createTestHelper($mockAgent);
    $result = $helper->generateCoverLetter($cvData, 'CompanyX', 'Engineer', '');

    assert_has_keys($result, ['cover_letter', 'word_count', 'generated_at'], 'Result has expected keys');
    assert_not_eq('', $result['cover_letter'], 'cover_letter is not empty even with empty CV');
    assert_contains($result['cover_letter'], 'CompanyX', 'Still mentions company');
    assert_contains($result['cover_letter'], 'Engineer', 'Still mentions job title');
}

function testGenerateCoverLetterCustomAiResponse(): void
{
    echo "\n== Test: generateCoverLetter() -- Custom AI Response Content ==\n";

    $customLetter = "This is a custom AI-generated cover letter for the test.";
    $mockAgent = new MockAgentClient($customLetter);
    $helper = createTestHelper($mockAgent);
    $cvData = sampleCvData();

    $result = $helper->generateCoverLetter($cvData, 'TestCorp', 'QA Tester', 'Test job description');

    assert_eq($customLetter, $result['cover_letter'], 'cover_letter matches custom mock response');
    assert_eq(str_word_count($customLetter), $result['word_count'], 'word_count matches custom response');
}

function testBuildCoverLetterPrompt(): void
{
    echo "\n== Test: buildCoverLetterPrompt() -- Prompt Content ==\n";

    $helper = createTestHelper();
    $cvData = sampleCvData();

    $prompt = invokePrivateMethod($helper, 'buildCoverLetterPrompt', [
        $cvData,
        'Acme Corp',
        'Senior Developer',
        'Looking for an experienced developer...',
    ]);

    assert_is_string($prompt, 'Prompt is a string');
    assert_not_eq('', $prompt, 'Prompt is not empty');
    assert_contains($prompt, 'Acme Corp', 'Prompt contains company name');
    assert_contains($prompt, 'Senior Developer', 'Prompt contains job title');
    assert_contains($prompt, 'Senior full-stack developer', 'Prompt contains summary');
    assert_contains($prompt, 'PHP, JavaScript, MySQL', 'Prompt contains skills list');
    assert_contains($prompt, 'TechStart Inc.', 'Prompt contains experience');
}

// ─── Test Runner ────────────────────────────────────────────────────────────

echo "========================================================\n";
echo "CvAiHelper::generateCoverLetter() -- Unit Tests\n";
echo "========================================================\n";

testGenerateCoverLetterAiPath();
testGenerateCoverLetterLocalFallback();
testLocalCoverLetterDirect();
testGenerateCoverLetterEmptyCv();
testGenerateCoverLetterCustomAiResponse();
testBuildCoverLetterPrompt();

echo "\n========================================================\n";
$total = $testsPassed + $testsFailed;
echo "  Total: {$total}  |  Passed: {$testsPassed}  |  Failed: {$testsFailed}\n";
echo "========================================================\n";

exit($testsFailed > 0 ? 1 : 0);
