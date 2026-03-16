<?php

/**
 * ReActAgent - Reasoning + Acting Agent Implementation
 * 
 * Implements the ReAct (Reasoning + Acting) paradigm for autonomous
 * multi-step problem solving with tool use capabilities.
 */

require_once __DIR__ . '/../../Models/AIKnowledge.php';
require_once __DIR__ . '/RAGEngine.php';

class ReActAgent
{
    private $mysqli;
    private $ragEngine;
    private $maxIterations;
    private $maxTokens;

    // Tool definitions
    private array $tools = [];

    public function __construct(mysqli $mysqli, int $maxIterations = 5, int $maxTokens = 4000)
    {
        $this->mysqli = $mysqli;
        $this->ragEngine = new RAGEngine($mysqli);
        $this->maxIterations = $maxIterations;
        $this->maxTokens = $maxTokens;

        $this->registerTools();
    }

    /**
     * Register available tools for the agent
     */
    private function registerTools(): void
    {
        $this->tools = [
            'search_knowledge' => [
                'name' => 'search_knowledge',
                'description' => 'Search the AI Knowledge Base for relevant information. Use this to find answers from documented knowledge.',
                'parameters' => [
                    'query' => ['type' => 'string', 'description' => 'The search query']
                ]
            ],
            'get_analytics' => [
                'name' => 'get_analytics',
                'description' => 'Get analytics data including visitor stats, page views, and user metrics.',
                'parameters' => []
            ],
            'check_logs' => [
                'name' => 'check_logs',
                'description' => 'Check system error logs for recent errors and warnings.',
                'parameters' => [
                    'limit' => ['type' => 'integer', 'description' => 'Number of log entries to retrieve', 'default' => 10]
                ]
            ],
            'list_users' => [
                'name' => 'list_users',
                'description' => 'List recent users or search for specific users.',
                'parameters' => [
                    'limit' => ['type' => 'integer', 'description' => 'Number of users to list', 'default' => 10],
                    'role' => ['type' => 'string', 'description' => 'Filter by role (admin, user, etc.)']
                ]
            ],
            'send_notification' => [
                'name' => 'send_notification',
                'description' => 'Send a notification to users or admin.',
                'parameters' => [
                    'message' => ['type' => 'string', 'description' => 'The notification message'],
                    'type' => ['type' => 'string', 'description' => 'Type: info, success, warning, error']
                ]
            ],
            'finish' => [
                'name' => 'finish',
                'description' => 'Finish the task and provide the final answer. Always call this when the task is complete.',
                'parameters' => [
                    'answer' => ['type' => 'string', 'description' => 'The final answer to provide to the user']
                ]
            ]
        ];
    }

    /**
     * Execute the ReAct agent loop
     * 
     * @param string $task The task/question from the user
     * @param callable $aiCallback Function to call for AI responses (model API)
     * @return array Result with answer and trace
     */
    public function execute(string $task, callable $aiCallback): array
    {
        $trace = [];
        $context = [];
        $iteration = 0;

        // Initial thought
        $trace[] = [
            'step' => 0,
            'thought' => "Task: {$task}",
            'action' => null,
            'observation' => null
        ];

        while ($iteration < $this->maxIterations) {
            $iteration++;

            // Build prompt for this iteration
            $prompt = $this->buildIterationPrompt($task, $context, $trace);

            // Get AI response
            $response = $aiCallback($prompt);

            // Parse response to extract thought, action, action_input
            $parsed = $this->parseResponse($response);

            $thought = $parsed['thought'] ?? '';
            $action = $parsed['action'] ?? '';
            $actionInput = $parsed['action_input'] ?? '';

            $trace[] = [
                'step' => $iteration,
                'thought' => $thought,
                'action' => $action,
                'action_input' => $actionInput,
                'observation' => null
            ];

            // Execute action if not finish
            if ($action === 'finish') {
                $trace[$iteration]['observation'] = 'Task completed';
                break;
            }

            if ($action && isset($this->tools[$action])) {
                $observation = $this->executeTool($action, $actionInput);
                $trace[$iteration]['observation'] = $observation;
                $context[] = "Observation: {$observation}";
            } else if ($action) {
                $trace[$iteration]['observation'] = "Unknown action: {$action}";
                $context[] = "Observation: Unknown action - {$action}";
            } else {
                $trace[$iteration]['observation'] = 'No action specified';
            }

            // Check for completion
            if (
                strpos(strtolower($trace[$iteration]['observation'] ?? ''), 'task complete') !== false ||
                strpos(strtolower($thought ?? ''), 'final answer') !== false
            ) {
                break;
            }
        }

        // Extract final answer
        $finalAnswer = $this->extractFinalAnswer($trace);

        return [
            'success' => true,
            'answer' => $finalAnswer,
            'iterations' => $iteration,
            'trace' => $trace
        ];
    }

