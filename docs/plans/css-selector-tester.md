# CSS Selector Tester for Scraper Diagnostics

## Overview
This document describes a CSS Selector Tester tool designed to verify that CSS selectors used in scraper configurations are working correctly against live websites.

## Purpose
The CSS Selector Tester helps diagnose scraper issues by:
- Testing selectors against live websites
- Identifying broken or outdated selectors
- Providing sample data extracted by each selector
- Measuring selector performance and accuracy

## Implementation Details

### Core Components
1. **PhpScraperService Integration** - Uses the existing PHP Scraper service for fetching and parsing web pages
2. **Selector Testing Engine** - Tests individual CSS selectors and reports results
3. **Result Formatting** - Provides clear, actionable output for troubleshooting

### Key Features
- Tests multiple selectors against a single URL
- Reports success/failure status for each selector
- Shows number of matches found
- Provides sample values extracted by working selectors
- Identifies specific errors for broken selectors
- Tracks overall test performance

### Usage Example
The tester can be used to validate selectors from existing presets like Prothom Alo:

```php
$tester = new CssSelectorTester();

$prothomAloSelectors = [
    'title' => 'h1.story-details-headline',
    'content' => '.story-element.story-element--text',
    'image' => '.story-element.story-element--image img',
    'date' => '.story-publish-time',
    'author' => '.story-byline',
    'category' => '.story-section',
    'tags' => '.story-tags a'
];

$results = $tester->testSelectors('https://www.prothomalo.com/', $prothomAloSelectors);
echo $tester->getFormattedResults();
```

### Expected Output Format
```
CSS Selector Test Results
========================
URL: https://www.prothomalo.com/
Timestamp: 2026-03-30 21:59:00
Overall Success: YES
Selectors Tested: 7
Selectors Passed: 7
Selectors Failed: 0

SELECTOR DETAILS:
  [title] h1.story-details-headline
    Success: YES
    Matches: 1
    Samples: Breaking News: Important Headline Here
  
  [content] .story-element.story-element--text
    Success: YES
    Matches: 15
    Samples: This is the first paragraph of the article content...
             This is the second paragraph with more details...
             The article continues with additional information...
  
  [image] .story-element.story-element--image img
    Success: YES
    Matches: 3
    Samples: https://example.com/image1.jpg
             https://example.com/image2.jpg
             https://example.com/image3.jpg
  
  [date] .story-publish-time
    Success: YES
    Matches: 1
    Samples: March 30, 2026 10:30 AM
  
  [author] .story-byline
    Success: YES
    Matches: 1
    Samples: By Reporter Name
  
  [category] .story-section
    Success: YES
    Matches: 1
    Samples: National News
  
  [tags] .story-tags a
    Success: YES
    Matches: 5
    Samples: Politics | Economy | Sports | Technology | Entertainment
```

## Integration Points
This tester can be integrated into:
1. **Scraper source validation** - Test selectors when creating/updating sources
2. **Diagnostic dashboard** - Provide real-time selector testing in admin interface
3. **Automated monitoring** - Schedule regular selector validation checks
4. **Preset validation** - Test preset selectors against example URLs

## Benefits
- **Quick Issue Identification** - Immediately see which selectors are broken
- **Reduced Debugging Time** - No need to manually test selectors in browser
- **Improved Accuracy** - Validate selectors actually extract expected data
- **Better Maintenance** - Keep selector configurations up-to-date
- **Proactive Monitoring** - Detect website changes before they break scraping

## Next Steps
1. Implement the tester as a PHP class in the diagnostics module
2. Create a web interface for easy selector testing
3. Integrate with scraper source creation/editing forms
4. Add automated testing schedules for critical selectors
5. Build reporting dashboard for selector health monitoring

## Files to Create
- `app/Modules/Scraper/Diagnostics/CssSelectorTester.php` - Main tester class
- `app/Views/scraper/diagnostics/selectors.twig` - Web interface for testing
- Routes for diagnostic endpoints in ScraperController