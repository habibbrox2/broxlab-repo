<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use mysqli;

/**
 * ProthomAloScraperService.php
 * Specialized scraper for Prothom Alo news website
 */
class ProthomAloScraperService
{
    private EnhancedScraperService $scraper;
    private ContentCleanerService $cleaner;
    private DuplicateCheckerService $duplicateChecker;
    private ImageDownloaderService $imageDownloader;
    private mysqli $mysqli;

    public function __construct(
        EnhancedScraperService $scraper,
        ContentCleanerService $cleaner,
        DuplicateCheckerService $duplicateChecker,
        ImageDownloaderService $imageDownloader,
        mysqli $mysqli
    ) {
        $this->scraper = $scraper;
        $this->cleaner = $cleaner;
        $this->duplicateChecker = $duplicateChecker;
        $this->imageDownloader = $imageDownloader;
        $this->mysqli = $mysqli;
    }

    /**
     * Scrape Prothom Alo homepage and save articles
     */
    public function scrapeHomepage(string $url = 'https://www.prothomalo.com/'): array
    {
        $result = [
            'success' => false,
            'articles_scraped' => 0,
            'articles_saved' => 0,
            'duplicates_found' => 0,
            'errors' => []
        ];

        try {
            // Scrape the list page
            $listData = $this->scraper->scrapeProthomAloList($url);

            if (!$listData['success']) {
                $result['errors'][] = 'Failed to scrape list: ' . ($listData['error'] ?? 'Unknown error');
                return $result;
            }

            $result['success'] = true;
            $result['articles_scraped'] = count($listData['articles']);

            // Process each article
            foreach ($listData['articles'] as $articleInfo) {
                try {
                    // Scrape individual article
                    $articleData = $this->scraper->scrapeProthomAloArticle($articleInfo['url']);

                    if (!$articleData['success']) {
                        $result['errors'][] = 'Failed to scrape article: ' . $articleInfo['url'] . ' - ' . ($articleData['error'] ?? 'Unknown error');
                        continue;
                    }

                    // Clean content
                    $cleanedContent = $this->cleaner->cleanProthomAloContent($articleData['content']);

                    if (!$cleanedContent['is_valid']) {
                        $result['errors'][] = 'Content too short or invalid for: ' . $articleInfo['title'];
                        continue;
                    }

                    // Check for duplicates
                    $duplicateCheck = $this->duplicateChecker->checkDuplicate([
                        'url' => $articleData['url'],
                        'title' => $articleData['title'],
                        'content' => $cleanedContent['text']
                    ]);

                    if ($duplicateCheck['is_duplicate']) {
                        $result['duplicates_found']++;
                        continue;
                    }

                    // Download featured image if available
                    $featuredImagePath = null;
                    if (!empty($articleData['featured_image'])) {
                        $featuredImagePath = $this->imageDownloader->download($articleData['featured_image'], 'prothom_alo');
                    }

                    // Save to database
                    $saved = $this->saveArticle([
                        'title' => $articleData['title'],
                        'original_content' => $cleanedContent['html'],
                        'clean_content' => $cleanedContent['text'],
                        'url' => $articleData['url'],
                        'author' => $articleData['author'],
                        'published_date' => $this->parseDate($articleData['date']),
                        'featured_image' => $featuredImagePath,
                        'images' => json_encode($cleanedContent['images']),
                        'word_count' => $cleanedContent['word_count'],
                        'source' => 'prothom_alo',
                        'status' => 'pending'
                    ]);

                    if ($saved) {
                        $result['articles_saved']++;
                    } else {
                        $result['errors'][] = 'Failed to save article: ' . $articleInfo['title'];
                    }

                } catch (\Exception $e) {
                    $result['errors'][] = 'Error processing article ' . $articleInfo['url'] . ': ' . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            $result['errors'][] = 'General error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Parse Prothom Alo date string
     */
    private function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        // Try datetime attribute first
        if (strtotime($dateString) !== false) {
            return date('Y-m-d H:i:s', strtotime($dateString));
        }

        // Handle Bengali date formats
        $bengaliMonths = [
            'জানুয়ারি' => 'January',
            'ফেব্রুয়ারি' => 'February',
            'মার্চ' => 'March',
            'এপ্রিল' => 'April',
            'মে' => 'May',
            'জুন' => 'June',
            'জুলাই' => 'July',
            'আগস্ট' => 'August',
            'সেপ্টেম্বর' => 'September',
            'অক্টোবর' => 'October',
            'নভেম্বর' => 'November',
            'ডিসেম্বর' => 'December'
        ];

        $dateString = str_replace(array_keys($bengaliMonths), array_values($bengaliMonths), $dateString);

        // Try to parse common formats
        $formats = [
            'd F Y, H:i',
            'd/m/Y H:i',
            'Y-m-d H:i:s'
        ];

        foreach ($formats as $format) {
            $parsed = date_parse_from_format($format, $dateString);
            if ($parsed['error_count'] === 0) {
                return sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                    $parsed['year'], $parsed['month'], $parsed['day'],
                    $parsed['hour'], $parsed['minute'], $parsed['second']);
            }
        }

        return null;
    }

    /**
     * Save article to database
     */
    private function saveArticle(array $data): bool
    {
        $sql = "INSERT INTO autocontent_articles (
            title, original_content, clean_content, url, author,
            published_date, featured_image, images, word_count,
            source, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'sssssssssss',
            $data['title'],
            $data['original_content'],
            $data['clean_content'],
            $data['url'],
            $data['author'],
            $data['published_date'],
            $data['featured_image'],
            $data['images'],
            $data['word_count'],
            $data['source'],
            $data['status']
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Get scraping statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total articles
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM autocontent_articles WHERE source = 'prothom_alo'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_articles'] = (int)$row['total'];
        }

        // Today's articles
        $result = $this->mysqli->query("SELECT COUNT(*) as today FROM autocontent_articles WHERE source = 'prothom_alo' AND DATE(created_at) = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['today_articles'] = (int)$row['today'];
        }

        // Published articles
        $result = $this->mysqli->query("SELECT COUNT(*) as published FROM autocontent_articles WHERE source = 'prothom_alo' AND status = 'published'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['published_articles'] = (int)$row['published'];
        }

        return $stats;
    }
}
