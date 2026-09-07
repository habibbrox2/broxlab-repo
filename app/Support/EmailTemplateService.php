<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Renders email templates from the legacy `email_templates` table,
 * replacing {{UPPER_CASE_KEY}} placeholders (same as the legacy
 * EmailTemplate model).
 */
class EmailTemplateService
{
    public function getBySlug(string $slug): ?object
    {
        return DB::table('email_templates')->where('slug', $slug)->first();
    }

    public function renderSubject(string $slug, array $variables = []): string
    {
        $template = $this->getBySlug($slug);

        return $template ? $this->replace($template->subject, $variables) : '';
    }

    public function renderBody(string $slug, array $variables = []): string
    {
        $template = $this->getBySlug($slug);

        return $template ? $this->replace($template->body, $variables) : '';
    }

    public function render(string $slug, array $variables = []): array
    {
        $template = $this->getBySlug($slug);

        if (! $template) {
            return ['subject' => '', 'body' => ''];
        }

        return [
            'subject' => $this->replace($template->subject, $variables),
            'body' => $this->replace($template->body, $variables),
        ];
    }

    private function replace(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.strtoupper($key).'}}', (string) $value, $text);
        }

        return $text;
    }
}