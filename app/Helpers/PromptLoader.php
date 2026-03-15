<?php

/**
 * PromptLoader
 *
 * Provides structured prompt loading for the AI assistant.
 *
 * Priority:
 * 1) system/prompts/{context}.yaml (e.g. admin.yaml, public.yaml)
 * 2) system/prompts/prompts.yaml
 * 3) Database settings (admin_system_prompt / public_system_prompt)
 */

// Polyfill for `yaml_parse` when the YAML extension is not installed.
// This avoids runtime crashes and IDE/static analysis complaints.
if (!function_exists('yaml_parse')) {
    function yaml_parse(string $input): array
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return [];
        }

        // If it looks like JSON, try decoding it.
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $result = [];
        $lines = preg_split('/\r?\n/', $trimmed);
        $currentKey = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Match key: value
            if (preg_match('/^([a-zA-Z0-9_\-]+):\s*(.*)$/', $line, $matches)) {
                $currentKey = $matches[1];
                $value = $matches[2];

                // Interpret empty value as empty string (not null)
                if ($value === '') {
                    $result[$currentKey] = '';
<<<<<<< HEAD
                }
                else {
=======
                } else {
>>>>>>> temp_branch
                    $result[$currentKey] = $value;
                }
                continue;
            }

            // Match list items under current key
            if ($currentKey !== null && preg_match('/^[-*]\s+(.*)$/', $line, $matches)) {
                if (!isset($result[$currentKey]) || !is_array($result[$currentKey])) {
                    $result[$currentKey] = [];
                }
                $result[$currentKey][] = $matches[1];
            }
        }

        return $result;
    }
}

class PromptLoader
{
    private static function loadStructuredFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = trim((string)file_get_contents($path));
        if ($content === '') {
            return [];
        }

        // Try YAML parsing first (if extension available)
        if (function_exists('yaml_parse')) {
            try {
                $data = @yaml_parse($content);
                if (is_array($data)) {
                    return $data;
                }
<<<<<<< HEAD
            }
            catch (Throwable $e) {
            // ignore
=======
            } catch (Throwable $e) {
                // ignore
>>>>>>> temp_branch
            }
        }

        // Fallback to JSON
        $json = json_decode($content, true);
        if (is_array($json)) {
            return $json;
        }

        // If file is plain text, treat as system_prompt
        return ['system_prompt' => $content];
    }

    public static function loadPrompts(string $context = 'public', ?mysqli $mysqli = null): array
    {
        $prompts = [];

        $baseFolder = __DIR__ . '/../../system/prompts';

        // 1) context-specific file (admin/public)
        $contextFile = $baseFolder . '/' . $context . '.md';
        if (file_exists($contextFile)) {
            $prompts = self::loadStructuredFile($contextFile);
        }

        // 2) fallback common prompts file
        if (empty($prompts)) {
            $commonFile = $baseFolder . '/prompts.yaml';
            $prompts = self::loadStructuredFile($commonFile);
        }

        // 3) fallback to DB setting if still empty or missing system_prompt
        if ((!isset($prompts['system_prompt']) || trim((string)$prompts['system_prompt']) === '') && $mysqli) {
            require_once __DIR__ . '/../Models/AIProvider.php';
            $aiProvider = new AIProvider($mysqli);
            if ($context === 'admin') {
                $prompts['system_prompt'] = $aiProvider->getSetting(
                    'admin_system_prompt',
                    'You are a helpful AI assistant for BroxBhai admin panel that can help with content management, user management, analytics, and website administration tasks.'
                );
<<<<<<< HEAD
            }
            else {
=======
            } else {
>>>>>>> temp_branch
                $prompts['system_prompt'] = $aiProvider->getSetting(
                    'public_system_prompt',
                    'You are a helpful AI assistant for BroxBhai website visitors. You can answer questions about the website content, services, and provide general information.'
                );
            }
        }

        // Ensure string values
        foreach ($prompts as $key => $val) {
            if (!is_string($val))
                continue;
            $prompts[$key] = trim($val);
        }

        return $prompts;
    }

    public static function getSystemPrompt(string $context = 'public', ?mysqli $mysqli = null): string
    {
        $prompts = self::loadPrompts($context, $mysqli);
        $system = trim((string)($prompts['system_prompt'] ?? ''));

        if ($context === 'admin' && $system) {
<<<<<<< HEAD
            $system .= "\n\nWhen providing answers, feel free to refer to admin URLs and tools available in the dashboard.";
=======
            $system .= "\n\n[ADMIN COPILOT INSTRUCTIONS]";
            $system .= "\n1. Use standard Markdown for formatting.";
            $system .= "\n2. For data summaries, tables, or complex lists, use the ARTIFACT format:";
            $system .= "\n   ```artifact\n   {\n     \"title\": \"Data Title\",\n     \"type\": \"table\",\n     \"headers\": [\"Col1\", \"Col2\"],\n     \"rows\": [[\"Val1\", \"Val2\"]]\n   }\n   ```";
            $system .= "\n3. Be context-aware. Use the provided [USER CONTEXT] to tailor your response to the current page.";
            $system .= "\n4. Supported slash commands (admin only): /summarize, /analyze-logs.";
            $system .= "\n5. Refer to admin URLs and tools available in the dashboard when relevant.";
>>>>>>> temp_branch
        }

        return $system;
    }

