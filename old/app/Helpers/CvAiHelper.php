<?php

// app/Helpers/CvAiHelper.php

require_once dirname(__DIR__, 1) . '/Modules/AISystem/AgentClient.php';

class CvAiHelper
{
    private $agentClient;
    private $mysqli;

    /**
     * @param mysqli $mysqli Database connection
     */
    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
        $this->agentClient = new AgentClient($mysqli);
    }

    /**
     * Improve text using AI
     * @param string $text The text to improve
     * @param string $type Type of text (bullet, sentence, paragraph)
     * @return array Result with improved text and score
     */
    public function improveText(string $text, string $type = 'bullet'): array
    {
        $prompt = $this->buildImprovePrompt($text, $type);

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);

            return [
                'improved' => $response,
                'score' => $this->calculateImprovementScore($response)
            ];
        } catch (Exception $e) {
            // Fallback to local improvement if AI fails
            return $this->localImprove($text, $type);
        }
    }

    /**
     * Calculate ATS score for CV
     * @param array $cvData The CV data to analyze
     * @return array ATS score analysis
     */
    public function calculateAtsScore(array $cvData): array
    {
        $prompt = $this->buildAtsPrompt($cvData);

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);

            // Parse the response to extract score and suggestions
            return $this->parseAtsResponse($response, $cvData);
        } catch (Exception $e) {
            // Fallback to local analysis if AI fails
            return $this->localAtsAnalysis($cvData);
        }
    }

    /**
     * Extract keywords from job description
     * @param string $jobDescription The job description text
     * @return array Extracted keywords with importance scores
     */
    public function extractKeywords(string $jobDescription): array
    {
        $prompt = $this->buildKeywordPrompt($jobDescription);

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);

            return $this->parseKeywordResponse($response);
        } catch (Exception $e) {
            // Fallback to local extraction
            return $this->localKeywordExtraction($jobDescription);
        }
    }

    /**
     * Parse CV from file (PDF/DOCX)
     * @param string $filePath Path to the uploaded file
     * @return array Parsed CV data
     */
    public function parseCvFile(string $filePath): array
    {
        $text = $this->extractTextFromFile($filePath);

        if (empty($text)) {
            return $this->localCvParsing($text);
        }

        $prompt = $this->buildParsePrompt($text);

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);

            return $this->parseCvResponse($response);
        } catch (Exception $e) {
            // Fallback to local parsing
            return $this->localCvParsing($text);
        }
    }

    /**
     * Generate a cover letter based on CV data and job details
     * @param array $cvData The CV data (summary, experience, education, skills)
     * @param string $companyName Target company name
     * @param string $jobTitle Target job title
     * @param string $jobDescription Optional job description
     * @return array Result with cover_letter text
     */
    public function generateCoverLetter(array $cvData, string $companyName, string $jobTitle, string $jobDescription = ''): array
    {
        $prompt = $this->buildCoverLetterPrompt($cvData, $companyName, $jobTitle, $jobDescription);

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);

            return [
                'cover_letter' => $response,
                'word_count' => str_word_count($response),
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            // Fallback to local generation
            return $this->localCoverLetter($cvData, $companyName, $jobTitle, $jobDescription);
        }
    }

    // ========== PROMPT BUILDERS ==========

    private function buildImprovePrompt(string $text, string $type): string
    {
        $systemPrompt = "You are a professional CV writer and career coach. Your task is to improve CV bullet points to make them more impactful, quantified, and ATS-friendly.";

        $userPrompt = "Improve the following {$type} point for a CV. Make it more:
1. Action-oriented (start with strong action verbs)
2. Quantified (include numbers/metrics where possible)
3. ATS-friendly (use relevant keywords)

Original text: {$text}

Provide only the improved text without any explanation.";

        return $systemPrompt . "\n\n" . $userPrompt;
    }

    private function buildCoverLetterPrompt(array $cvData, string $companyName, string $jobTitle, string $jobDescription): string
    {
        $systemPrompt = <<<EOT
You are a professional cover letter writer and career coach. Your task is to write a compelling, personalized cover letter.

The cover letter should:
1. Be addressed to the hiring manager
2. Open with a strong hook about the applicant's enthusiasm for the role
3. Highlight 2-3 key achievements from the CV that are relevant to the target role
4. Connect the applicant's skills to the company's needs
5. Close with a call to action for an interview
6. Be professional but warm in tone
7. Be 3-4 paragraphs (250-400 words)

Write only the body of the letter without any meta-commentary, subject lines, or salutation instructions.
EOT;

        $cvSummary = !empty($cvData['summary']) ? $cvData['summary'] : 'Not provided';

        $experienceText = '';
        if (!empty($cvData['experience'])) {
            $expItems = [];
            foreach ($cvData['experience'] as $exp) {
                $title = $exp['position'] ?? $exp['title'] ?? 'Role';
                $company = $exp['company'] ?? '';
                $desc = $exp['description'] ?? '';
                $expItems[] = "- {$title} at {$company}: {$desc}";
            }
            $experienceText = implode("\n", $expItems);
        }

        $skillsText = '';
        if (!empty($cvData['skills'])) {
            $skillsText = implode(', ', array_map(function ($s) {
                return is_string($s) ? $s : ($s['name'] ?? '');
            }, $cvData['skills']));
        }

        $userPrompt = <<<EOT
Write a personalized cover letter for the following:

TARGET COMPANY: {$companyName}
TARGET POSITION: {$jobTitle}

JOB DESCRIPTION (if available):
{$jobDescription}

APPLICANT CV DATA:
Summary: {$cvSummary}

Experience:
{$experienceText}

Skills: {$skillsText}

---
Write a compelling cover letter tailored to this specific role and company. Use the applicant's actual experience to demonstrate fit.
EOT;

        return $systemPrompt . "\n\n" . $userPrompt;
    }

    private function buildAtsPrompt(array $cvData): string
    {
        $systemPrompt = "You are an ATS (Applicant Tracking System) expert. Analyze the CV and provide:";

        $userPrompt = "Analyze this CV for ATS compatibility. Provide:
1. A score from 0-100
2. List of keywords found
3. Missing keywords that should be added
4. Suggestions for improvement

CV Data:
" . json_encode($cvData, JSON_PRETTY_PRINT) . "

Respond in JSON format:
{
  \"score\": number,
  \"found_keywords\": [\"keyword1\", \"keyword2\"],
  \"missing_keywords\": [\"keyword1\", \"keyword2\"],
  \"suggestions\": [\"suggestion1\", \"suggestion2\"]
}";

        return $systemPrompt . "\n\n" . $userPrompt;
    }

    private function buildKeywordPrompt(string $jobDescription): string
    {
        $systemPrompt = "You are a job market expert specializing in resume optimization.";

        $userPrompt = "Extract the most important keywords and skills from this job description. Focus on:
1. Technical skills
2. Soft skills
3. Required qualifications
4. Action verbs

Job Description:
{$jobDescription}

Provide the top 10-15 keywords in order of importance as a comma-separated list.";

        return $systemPrompt . "\n\n" . $userPrompt;
    }

    private function buildParsePrompt(string $text): string
    {
        $systemPrompt = "You are a CV parsing expert. Extract structured information from CV/resume text.";

        $userPrompt = "Extract the following information from this CV text and respond in JSON format:
{
  \"name\": \"full name\",
  \"email\": \"email address\",
  \"phone\": \"phone number\",
  \"summary\": \"professional summary\",
  \"experience\": [
    {
      \"company\": \"company name\",
      \"position\": \"job title\",
      \"start_date\": \"YYYY-MM\",
      \"end_date\": \"YYYY-MM or Present\",
      \"description\": \"job description\"
    }
  ],
  \"education\": [
    {
      \"institution\": \"school name\",
      \"degree\": \"degree name\",
      \"start_date\": \"YYYY-MM\",
      \"end_date\": \"YYYY-MM\"
    }
  ],
  \"skills\": [\"skill1\", \"skill2\"]
}

