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

    $messages = [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $message]
    ];

    $response = $agent->chat($messages, $provider, $model);
    echo json_encode($response);
});
