<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use App\Modules\Scraper\HtmlParserService;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * Selector Testing Service
 * Test CSS and XPath selectors against HTML content to verify they work
 */
class SelectorTestingService
{
    private HtmlParserService $htmlParser;
    private string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
        $this->htmlParser = new HtmlParserService($html);
    }

    /**
     * Test a CSS selector and return detailed results
     */
    public function testCssSelector(string $selector, int $maxSamples = 5): array
    {
        try {
            $crawler = $this->htmlParser->getCrawler();
            $matches = $crawler->filter($selector);
            $count = $matches->count();

            if ($count === 0) {
                return [
                    'success' => true,
                    'type' => 'css',
                    'selector' => $selector,
                    'matched' => false,
                    'count' => 0,
                    'message' => 'No elements matched this selector'
                ];
            }

            $samples = [];
            $matches->each(function (Crawler $node, $index) use (&$samples, $maxSamples) {
                if ($index < $maxSamples) {
                    $samples[] = [
                        'index' => $index,
                        'text' => substr($node->text(''), 0, 200),
                        'html' => substr($node->html(), 0, 300),
                        'attributes' => $this->extractAttributes($node)
                    ];
                }
                return true;
            });

            return [
                'success' => true,
                'type' => 'css',
                'selector' => $selector,
                'matched' => true,
                'count' => $count,
                'samples' => $samples,
                'message' => "Matched {$count} element" . ($count !== 1 ? 's' : '')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'css',
                'selector' => $selector,
                'error' => $e->getMessage(),
                'message' => 'CSS selector is invalid'
            ];
        }
    }

    /**
     * Test an XPath selector and return detailed results
     */
    public function testXPathSelector(string $xpath, int $maxSamples = 5): array
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $xpath_obj = new \DOMXPath($dom);
            $matches = $xpath_obj->query($xpath);

            if (!$matches || $matches->length === 0) {
                return [
                    'success' => true,
                    'type' => 'xpath',
                    'selector' => $xpath,
                    'matched' => false,
                    'count' => 0,
                    'message' => 'No elements matched this XPath'
                ];
            }

            $samples = [];
            for ($i = 0; $i < min($maxSamples, $matches->length); $i++) {
                $node = $matches->item($i);
                $attributes = [];
                // Handle DOMNode - it might be an Element or other node type
                if ($node && method_exists($node, 'attributes') && $node->attributes) {
                    foreach ($node->attributes as $attr) {
                        $attributes[$attr->name] = $attr->value;
                    }
                }
                $samples[] = [
                    'index' => $i,
                    'text' => substr($node->textContent, 0, 200),
                    'html' => substr($dom->saveHTML($node), 0, 300),
                    'tag' => $node->nodeName,
                    'attributes' => $attributes
                ];
            }

            return [
                'success' => true,
                'type' => 'xpath',
                'selector' => $xpath,
                'matched' => true,
                'count' => $matches->length,
                'samples' => $samples,
                'message' => "Matched {$matches->length} element" . ($matches->length !== 1 ? 's' : '')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => 'xpath',
                'selector' => $xpath,
                'error' => $e->getMessage(),
                'message' => 'XPath selector is invalid'
            ];
        }
    }

    /**
     * Test attribute extraction from a selector
     */
    public function testAttributeExtraction(string $selector, string $attribute, int $maxSamples = 5): array
    {
        try {
            $crawler = $this->htmlParser->getCrawler();
            $matches = $crawler->filter($selector);
            $count = $matches->count();

            if ($count === 0) {
                return [
                    'success' => true,
                    'selector' => $selector,
                    'attribute' => $attribute,
                    'matched' => false,
                    'count' => 0,
                    'message' => 'No elements matched'
                ];
            }

            $values = [];
            $matches->each(function (Crawler $node, $index) use (&$values, $attribute, $maxSamples) {
                if ($index < $maxSamples) {
                    $value = $node->attr($attribute);
                    if ($value !== null) {
                        $values[] = [
                            'index' => $index,
                            'value' => $value
                        ];
                    }
                }
                return true;
            });

            return [
                'success' => true,
                'selector' => $selector,
                'attribute' => $attribute,
                'matched' => count($values) > 0,
                'found_count' => count($values),
                'total_elements' => $count,
                'values' => $values,
                'message' => "Found {$attribute} in " . count($values) . " of {$count} elements"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'selector' => $selector,
                'attribute' => $attribute,
                'error' => $e->getMessage(),
                'message' => 'Selector is invalid'
            ];
        }
    }

    /**
     * Test nested selector extraction (element + sub-selector)
     */
    public function testNestedSelection(string $containerSelector, string $fieldMappings, int $maxSamples = 5): array
    {
        try {
            $mappings = json_decode($fieldMappings, true);
            if (!is_array($mappings)) {
                return [
                    'success' => false,
                    'error' => 'Field mappings must be valid JSON',
                    'message' => 'Invalid field mappings format'
                ];
            }

            $crawler = $this->htmlParser->getCrawler();
            $containers = $crawler->filter($containerSelector);
            $containerCount = $containers->count();

            if ($containerCount === 0) {
                return [
                    'success' => true,
                    'containerSelector' => $containerSelector,
                    'matched' => false,
                    'message' => 'No containers matched'
                ];
            }

            $results = [];
            $containers->each(function (Crawler $container, $index) use (&$results, $mappings, $maxSamples) {
                if ($index < $maxSamples) {
                    $item = [];
                    foreach ($mappings as $field => $selector) {
                        try {
                            $value = $container->filter($selector)->text('');
                            $item[$field] = trim($value);
                        } catch (Exception $e) {
                            $item[$field] = null;
                        }
                    }
                    $results[] = $item;
                }
                return true;
            });

            return [
                'success' => true,
                'containerSelector' => $containerSelector,
                'fieldMappings' => $mappings,
                'containerCount' => $containerCount,
                'samplesExtracted' => count($results),
                'samples' => $results,
                'message' => "Extracted {$containerCount} containers with nested fields"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'containerSelector' => $containerSelector,
                'error' => $e->getMessage(),
                'message' => 'Nested selection failed'
            ];
        }
    }

    /**
     * Validate multiple selectors (batch test)
     */
    public function validateSelectors(array $selectors): array
    {
        $results = [];
        foreach ($selectors as $name => $selector) {
            $result = $this->testCssSelector($selector, 1);
            $results[$name] = [
                'valid' => $result['success'] && $result['matched'] ?? false,
                'matched' => $result['matched'] ?? false,
                'count' => $result['count'] ?? 0,
                'error' => $result['error'] ?? null
            ];
        }
        return $results;
    }

    /**
     * Extract attributes from a Crawler node
     */
    private function extractAttributes(Crawler $node): array
    {
        $attributes = [];
        try {
            if ($node->count() > 0) {
                $node->each(function (Crawler $n) use (&$attributes) {
                    foreach (['href', 'src', 'alt', 'title', 'class', 'id', 'data-*'] as $attr) {
                        if ($attr === 'data-*') {
                            // Get all data-* attributes
                            continue;
                        }
                        $value = $n->attr($attr);
                        if ($value) {
                            $attributes[$attr] = $value;
                        }
                    }
                    return false;
                });
            }
        } catch (Exception $e) {
            // Ignore attribute extraction errors
        }
        return $attributes;
    }

    /**
     * Extract attributes from a DOMNode
     */
    private function extractDomAttributes($node): array
    {
        $attributes = [];
        if (method_exists($node, 'attributes') && $node->attributes) {
            foreach ($node->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }
        return $attributes;
    }

    /**
     * Get URL from HTML (for checking if content matches expected site)
     */
    public function detectPageUrl(): ?string
    {
        try {
            $crawler = $this->htmlParser->getCrawler();

            // Try canonical link
            $canonical = $crawler->filter('link[rel="canonical"]')->attr('href');
            if ($canonical) {
                return $canonical;
            }

            // Try og:url meta tag
            $ogUrl = $crawler->filter('meta[property="og:url"]')->attr('content');
            if ($ogUrl) {
                return $ogUrl;
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get page title
     */
    public function getPageTitle(): ?string
    {
        try {
            $crawler = $this->htmlParser->getCrawler();
            return $crawler->filter('title')->text('');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get page meta description
     */
    public function getPageDescription(): ?string
    {
        try {
            $crawler = $this->htmlParser->getCrawler();
            return $crawler->filter('meta[name="description"]')->attr('content');
        } catch (Exception $e) {
            return null;
        }
    }
}
