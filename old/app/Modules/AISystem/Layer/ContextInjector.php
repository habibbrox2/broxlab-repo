<?php
namespace App\Modules\AISystem\Layer;

/**
 * Context Injector
 * Injects contextual information (like page content, current time, user info) into the prompt safely.
 */
class ContextInjector
{
    private $contextData = [];

    public function __construct(array $initialContext = [])
    {
        $this->contextData = $initialContext;

        // Always inject system defaults
        $this->contextData['system_time'] = date('Y-m-d H:i:s P');
    }

    public function addContext(string $key, $value): void
    {
        $this->contextData[$key] = $value;
    }

    /**
     * Injects context into the messages array.
     * Prefers modifying the system prompt if one exists.
     */
    public function inject(array $messages): array
    {
        if (empty($this->contextData)) {
            return $messages;
        }

        $contextString = "\n\n[SYSTEM CONTEXT]\n";
        foreach ($this->contextData as $key => $val) {
            if (is_scalar($val)) {
                $contextString .= ucfirst($key) . ": " . $val . "\n";
            }
            elseif (is_array($val)) {
                $contextString .= ucfirst($key) . ": " . json_encode($val) . "\n";
            }
        }

        // Check if there's already a system message
        $hasSystemMsg = false;
        foreach ($messages as &$msg) {
            if ($msg['role'] === 'system') {
                $msg['content'] .= $contextString;
                $hasSystemMsg = true;
                break;
            }
        }

        // If no system message, prepend one
        if (!$hasSystemMsg) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => "You are a helpful AI assistant." . $contextString
            ]);
        }

        return $messages;
    }
}