    /**
     * Build the prompt for each iteration
     */
    private function buildIterationPrompt(string $task, array $context, array $trace): string
    {
        $toolsDescription = $this->getToolsDescription();

        // Build trace history
        $history = "";
        foreach ($trace as $entry) {
            if ($entry['step'] === 0) {
                $history .= "Task: {$entry['thought']}\n\n";
            } else {
                $history .= "Thought {$entry['step']}: {$entry['thought']}\n";
                if ($entry['action']) {
                    $history .= "Action {$entry['step']}: {$entry['action']}";
                    if ($entry['action_input']) {
                        $history .= "[{$entry['action_input']}]";
                    }
                    $history .= "\n";
                }
                if ($entry['observation']) {
                    $history .= "Observation {$entry['step']}: {$entry['observation']}\n";
                }
                $history .= "\n";
            }
        }

        // Add context
        $contextStr = !empty($context) ? implode("\n", $context) . "\n\n" : "";

        $prompt = <<<PROMPT
You are a helpful AI assistant with access to tools to help answer user questions.

## Available Tools:
{$toolsDescription}

## Guidelines:
- Think step by step (Reasoning + Acting = ReAct)
- After each thought, decide if you need to use a tool or can provide the answer
- Always search the knowledge base first if the question might have a documented answer
- Use the 'finish' tool when you have the complete answer

## History:
{$history}
{$contextStr}

Now continue. What is your next thought and action? Use the format:
Thought: [your reasoning]
Action: [tool_name]
Action Input: [input for the tool]

PROMPT;

        return $prompt;
    }

    /**
     * Get tools description for the prompt
     */
    private function getToolsDescription(): string
    {
        $desc = [];
        foreach ($this->tools as $name => $tool) {
            $params = [];
            foreach ($tool['parameters'] as $param => $info) {
                $required = isset($info['default']) ? '(optional)' : '(required)';
                $params[] = "  - {$param}: {$info['description']} {$required}";
            }
            $desc[] = "### {$tool['name']}\n{$tool['description']}\n" . implode("\n", $params);
        }
        return implode("\n\n", $desc);
    }

