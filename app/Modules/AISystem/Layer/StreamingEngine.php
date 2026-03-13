<?php
namespace App\Modules\AISystem\Layer;

/**
 * Streaming Engine
 * Wrapper logic for handling API streaming safely.
 */
class StreamingEngine
{

    public static function streamResponse(callable $apiStreamFunction)
    {
        // Disable output buffering
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // Execute API call with a callback that flushes chunks directly to the client
        $apiStreamFunction(function ($chunk) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content = $chunk['choices'][0]['delta']['content'];
                echo "data: " . json_encode(['text' => $content]) . "\n\n";
                flush();
            }
        });

        echo "data: [DONE]\n\n";
        flush();
    }
}
