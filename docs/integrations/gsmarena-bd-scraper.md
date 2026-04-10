# GSMArena Bangladesh Scraper

## Summary
Document the PHP scraper that collects device data from gsmarena.com.bd for integration into the BroxLab catalog.

## Purpose
Clarify architecture, features, and configuration so contributors can extend or debug the scraper while keeping logs and rate limits consistent.

## Key Actions
- Keep the multi-page pagination and price parsing up to date with the selectors defined in `app/Modules/Scraper/config/gsmarena_bd.php`.\n- Respect rate limits and user-agent rotation to avoid blocks.\n- Use the cron/test scripts for scheduled runs and debugging.

## Related References
- `docs/integrations/scraper-api.md` for how the collected data flows into APIs.\n- `docs/project/project-context.md` for module placement.\n- `docs/guides/coding-standards.md` for logging and error-handling expectations.

## Scraper Overview

The GSMArena Bangladesh Scraper is a PHP-based web scraping system designed to extract mobile device information, specifications, and prices from [gsmarena.com.bd](https://www.gsmarena.com.bd). This scraper follows the same architectural pattern as other scrapers in the BroxBhai project.

## Architecture

```
GSMArena Bangladesh Scraper
├── Configuration (app/Modules/Scraper/config/gsmarena_bd.php)
├── Service Class (app/Modules/Scraper/GSMArenaBDScraperService.php)
├── Cron Script (scripts/cron/gsmarena-bd-scraper.php)
└── Test Script (scripts/test-gsmarena-bd.php)
```

## Features

1. **Multi-page Scraping**: Automatically follows pagination to scrape multiple pages
2. **Price Extraction**: Extracts and parses Bangladeshi Taka prices (৳)
3. **Device Details**: Extracts device names, images, URLs, and specifications
4. **Error Handling**: Comprehensive error handling and retry logic
5. **Rate Limiting**: Configurable delays between requests to avoid being blocked
6. **User-Agent Rotation**: Uses multiple user agents to mimic real browsers
7. **Logging**: Detailed logging for monitoring and debugging

## Configuration

The scraper configuration is stored in `app/Modules/Scraper/config/gsmarena_bd.php`:

```php
return [
    'base_url' => 'https://www.gsmarena.com.bd',
    'phones_url' => '/phones.php',
    'selectors' => [
        'phone_container' => '.product-item',
        'phone_name' => 'h3',
        'phone_price' => '.price',
        'phone_image' => 'img',
        'phone_link' => 'a',
        'phone_specs_link' => 'a[data-specs]',
        'pagination' => '.pagination a',
        'next_page' => 'a.next, a[rel="next"]',
        // ... more selectors
    ],
    'pagination' => [
        'enabled' => true,
        'max_pages' => 10,
        'delay' => 3000, // 3 seconds
    ],
    // ... more configuration
];
```

## Usage

### 1. Running the Scraper

```bash
# Basic usage (scrapes 5 pages by default)
php scripts/cron/gsmarena-bd-scraper.php

# Scrape specific number of pages
php scripts/cron/gsmarena-bd-scraper.php --max-pages=10

# Verbose output
php scripts/cron/gsmarena-bd-scraper.php --verbose

# Test mode (scrapes only 1 page)
php scripts/cron/gsmarena-bd-scraper.php --test
```

### 2. Cron Job Setup

Add to crontab for automated daily scraping:

```bash
# Run daily at 3:00 AM
0 3 * * * /usr/bin/php /path/to/broxlab/scripts/cron/gsmarena-bd-scraper.php >> /path/to/logs/gsmarena-bd-scraper.log 2>&1
```

### 3. Manual Testing

```bash
# Run the test script
php scripts/test-gsmarena-bd.php
```

## Output Format

The scraper extracts devices in the following format:

```json
{
    "slug": "samsung-galaxy-a16-5g-a1b2c3d4",
    "name": "Samsung Galaxy A16 5G",
    "price_text": "৳28,999",
    "price_value": 28999,
    "price_currency": "BDT",
    "url": "https://www.gsmarena.com.bd/samsung_galaxy_a16_5g-13000.php",
    "image_url": "https://cdn.gsmarena.com.bd/images/products/samsung_a16_5g.jpg",
    "specs_url": "https://www.gsmarena.com.bd/specs/samsung_galaxy_a16_5g",
    "scraped_at": "2026-03-27 11:00:00"
}
```

## Database Integration

The scraper is designed to work with a database model. Create a model class at `app/Models/GSMArenaBDDeviceModel.php`:

```php
class GSMArenaBDDeviceModel {
    public function saveDevice(array $device): array {
        // Implementation for saving to database
    }
    
    public function getTotalCount(): int {
        // Return total device count
    }
}
```

## Error Handling

The scraper includes comprehensive error handling:

1. **HTTP Errors**: Logs and retries failed requests
2. **Parsing Errors**: Continues processing even if some elements fail to parse
3. **Rate Limiting**: Automatically delays requests to avoid being blocked
4. **Notification System**: Sends email notifications to admins for critical errors

## Logs

Logs are stored in:
- `logs/gsmarena-bd-scraper.log` - Main scraper log
- `logs/gsmarena-bd-errors.log` - Error log (if configured)
- `app/Modules/Scraper/logs/gsmarena_bd_last_scrape.json` - Last scrape statistics

## Performance Considerations

1. **Rate Limiting**: Default 3-second delay between pages to avoid being blocked
2. **Memory Usage**: Processes pages sequentially to minimize memory usage
3. **Timeout**: 30-second timeout for HTTP requests
4. **Concurrency**: Single-threaded to avoid overwhelming the target server

## Security

1. **Input Validation**: All URLs and inputs are validated
2. **SQL Injection Protection**: Uses prepared statements in database operations
3. **Error Masking**: Sensitive information is not logged
4. **User-Agent Spoofing**: Uses realistic user agents to avoid detection

## Troubleshooting

### Common Issues

1. **No devices found**: Check if CSS selectors need updating (website structure may have changed)
2. **HTTP 403 errors**: The website may be blocking requests (try increasing delays or rotating user agents)
3. **Memory exhaustion**: Reduce `max_pages` or implement batch processing

### Debugging

```bash
# Enable verbose logging
php scripts/cron/gsmarena-bd-scraper.php --verbose --max-pages=1

# Check logs
tail -f logs/gsmarena-bd-scraper.log

# Test selectors manually
php scripts/test-gsmarena-bd.php
```

## Maintenance

1. **Regular Updates**: Monitor for website structure changes and update selectors accordingly
2. **Log Rotation**: Implement log rotation for large log files
3. **Performance Monitoring**: Monitor scrape duration and success rates
4. **Data Validation**: Regularly validate scraped data for accuracy

## Related Components

- `GSMArenaDeviceScraperService` - International GSMArena scraper
- `HtmlParserService` - HTML parsing utilities
- `HttpClientService` - HTTP client with retry logic
- `MobileDeviceScraper` - JavaScript-based mobile device scraper

## Version History

- **v1.0.0** (2026-03-27): Initial release with basic scraping functionality
- **Features**: Multi-page scraping, price parsing, error handling, logging

## Contributing

When making changes to the scraper:

1. Update CSS selectors if website structure changes
2. Test with `scripts/test-gsmarena-bd.php`
3. Run in test mode before deploying to production
4. Update documentation for any configuration changes

## License

Part of the BroxBhai project. See project LICENSE for details.
