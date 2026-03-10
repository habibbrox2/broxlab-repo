<?php
// app/Controllers/AISystemChatController.php

require_once __DIR__ . '/../Modules/AISystem/AgentClient.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';

/** @var \Router $router */
/** @var \mysqli $mysqli */
/** @var \Twig\Environment $twig */

$router->post('/api/chat', function () use ($mysqli) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    $provider = $input['provider'] ?? null;
    $model = $input['model'] ?? null;
    $context = $input['context'] ?? 'public'; // 'public' or 'admin'

    if (!$message) {
        echo json_encode(["error" => "No message provided"]);
        return;
    }

    $agent = new AgentClient($mysqli);

    // Load context-aware system prompt using PromptLoader
    $systemPrompt = PromptLoader::getSystemPrompt($context, $mysqli);

    // Append relevant knowledge-base context (if any) based on the user message
    $kbContext = PromptLoader::getKnowledgeContext($message, $mysqli);
    if ($kbContext) {
        $systemPrompt .= "\n\n" . $kbContext;
    }

    $messages = [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $message]
    ];

    $extraContext = [];
    if (isset($input['context']) && is_array($input['context'])) {
        $extraContext = $input['context'];
    }

    $stream = isset($input['stream']) ? (bool)$input['stream'] : false;

    // The chat method will exit script automatically if streaming is active.
    $response = $agent->chat($messages, $provider, $model, $extraContext, $stream);
    echo json_encode($response);
});
