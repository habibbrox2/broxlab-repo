# Teletalk Government Job Automation System

## Overview

This system automatically fetches government job data from the Teletalk API and stores it in a database with deduplication, normalization, and scheduled automation.

## Features

- **Automatic Data Fetching**: Fetches job data from Teletalk API every 10 minutes
- **Pagination Support**: Handles multiple pages of API responses
- **Deduplication**: Prevents duplicate job entries using job ID as unique key
- **Data Normalization**: Trims whitespace, converts empty values to NULL
- **Error Handling**: Retries failed API requests up to 3 times
- **Logging**: Comprehensive logging of all operations
- **Statistics**: Track total organizations and jobs processed

## Architecture

### Components

1. **TeletalkJobCronWorker** (`app/Modules/AutoContent/TeletalkJobCronWorker.php`)
   - Main worker class that orchestrates API fetching
   - Handles pagination, error handling, and data processing
   - Logs all operations

2. **TeletalkJobModel** (`app/Models/TeletalkJobModel.php`)
   - Database model for CRUD operations
   - Uses prepared statements for security
   - Handles organization and job data

3. **Cron Script** (`scripts/teletalk_cron.php`)
   - Entry point for cron execution
   - Supports command-line options
   - Provides statistics and help

4. **Migration Script** (`scripts/migrate_teletalk_tables.php`)
   - Creates required database tables
   - Handles schema updates

5. **Configuration** (`Config/Teletalk.php`)
   - Centralized configuration
   - Environment variable support

## Database Schema

### teletalk_organizations

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| api_id | INT UNSIGNED | Unique API organization ID |
| name | VARCHAR(255) | Organization name |
| name_bn | VARCHAR(255) | Bengali name |
| short_name | VARCHAR(100) | Short name |
| logo | VARCHAR(500) | Logo URL |
| website | VARCHAR(500) | Website URL |
| job_created_at | DATETIME | Job creation timestamp |
| created_at | DATETIME | Record creation time |
| updated_at | DATETIME | Last update time |

### teletalk_jobs

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| job_id | VARCHAR(100) | Unique job ID |
| title | VARCHAR(500) | Job title |
| title_bn | VARCHAR(500) | Bengali title |
| organization | VARCHAR(255) | Organization name |
| organization_id | INT UNSIGNED | Foreign key to organizations |
| openings | INT UNSIGNED | Number of openings |
| url | VARCHAR(500) | Job URL |
| image_url | VARCHAR(500) | Image URL |
| scraped_at | DATETIME | Scraping timestamp |
| updated_at | DATETIME | Last update time |

### teletalk_cron_logs

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| last_run_at | DATETIME | Last run timestamp |
| status | ENUM | success/error/partial |
| message | TEXT | Log message |
| created_at | DATETIME | Record creation time |

## Installation

### 1. Run Database Migration

```bash
php scripts/migrate_teletalk_tables.php
```

### 2. Configure Environment Variables

Add to your `.env` file:

```env
# Database
DB_HOST=localhost
DB_NAME=broxlab
DB_USER=root
DB_PASS=your_password
DB_PORT=3306

# Teletalk API
TELETALK_API_URL=https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list
TELETALK_PAGE_LIMIT=20
TELETALK_MAX_RETRIES=3
TELETALK_RETRY_DELAY=2

# Logging
TELETALK_LOGGING_ENABLED=true
TELETALK_LOG_PATH=/path/to/logs/teletalk_cron.log
```

### 3. Set Up Cron Job

Add to your crontab (run every 10 minutes):

```bash
*/10 * * * * /usr/bin/php /path/to/scripts/teletalk_cron.php >> /path/to/logs/teletalk_cron.log 2>&1
```

Or on Windows Task Scheduler:
- Program: `C:\php\php.exe`
- Arguments: `C:\path\to\scripts\teletalk_cron.php`
- Schedule: Every 10 minutes

## Usage

### Run Manually