CV Text:
{$text}

Provide only valid JSON without any markdown formatting.";

        return $systemPrompt . "\n\n" . $userPrompt;
    }

    // ========== RESPONSE PARSERS ==========

    private function parseAtsResponse(string $response, array $cvData): array
    {
        // Try to extract JSON from response
        preg_match('/\{.*\}/s', $response, $matches);

        if (!empty($matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // Fallback to local analysis
        return $this->localAtsAnalysis($cvData);
    }

    private function parseKeywordResponse(string $response): array
    {
        $keywords = array_map('trim', explode(',', $response));

        $importance = [];
        foreach ($keywords as $index => $keyword) {
            $importance[$keyword] = 10 - $index;
        }

        return [
            'keywords' => $keywords,
            'importance' => $importance
        ];
    }

    private function parseCvResponse(string $response): array
    {
        preg_match('/\{.*\}/s', $response, $matches);

        if (!empty($matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'summary' => '',
            'experience' => [],
            'education' => [],
            'skills' => []
        ];
    }

    // ========== LOCAL FALLBACKS ==========

    private function localImprove(string $text, string $type): array
    {
        $improved = $text;

        // Add action verbs if missing
        $actionVerbs = ['Led', 'Developed', 'Created', 'Implemented', 'Managed', 'Optimized'];
        $hasActionVerb = false;

        foreach ($actionVerbs as $verb) {
            if (stripos($text, $verb) === 0) {
                $hasActionVerb = true;
                break;
            }
        }

        if (!$hasActionVerb) {
            $improved = 'Achieved ' . strtolower($text);
        }

        // Quantify if possible
        if (!preg_match('/\d+/', $text)) {
            $improved .= ' resulting in measurable outcomes';
        }

        return [
            'improved' => $improved,
            'score' => $this->calculateImprovementScore($improved)
        ];
    }

    private function localCoverLetter(array $cvData, string $companyName, string $jobTitle, string $jobDescription): array
    {
        $name = $cvData['personal']['full_name'] ?? 'the applicant';
        $summary = $cvData['summary'] ?? '';

        $letter = <<<LETTER

Dear Hiring Manager,

I am writing to express my strong interest in the {$jobTitle} position at {$companyName}. As a professional with a proven track record of delivering results, I am confident that my skills and experience align perfectly with the requirements of this role.

{$summary}

Throughout my career, I have developed expertise in delivering high-quality outcomes while collaborating effectively with cross-functional teams. I am particularly drawn to {$companyName} because of its reputation for innovation and excellence in the industry.

I would welcome the opportunity to discuss how my background and skills can contribute to the continued success of {$companyName}. Thank you for considering my application.

Sincerely,
{$name}
LETTER;

        return [
            'cover_letter' => $letter,
            'word_count' => str_word_count($letter),
            'generated_at' => date('c')
        ];
    }

    private function localAtsAnalysis(array $cvData): array
    {
        $score = 50;
        $suggestions = [];
        $foundKeywords = [];
        $missingKeywords = [];

        // Check summary
        if (!empty($cvData['summary']) && strlen($cvData['summary']) > 50) {
            $score += 10;
        } else {
            $suggestions[] = 'Expand your professional summary';
        }

        // Check experience
        if (!empty($cvData['experience'])) {
            $score += 15;
            $foundKeywords = array_merge($foundKeywords, ['Experience', 'Manager']);
        } else {
            $suggestions[] = 'Add work experience';
        }

        // Check education
        if (!empty($cvData['education'])) {
            $score += 10;
        } else {
            $suggestions[] = 'Add education details';
        }

        // Check skills
        if (!empty($cvData['skills'])) {
            $score += 15;
            $foundKeywords = array_merge($foundKeywords, $cvData['skills']);
        } else {
            $suggestions[] = 'Add skills';
        }

        // Common missing keywords
        $commonSkills = ['PHP', 'JavaScript', 'MySQL', 'AWS', 'Docker'];
        foreach ($commonSkills as $skill) {
            if (empty($cvData['skills']) || !in_array($skill, $cvData['skills'])) {
                $missingKeywords[] = $skill;
            }
        }

        return [
            'score' => min(100, $score),
            'feedback' => [
                'keywords' => [
                    'found' => $foundKeywords,
                    'missing' => $missingKeywords
                ],
                'readability' => $score > 70 ? 'Good' : 'Needs improvement',
                'sections' => [
                    'complete' => $score > 60,
                    'missing' => $suggestions
                ]
            ],
            'suggestions' => $suggestions
        ];
    }

    private function localKeywordExtraction(string $text): array
    {
        $splitWords = preg_split('/\W+/', $text);
        $words = is_array($splitWords) ? array_map('strtolower', $splitWords) : [];
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        $wordCount = [];
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $wordCount[$word] = ($wordCount[$word] ?? 0) + 1;
            }
        }

        arsort($wordCount);
        $keywords = array_slice(array_keys($wordCount), 0, 15);

        $importance = [];
        foreach ($keywords as $index => $keyword) {
            $importance[$keyword] = 10 - $index;
        }

        return [
            'keywords' => $keywords,
            'importance' => $importance
        ];
    }

    private function localCvParsing(string $text): array
    {
        $data = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'summary' => '',
            'experience' => [],
            'education' => [],
            'skills' => []
        ];

        // Extract email
        if (preg_match('/[\w.-]+@[\w.-]+\.\w+/', $text, $matches)) {
            $data['email'] = $matches[0];
        }

        // Extract phone
        if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
            $data['phone'] = $matches[0];
        }

        // First non-empty line is likely the name
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        if (!empty($lines)) {
            $data['name'] = reset($lines);
        }

        return $data;
    }

    private function extractTextFromFile(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            // Use pdf-parse via shell or PHP extension
            // For now, return empty
            return '';
        } elseif ($ext === 'docx') {
            // Use mammoth or PHP extension
            return '';
        }

        return file_get_contents($filePath);
    }

    private function calculateImprovementScore(string $text): int
    {
        $score = 50;

        // Action verbs
        $actionVerbs = ['led', 'developed', 'created', 'implemented', 'managed', 'optimized', 'achieved'];
        if (preg_match('/^(' . implode('|', $actionVerbs) . ')/i', $text)) {
            $score += 15;
        }

        // Numbers
        if (preg_match('/\d+/', $text)) {
            $score += 15;
        }

        // Impact words
        $impactWords = ['resulted', 'increased', 'decreased', 'saved', 'improved'];
        foreach ($impactWords as $word) {
            if (stripos($text, $word) !== false) {
                $score += 10;
                break;
            }
        }

        // Length
        if (strlen($text) > 30 && strlen($text) < 100) {
            $score += 10;
        }

        return min(100, $score);
    }
}
