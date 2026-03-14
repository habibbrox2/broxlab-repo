<?php
namespace App\Modules\AISystem\Layer;

/**
 * Prompt Builder
 * Utility class to dynamically build structured, high-quality prompts.
 */
class PromptBuilder
{
    private $systemPrompt = "";
    private $messages = [];
    private $knowledgeContext = "";

    public function setSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function appendKnowledge(string $context): self
    {
        $this->knowledgeContext .= $context;
        return $this;
    }

    public function addMessage(string $role, string $content): self
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
        return $this;
    }

    public function build(): array
    {
        $finalMessages = [];

        $finalSystemContent = trim($this->systemPrompt);
        if (!empty($this->knowledgeContext)) {
            $finalSystemContent .= "\n\n" . trim($this->knowledgeContext);
        }

        if (!empty($finalSystemContent)) {
            $finalMessages[] = ['role' => 'system', 'content' => $finalSystemContent];
        }

        foreach ($this->messages as $msg) {
            $finalMessages[] = $msg;
        }

        return $finalMessages;
    }
}
