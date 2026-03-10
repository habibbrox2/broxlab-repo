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
                }
                else {
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
            }
            catch (Throwable $e) {
            // ignore
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
            }
            else {
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
            $system .= "\n\n[ADMIN COPILOT INSTRUCTIONS]";
            $system .= "\n1. Use standard Markdown for formatting.";
            $system .= "\n2. For data summaries, tables, or complex lists, use the ARTIFACT format:";
            $system .= "\n   ```artifact\n   {\n     \"title\": \"Data Title\",\n     \"type\": \"table\",\n     \"headers\": [\"Col1\", \"Col2\"],\n     \"rows\": [[\"Val1\", \"Val2\"]]\n   }\n   ```";
            $system .= "\n3. Be context-aware. Use the provided [USER CONTEXT] to tailor your response to the current page.";
            $system .= "\n4. Supported slash commands (if mentioned by user): /generate, /summarize, /analyze.";
            $system .= "\n5. Refer to admin URLs and tools available in the dashboard when relevant.";
        }

        return $system;
    }

    /**
     * Fetch relevant knowledge base snippets based on a user query.
     * Returns a concatenated string (may be empty) to append to the system prompt.
     */
    public static function getKnowledgeContext(string $query, ?mysqli $mysqli = null, int $limit = 3): string
    {
        if (!$mysqli || trim($query) === '') {
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
        $sql = "SELECT `title`,`content` FROM `ai_knowledge_base` WHERE ({$whereSql}) ORDER BY `created_at` DESC LIMIT " . intval($limit);

        $res = $mysqli->query($sql);
        if (!$res) {
            return '';
        }

        $snippets = [];
        while ($row = $res->fetch_assoc()) {
            $title = trim($row['title'] ?? '');
            $content = trim($row['content'] ?? '');
            // Keep short preview (first 800 chars)
            $preview = mb_substr(preg_replace('/\s+/', ' ', strip_tags($content)), 0, 800);
            if ($title) {
                $snippets[] = "- " . $title . ": " . $preview;
            } else {
                $snippets[] = $preview;
            }
        }

        if (empty($snippets)) {
            return '';
        }

        $out = "[KNOWLEDGE BASE CONTEXT]\n" . implode("\n", $snippets);
        return $out;
    }

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
            }
            catch (Throwable $e) {
            // ignore
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
                    }
                    else {
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
}