<<<<<<< HEAD
=======
    /**
     * Fetch relevant knowledge base snippets based on a user query.
     * Returns a concatenated string (may be empty) to append to the system prompt.
     */
    public static function getKnowledgeContext(string $query, ?mysqli $mysqli = null, int $limit = 3): string
    {
        if (!$mysqli || trim($query) === '') {
            return '';
        }

        // First check if the table exists
        $tableCheck = $mysqli->query("SHOW TABLES LIKE 'ai_knowledge_base'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return '';
        }

        // Check if is_active column exists (schema version check)
        $columnCheck = $mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'is_active'");
        if (!$columnCheck || $columnCheck->num_rows === 0) {
            return '';
        }

        // Basic keyword extraction: words longer than 3 chars
        $words = preg_split('/\W+/', $query);
        $keywords = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) >= 4) {
                $keywords[] = $mysqli->real_escape_string($w);
            }
        }

        if (empty($keywords)) {
            return '';
        }

        // Build a simple LIKE-based query across title and content
        $whereParts = [];
        foreach ($keywords as $kw) {
            $kwLike = "%{$kw}%";
            $whereParts[] = "(`title` LIKE '" . $kwLike . "' OR `content` LIKE '" . $kwLike . "')";
        }

        $whereSql = implode(' OR ', $whereParts);
        $sql = "SELECT `title`,`content`, `category` FROM `ai_knowledge_base` WHERE ({$whereSql}) AND is_active = 1 ORDER BY priority DESC, `created_at` DESC LIMIT " . intval($limit);

        $res = $mysqli->query($sql);
        if (!$res) {
            return '';
        }

        $snippets = [];
        while ($row = $res->fetch_assoc()) {
            $title = trim($row['title'] ?? '');
            $content = trim($row['content'] ?? '');
            $category = trim($row['category'] ?? '');
            // Keep short preview (first 800 chars)
            $preview = mb_substr(preg_replace('/\s+/', ' ', strip_tags($content)), 0, 800);
            if ($title) {
                $catLabel = $category ? " [{$category}]" : "";
                $snippets[] = "- " . $title . $catLabel . ": " . $preview;
            } else {
                $snippets[] = $preview;
            }
        }

        if (empty($snippets)) {
            return '';
        }

        $out = "[KNOWLEDGE BASE CONTEXT - Priority Ordered]\n" . implode("\n", $snippets);
        return $out;
    }

