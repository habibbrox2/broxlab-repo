#!/usr/bin/env bash
# Selector Testing Quick Start Guide

# This file documents the selector testing API endpoints and usage examples

echo "=== Selector Testing API Quick Reference ==="
echo ""
echo "📍 Access Points:"
echo "  - Web UI:      /admin/scraper/selector-tester"
echo "  - API Base:    /admin/scraper/selectors/"
echo "  - JS Client:   /assets/js/selector-testing-client.js"
echo ""

# Example 1: Test CSS selector with curl
echo "=== Example 1: Test CSS Selector with curl ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/test-css' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: YOUR_CSRF_TOKEN' \
  -d '{
    "url": "https://example.com",
    "selector": ".article-item",
    "max_samples": 5
  }'
EOF
echo ""

# Example 2: Test XPath selector
echo "=== Example 2: Test XPath Selector ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/test-xpath' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: YOUR_CSRF_TOKEN' \
  -d '{
    "url": "https://example.com",
    "selector": "//div[@class=\"article\"]",
    "max_samples": 5
  }'
EOF
echo ""

# Example 3: Extract attributes
echo "=== Example 3: Extract Attributes ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/test-attribute' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: YOUR_CSRF_TOKEN' \
  -d '{
    "url": "https://example.com",
    "selector": "a.article-link",
    "attribute": "href",
    "max_samples": 10
  }'
EOF
echo ""

# Example 4: Test nested selectors
echo "=== Example 4: Test Nested Selectors (Full Config) ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/test-nested' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: YOUR_CSRF_TOKEN' \
  -d '{
    "url": "https://example.com",
    "container_selector": ".article",
    "field_mappings": {
      "title": "h2.title",
      "author": "span.author",
      "date": "time",
      "url": "a.permalink"
    },
    "max_samples": 5
  }'
EOF
echo ""

# Example 5: Validate batch
echo "=== Example 5: Validate Multiple Selectors ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/validate-batch' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: YOUR_CSRF_TOKEN' \
  -d '{
    "url": "https://example.com",
    "selectors": [".article", ".title", ".date", ".author"]
  }'
EOF
echo ""

# Example 6: Get page metadata
echo "=== Example 6: Get Page Metadata (Public - No Auth) ==="
cat << 'EOF'
curl -X POST 'http://localhost/admin/scraper/selectors/page-info' \
  -H 'Content-Type: application/json' \
  -d '{
    "url": "https://example.com"
  }'
EOF
echo ""

# JavaScript examples
echo "=== JavaScript Usage ==="
cat << 'EOF'
// Include client library
<script src="/assets/js/selector-testing-client.js"></script>

<script>
// Create client instance
const client = new SelectorTestingClient();

// Test CSS selector
client.testCss('.item', 'https://example.com')
  .then(result => console.log(result))
  .catch(error => console.error(error));

// Test XPath
client.testXPath('//div[@class="item"]', 'https://example.com');

// Extract attributes
client.testAttribute('a.link', 'href', 'https://example.com');

// Validate nested extraction
client.testNested(
  '.article',
  { title: 'h2', author: '.author', date: 'time' },
  'https://example.com'
);

// Validate batch
client.validateBatch(['.item', '.title'], 'https://example.com');

// Get page metadata
client.getPageInfo('https://example.com');
</script>
EOF
echo ""

echo "📚 Documentation: /docs/SELECTOR_TESTING_API.md"
echo "📂 Twig Template: /app/Views/admin/scraper/selector-tester.twig"
echo "🔧 Service Class: /app/Modules/Scraper/Services/SelectorTestingService.php"
echo "🛣️  Routes: /app/Controllers/ScraperController.php (lines 47-80, 834-1128)"
echo ""
echo "👉 Access at: http://localhost/admin/scraper/selector-tester"
