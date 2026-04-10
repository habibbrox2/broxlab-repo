<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * LinkedInJobsPreset - Scraper preset for LinkedIn Jobs
 * 
 * Professional networking site for job postings
 */
class LinkedInJobsPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'linkedin_jobs';
    }

    public function getName(): string
    {
        return 'LinkedIn Jobs';
    }

    public function getDescription(): string
    {
        return 'Professional networking site for job postings and career opportunities.';
    }

    public function getCategory(): string
    {
        return 'Jobs';
    }

    public function getIcon(): string
    {
        return '💼';
    }

    public function getType(): string
    {
        return 'static';
    }

    public function getContentType(): string
    {
        return 'job';
    }

    public function getFetchInterval(): int
    {
        return 120; // 2 hours
    }

    public function getDelay(): int
    {
        return 5; // Be respectful to LinkedIn
    }

    public function getMaxPages(): int
    {
        return 5;
    }

    public function getPaginationType(): string
    {
        return 'query';
    }

    public function getPaginationSelector(): ?string
    {
        return null;
    }

    public function getPaginationPattern(): ?string
    {
        return 'start';
    }

    public function getConfig(): array
    {
        return [
            'list_container' => '.jobs-search-results-list',
            'list_item' => '.jobs-search-results-list__item',
            'list_title' => '.base-search-card__title',
            'list_link' => '.base-card__full-link',
            'list_company' => '.base-search-card__subtitle',
            'list_location' => '.job-search-card__location',
            'list_date' => '.job-search-card__listdate',
            'title' => '.top-card-layout__title',
            'company' => '.topcard__org-name-link',
            'location' => '.topcard__flavor--bullet',
            'description' => '.show-more-less-html__markup',
            'date' => '.posted-time-ago__text',
            'seniority' => '.description__job-criteria-text',
            'employment_type' => '.description__job-criteria-text',
            'industry' => '.description__job-criteria-text',
            'pagination' => '.infinite-scroller__show-more-button'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.linkedin.com/jobs/',
            'https://www.linkedin.com/jobs/search/?keywords=developer',
            'https://www.linkedin.com/jobs/search/?location=Dhaka'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'linkedin.com') &&
            str_contains($url, '/jobs/');
    }
}
