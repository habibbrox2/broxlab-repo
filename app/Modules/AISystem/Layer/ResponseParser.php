<?php
namespace App\Modules\AISystem\Layer;

/**
 * Response Parser
 * Normalizes responses from different API providers into a consistent internal format.
 */
class ResponseParser
{

    /**
     * Parse raw response body from API providers
     */
    public static function parse(array $data, string $providerName): array
    {
        // Handle common error formats
        if (isset($data['error'])) {
            $errorMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
            return ['success' => false, 'error' => $errorMsg];
        }

        // Standard OpenAI/OpenRouter format
        if (isset($data['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'content' => $data['choices'][0]['message']['content'],
                'usage' => $data['usage'] ?? []
            ];
        }

        // Anthropic format
        if ($providerName === 'anthropic' && isset($data['content'])) {
            $content = '';
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
            return [
                'success' => true,
                'content' => $content,
                'usage' => $data['usage'] ?? []
            ];
        }

        // Handling reasoning chunks for DeepSeek/o1-like models
        if (isset($data['choices'][0]['message']) && is_array($data['choices'][0]['message'])) {
            $msg = $data['choices'][0]['message'];
            if (!empty($msg['reasoning_details'])) {
                // Some providers nest reasoning details
                return ['success' => true, 'content' => json_encode($msg), 'usage' => $data['usage'] ?? [], 'raw' => $data];
            }
            if (!empty($msg['reasoning']) && is_string($msg['reasoning'])) {
                return ['success' => true, 'content' => $msg['reasoning'], 'usage' => $data['usage'] ?? [], 'raw' => $data];
            }
        }

        return ['success' => false, 'error' => 'Unable to parse API response.', 'raw' => $data];
    }
}