>>>>>>> temp_branch
    public static function parseResponseConfig(string $text): array
    {
        $result = ['config' => null, 'content' => $text];
        $trimmed = ltrim($text);
        if (!str_starts_with($trimmed, '---')) {
            return $result;
        }

        // Split into lines and find closing delimiter
        $lines = preg_split('/\r?\n/', $trimmed);
        if (!$lines || $lines[0] !== '---') {
            return $result;
        }

        $headerLines = [];
        $i = 1;
        for (; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '---') {
                $i++;
                break;
            }
            $headerLines[] = $lines[$i];
        }

        if (empty($headerLines)) {
            return $result;
        }

        // Try YAML parser first
        $config = null;
        if (function_exists('yaml_parse')) {
            try {
                $yamlText = implode("\n", $headerLines);
                $parsed = @call_user_func('yaml_parse', $yamlText);
                if (is_array($parsed)) {
                    $config = $parsed;
                }
<<<<<<< HEAD
            }
            catch (Throwable $e) {
            // ignore
=======
            } catch (Throwable $e) {
                // ignore
>>>>>>> temp_branch
            }
        }

        // Fallback to basic parsing
        if ($config === null) {
            $config = [];
            $currentKey = null;
            foreach ($headerLines as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                    continue;
                }

                if (preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $trimmedLine, $matches)) {
                    $currentKey = $matches[1];
                    $value = $matches[2];
                    if ($value === '') {
                        $config[$currentKey] = [];
<<<<<<< HEAD
                    }
                    else {
=======
                    } else {
>>>>>>> temp_branch
                        $config[$currentKey] = $value;
                    }
                    continue;
                }

                if ($currentKey && preg_match('/^[-*]\s+(.*)$/', $trimmedLine, $matches)) {
                    if (!isset($config[$currentKey]) || !is_array($config[$currentKey])) {
                        $config[$currentKey] = [$config[$currentKey] ?? null];
                    }
                    $config[$currentKey][] = $matches[1];
                }
            }
        }

        $result['config'] = $config;
        $result['content'] = trim(implode("\n", array_slice($lines, $i)));
        return $result;
    }
