/**
* OpenRouter Provider Module - Phase 3 Backend Integration
* Path: /app/Providers/OpenRouterProvider.php
*
* Handles:
* - OpenRouter API communication
* - Stream response formatting
* - Error handling & retry logic
* - Token management
*/

namespace App\Providers;

class OpenRouterProvider {
private $apiKey;
private $baseUrl = 'https://openrouter.ai/api/v1';
private $model = 'openai/gpt-4o-mini';
private $timeout = 30;

public function __construct() {
$this->apiKey = getenv('OPENROUTER_KEY');

if (!$this->apiKey) {
throw new \Exception('OPENROUTER_KEY environment variable not set');
}
}

/**
* Stream chat completion
* Yields SSE-formatted chunks
*/
public function streamChat($messages, $options = []) {
try {
$payload = [
'model' => $options['model'] ?? $this->model,
'messages' => $messages,
'stream' => true,
'temperature' => $options['temperature'] ?? 0.7,
'max_tokens' => $options['max_tokens'] ?? 2000,
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
'Authorization: Bearer ' . $this->apiKey,
'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Handle streaming response
$this->handleStreamResponse($ch);

curl_close($ch);

} catch (\Exception $e) {
$this->sendSSEError($e->getMessage());
throw $e;
}
}

/**
* Handle streaming response from OpenRouter
*/
private function handleStreamResponse($ch) {
$fullContent = '';
$buffer = '';

while (!curl_exec($ch) === true) {
$data = curl_multi_getcontent($ch);
if ($data) {
$buffer .= $data;
$lines = explode("\n", $buffer);

// Process complete lines
for ($i = 0; $i < count($lines) - 1; $i++) {
    $line=$lines[$i];

    if (strpos($line, 'data:' )===0) {
    $jsonStr=trim(substr($line, 5));

    if ($jsonStr==='[DONE]' ) {
    break 2;
    }

    try {
    $parsed=json_decode($jsonStr, true);
    $token=$parsed['choices'][0]['delta']['content'] ?? '' ;

    if ($token) {
    $fullContent .=$token;
    $this->sendSSEChunk($token, [
    'index' => $parsed['choices'][0]['index'] ?? 0,
    'finish_reason' => $parsed['choices'][0]['finish_reason'] ?? null,
    ]);
    }
    } catch (\Exception $e) {
    // Skip invalid JSON lines
    continue;
    }
    }
    }

    // Keep unprocessed part for next iteration
    $buffer = end($lines);
    }
    }

    // Send completion event
    $this->sendSSEComplete(['content' => $fullContent]);
    }

    /**
    * Non-streaming chat completion
    */
    public function chat($messages, $options = []) {
    try {
    $payload = [
    'model' => $options['model'] ?? $this->model,
    'messages' => $messages,
    'stream' => false,
    'temperature' => $options['temperature'] ?? 0.7,
    'max_tokens' => $options['max_tokens'] ?? 2000,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $this->apiKey,
    'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
    throw new \Exception('OpenRouter API error: HTTP ' . $httpCode);
    }

    $data = json_decode($response, true);
    curl_close($ch);

    return [
    'content' => $data['choices'][0]['message']['content'] ?? '',
    'model' => $data['model'] ?? $this->model,
    'usage' => $data['usage'] ?? [],
    ];

    } catch (\Exception $e) {
    curl_close($ch);
    throw new \Exception('OpenRouter API failed: ' . $e->getMessage());
    }
    }

    /**
    * Send SSE chunk
    */
    private function sendSSEChunk($content, $metadata = []) {
    $data = [
    'type' => 'chunk',
    'text' => $content,
    'timestamp' => time(),
    'metadata' => $metadata,
    ];

    echo 'data: ' . json_encode($data) . "\n\n";
    flush();
    }

    /**
    * Send SSE complete
    */
    private function sendSSEComplete($data) {
    $response = [
    'type' => 'complete',
    'data' => $data,
    'timestamp' => time(),
    ];

    echo 'data: ' . json_encode($response) . "\n\n";
    flush();
    }

    /**
    * Send SSE error
    */
    private function sendSSEError($message) {
    $error = [
    'type' => 'error',
    'message' => $message,
    'timestamp' => time(),
    ];

    echo 'data: ' . json_encode($error) . "\n\n";
    flush();
    }

    /**
    * Validate messages format
    */
    public function validateMessages($messages) {
    if (!is_array($messages)) {
    throw new \InvalidArgumentException('Messages must be an array');
    }

    foreach ($messages as $msg) {
    if (!isset($msg['role']) || !isset($msg['content'])) {
    throw new \InvalidArgumentException('Each message must have role and content');
    }

    if (!in_array($msg['role'], ['system', 'user', 'assistant'])) {
    throw new \InvalidArgumentException('Invalid role: ' . $msg['role']);
    }
    }

    return true;
    }

    /**
    * Get available models
    */
    public function getAvailableModels() {
    return [
    'openai/gpt-4o' => 'GPT-4 Omni',
    'openai/gpt-4o-mini' => 'GPT-4 Omni Mini',
    'openai/gpt-4-turbo' => 'GPT-4 Turbo',
    'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo',
    'anthropic/claude-3-opus' => 'Claude 3 Opus',
    'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
    'meta-llama/llama-2-70b-chat' => 'Llama 2 70B',
    ];
    }
    }