<?php
namespace App\Modules\AISystem\Layer;

/**
 * Tool Calling Handler
 * Manages tool definitions and executes local tools when requested by the model.
 */
class ToolCallingHandler
{
    private $tools = [];

    /**
     * Register a new tool
     * 
     * @param string $name Name of the tool
     * @param string $description What the tool does
     * @param array $parameters JSON Schema of parameters
     * @param callable $callback The PHP function to execute
     */
    public function registerTool(string $name, string $description, array $parameters, callable $callback)
    {
        $this->tools[$name] = [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters
            ],
            'callback' => $callback
        ];
    }

    /**
     * Get the list of registered tools formatted for OpenAI/OpenRouter APIs
     */
    public function getToolsForApi(): array
    {
        $apiTools = [];
        foreach ($this->tools as $tool) {
            $apiTools[] = [
                'type' => $tool['type'],
                'function' => $tool['function']
            ];
        }
        return $apiTools;
    }

    /**
     * Execute a tool call requested by the model
     * 
     * @param string $name The name of the tool to call
     * @param string $argumentsJson The JSON arguments provided by the model
     * @return mixed Tool execution result
     */
    public function handleCall(string $name, string $argumentsJson)
    {
        if (!isset($this->tools[$name])) {
            return "Error: Tool '{$name}' not found.";
        }

        $args = json_decode($argumentsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Error: Invalid JSON arguments provided.";
        }

        try {
            $callback = $this->tools[$name]['callback'];
            // Pass the decoded array to the callback
            $result = call_user_func($callback, $args);

            // Ensure result is stringifiable for the next turn
            if (is_array($result) || is_object($result)) {
                return json_encode($result);
            }
            return (string)$result;
        }
        catch (\Exception $e) {
            return "Error executing tool: " . $e->getMessage();
        }
    }

    public function hasTools(): bool
    {
        return !empty($this->tools);
    }
}