```bash
# Basic run
php scripts/teletalk_cron.php

# Verbose output
php scripts/teletalk_cron.php --verbose

# Show statistics
php scripts/teletalk_cron.php --stats

# Show help
php scripts/teletalk_cron.php --help
```

### Example Output

```
Starting Teletalk job fetch...
Database: broxlab
API URL: https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list

==================================================
Execution Summary
==================================================
Status: SUCCESS
Organizations Processed: 15
Jobs Inserted: 42
Pages Fetched: 3
Execution Time: 2450ms
==================================================
```

## API Response Structure

The system expects the following JSON structure from the Teletalk API:

```json
{
  "data": [
    {
      "id": 123,
      "name": "Organization Name",
      "name_bn": "সংস্থার নাম",
      "short_name": "ORG",
      "website": "https://example.com",
      "logo": "https://example.com/logo.png",
      "govt_jobs": [
        {
          "id": 456,
          "job_title": "Job Title",
          "job_title_bn": "চাকরির শিরোনাম",
          "organization_id": 123
        }
      ]
    }
  ]
}
```

## Error Handling

The system implements multiple layers of error handling:

1. **API Retry**: Failed API requests are retried up to 3 times with delay
2. **Page Continuation**: If one page fails, the system continues to the next
3. **Organization Isolation**: Errors in one organization don't affect others
4. **Logging**: All errors are logged with timestamps

## Performance Optimization

- **Pagination Batching**: Processes data page by page to avoid memory overload
- **Prepared Statements**: Uses PDO/MySQLi prepared statements for security
- **Connection Pooling**: Reuses database connections efficiently
- **Memory Management**: Processes data incrementally

## Monitoring

### Check Last Run

```bash
php scripts/teletalk_cron.php --stats
```

### View Logs

```bash
tail -f logs/teletalk_cron.log
```

### Database Queries

```sql
-- Total organizations
SELECT COUNT(*) FROM teletalk_organizations;

-- Total jobs
SELECT COUNT(*) FROM teletalk_jobs;

-- Recent jobs
SELECT * FROM teletalk_jobs ORDER BY scraped_at DESC LIMIT 10;

-- Jobs by organization
SELECT o.name, COUNT(j.id) as job_count 
FROM teletalk_organizations o 
LEFT JOIN teletalk_jobs j ON o.id = j.organization_id 
GROUP BY o.id;
```

## Troubleshooting

### Issue: No data is being fetched

1. Check API URL is correct
2. Verify network connectivity
3. Check API rate limits
4. Review error logs

### Issue: Duplicate entries

The system uses job ID as unique key. If duplicates appear:
1. Check if job_id is being normalized correctly
2. Verify database constraints
3. Run migration script to ensure proper indexing

### Issue: Memory errors

1. Reduce page limit in configuration
2. Increase PHP memory limit
3. Check for memory leaks in logs

### Issue: Cron not running

1. Verify cron service is running
2. Check file permissions
3. Test script manually
4. Review cron logs

## Security Considerations

- **SQL Injection**: Uses prepared statements exclusively
- **Input Validation**: All API data is validated and sanitized
- **Error Exposure**: Errors are logged, not exposed to users
- **Rate Limiting**: Respects API rate limits with delays

## Maintenance

### Regular Tasks

1. **Monitor Logs**: Check for errors weekly
2. **Database Cleanup**: Archive old data monthly
3. **Performance Review**: Check execution times
4. **API Changes**: Monitor for API structure changes

### Database Maintenance

```sql
-- Optimize tables
OPTIMIZE TABLE teletalk_organizations, teletalk_jobs, teletalk_cron_logs;

-- Check table status
SHOW TABLE STATUS LIKE 'teletalk_%';

-- Archive old jobs (older than 6 months)
DELETE FROM teletalk_jobs WHERE scraped_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

## Support

For issues or questions:
1. Check the logs first
2. Review this documentation
3. Test with verbose mode
4. Check database connectivity

## License

This system is part of the BroxLab project.