<<<<<<< HEAD
=======

    /**
     * Load AI skills configuration
     */
    public static function loadAISkills(): array
    {
        $skillsFile = __DIR__ . '/../../system/prompts/ai-skills.json';
        if (!file_exists($skillsFile)) {
            return [];
        }
        $content = file_get_contents($skillsFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Load AI tools configuration
     */
    public static function loadAITools(): array
    {
        $toolsFile = __DIR__ . '/../../system/prompts/ai-tools.json';
        if (!file_exists($toolsFile)) {
            return [];
        }
        $content = file_get_contents($toolsFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get available skills for a given context
     */
    public static function getSkillsForContext(string $context = 'public'): array
    {
        $skills = self::loadAISkills();
        if (empty($skills['skills'])) {
            return [];
        }

        $enabledSkills = [];
        foreach ($skills['skills'] as $skill) {
            if (!empty($skill['enabled'])) {
                $enabledSkills[] = $skill;
            }
        }

        // Sort by priority
        usort($enabledSkills, function ($a, $b) {
            return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
        });

        return $enabledSkills;
    }

    /**
     * Get available tools for user role
     */
    public static function getToolsForRole(string $role = 'public'): array
    {
        $tools = self::loadAITools();
        if (empty($tools['tools']) || empty($tools['tool_permissions'][$role])) {
            return [];
        }

        $allowedToolIds = $tools['tool_permissions'][$role];
        $allowedTools = [];

        foreach ($tools['tools'] as $tool) {
            if (in_array($tool['id'], $allowedToolIds) && !empty($tool['enabled'])) {
                $allowedTools[] = $tool;
            }
        }

        return $allowedTools;
    }

    /**
     * Load site settings from database
     */
    public static function loadSiteSettings(?mysqli $mysqli = null): array
    {
        if (!$mysqli) {
            return self::getDefaultSiteSettings();
        }

        $result = $mysqli->query("SELECT * FROM app_settings LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return self::getDefaultSiteSettings();
        }

        $settings = $result->fetch_assoc();
        return [
            'site_name' => $settings['site_name'] ?? 'BroxBhai',
            'site_url' => self::getSiteUrl($settings),
            'site_logo' => $settings['site_logo'] ?? '',
            'contact_email' => $settings['contact_email'] ?? '',
            'contact_phone' => $settings['contact_phone'] ?? '',
            'contact_address' => $settings['contact_address'] ?? '',
            'meta_title' => $settings['meta_title'] ?? '',
            'meta_description' => $settings['meta_description'] ?? '',
            'default_language' => $settings['default_language'] ?? 'en',
            'timezone' => $settings['timezone'] ?? 'Asia/Dhaka',
            'social_facebook' => $settings['social_facebook'] ?? '',
            'social_twitter' => $settings['social_twitter'] ?? '',
            'social_instagram' => $settings['social_instagram'] ?? '',
            'social_youtube' => $settings['social_youtube'] ?? ''
        ];
    }

    /**
     * Get default site settings
     */
    private static function getDefaultSiteSettings(): array
    {
        return [
            'site_name' => 'BroxBhai',
            'site_url' => 'https://broxlab.online',
            'site_logo' => '',
            'contact_email' => 'info@broxlab.online',
            'contact_phone' => '',
            'contact_address' => '',
            'meta_title' => 'BroxBhai - Bengali Tech Platform',
            'meta_description' => 'A Bengali-first tech platform',
            'default_language' => 'bn',
            'timezone' => 'Asia/Dhaka',
            'social_facebook' => '',
            'social_twitter' => '',
            'social_instagram' => '',
            'social_youtube' => ''
        ];
    }

    /**
     * Get site URL from settings or use default
     */
    private static function getSiteUrl(array $settings): string
    {
        // Check if there's a configured URL
        if (!empty($settings['site_url'])) {
            return $settings['site_url'];
        }

        // Try to determine from request if available
        if (!empty($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $_SERVER['HTTP_HOST'];
        }

        return 'https://broxlab.online';
    }

    /**
     * Inject site settings into prompt text
     * Replaces placeholders like {{site_name}}, {{site_url}} with actual values
     */
    public static function injectSiteSettings(string $prompt, ?mysqli $mysqli = null): string
    {
        $settings = self::loadSiteSettings($mysqli);

        // Replace placeholders
        $prompt = str_replace('{{site_name}}', $settings['site_name'], $prompt);
        $prompt = str_replace('{{site_url}}', $settings['site_url'], $prompt);
        $prompt = str_replace('{{site_logo}}', $settings['site_logo'], $prompt);
        $prompt = str_replace('{{contact_email}}', $settings['contact_email'], $prompt);
        $prompt = str_replace('{{contact_phone}}', $settings['contact_phone'], $prompt);
        $prompt = str_replace('{{contact_address}}', $settings['contact_address'], $prompt);
        $prompt = str_replace('{{meta_title}}', $settings['meta_title'], $prompt);
        $prompt = str_replace('{{meta_description}}', $settings['meta_description'], $prompt);
        $prompt = str_replace('{{default_language}}', $settings['default_language'], $prompt);
        $prompt = str_replace('{{timezone}}', $settings['timezone'], $prompt);
        $prompt = str_replace('{{social_facebook}}', $settings['social_facebook'], $prompt);
        $prompt = str_replace('{{social_twitter}}', $settings['social_twitter'], $prompt);
        $prompt = str_replace('{{social_instagram}}', $settings['social_instagram'], $prompt);
        $prompt = str_replace('{{social_youtube}}', $settings['social_youtube'], $prompt);

        return $prompt;
    }

    /**
     * Get system prompt with site settings injected
     * This is the main method to call for getting prompts
     */
    public static function getPromptWithSettings(string $context = 'public', ?mysqli $mysqli = null): string
    {
        $prompt = self::getSystemPrompt($context, $mysqli);
        return self::injectSiteSettings($prompt, $mysqli);
    }
>>>>>>> temp_branch
}