    /**
     * Parse AI response to extract thought, action, and action_input
     */
    private function parseResponse(string $response): array
    {
        $result = [
            'thought' => '',
            'action' => '',
            'action_input' => ''
        ];

        // Extract thought
        if (preg_match('/Thought:?\s*(.+?)(?=\nAction:|$)/is', $response, $matches)) {
            $result['thought'] = trim($matches[1]);
        }

        // Extract action
        if (preg_match('/Action:?\s*(\w+)/is', $response, $matches)) {
            $result['action'] = trim($matches[1]);
        }

        // Extract action input
        if (preg_match('/Action Input:?\s*(.+?)(?=\n\n|Thought:|$)/is', $response, $matches)) {
            $result['action_input'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Execute a tool and return observation
     */
    private function executeTool(string $toolName, string $input): string
    {
        // Parse input (simple JSON or key=value format)
        $params = $this->parseInput($input);

        switch ($toolName) {
            case 'search_knowledge':
                return $this->toolSearchKnowledge($params['query'] ?? '');

            case 'get_analytics':
                return $this->toolGetAnalytics();

            case 'check_logs':
                return $this->toolCheckLogs($params['limit'] ?? 10);

            case 'list_users':
                return $this->toolListUsers($params['limit'] ?? 10, $params['role'] ?? null);

            case 'send_notification':
                return $this->toolSendNotification($params['message'] ?? '', $params['type'] ?? 'info');

            case 'finish':
                return "Task complete: " . ($params['answer'] ?? 'No answer provided');

            default:
                return "Unknown tool: {$toolName}";
        }
    }

    /**
     * Parse tool input
     */
    private function parseInput(string $input): array
    {
        // Try JSON first
        $json = json_decode($input, true);
        if ($json && is_array($json)) {
            return $json;
        }

        // Try key=value format
        $params = [];
        if (preg_match_all('/(\w+)=([^\n]+)/', $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[$match[1]] = trim($match[2]);
            }
        }

        return $params;
    }

    /**
     * Tool: Search Knowledge Base
     */
    private function toolSearchKnowledge(string $query): string
    {
        if (empty($query)) {
            return "Error: No query provided for knowledge search";
        }

        $results = $this->ragEngine->retrieve($query, ['limit' => 3]);

        if (empty($results)) {
            return "No relevant information found in knowledge base for: {$query}";
        }

        $output = "Found " . count($results) . " relevant articles:\n";
        foreach ($results as $i => $r) {
            $output .= "- {$r['title']}: " . substr($r['content'] ?? '', 0, 200) . "...\n";
        }

        return $output;
    }

    /**
     * Tool: Get Analytics
     */
    private function toolGetAnalytics(): string
    {
        try {
            require_once __DIR__ . '/../../Models/AnalyticsModel.php';
            $model = new AnalyticsModel($this->mysqli);

            $summary = $model->getSummary();

            if ($summary) {
                return json_encode($summary, JSON_PRETTY_PRINT);
            }

            return "No analytics data available";
        } catch (Exception $e) {
            return "Error fetching analytics: " . $e->getMessage();
        }
    }

    /**
     * Tool: Check Logs
     */
    private function toolCheckLogs(int $limit = 10): string
    {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/errors.log';

        if (!file_exists($logFile)) {
            return "No error log file found";
        }

        $lines = file($logFile);
        $recent = array_slice($lines, -$limit);

        $output = "Recent {$limit} log entries:\n";
        foreach ($recent as $line) {
            if (strlen(trim($line)) > 5) {
                $output .= "- " . substr(trim($line), 0, 200) . "\n";
            }
        }

        return $output;
    }

    /**
     * Tool: List Users
     */
    private function toolListUsers(int $limit = 10, ?string $role = null): string
    {
        try {
            require_once __DIR__ . '/../../Models/UserModel.php';
            $model = new UserModel($this->mysqli);

            // Simple user list - in production, add proper method
            $output = "User listing (limit: {$limit}";
            if ($role) {
                $output .= ", role: {$role}";
            }
            $output .= ")\n";

            $output .= "- Use /admin/users to view full user management";

            return $output;
        } catch (Exception $e) {
            return "Error listing users: " . $e->getMessage();
        }
    }

    /**
     * Tool: Send Notification
     */
    private function toolSendNotification(string $message, string $type = 'info'): string
    {
        if (empty($message)) {
            return "Error: No message provided";
        }

        // Log notification (actual implementation would use notification system)
        error_log("[ReActAgent Notification] [{$type}] {$message}");

        return "Notification queued: [{$type}] {$message}";
    }

    /**
     * Extract final answer from trace
     */
    private function extractFinalAnswer(array $trace): string
    {
        // Look for finish action observation
        foreach (array_reverse($trace) as $entry) {
            if (($entry['action'] ?? '') === 'finish' && $entry['observation']) {
                return str_replace('Task complete: ', '', $entry['observation']);
            }
        }

        // Fall back to last thought
        $lastThought = '';
        foreach (array_reverse($trace) as $entry) {
            if (!empty($entry['thought'])) {
                $lastThought = $entry['thought'];
                break;
            }
        }

        return $lastThought ?: "Task completed but no clear answer provided.";
    }

    /**
     * Get available tools (for external use)
     */
    public function getTools(): array
    {
        return $this->tools;
    }
}
