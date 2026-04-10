# Scraper Diagnostic Script

## Overview
This document describes a diagnostic script designed to check existing scraper sources for issues and report findings. The script helps identify problematic sources, configuration issues, and recent failures.

## Purpose
The diagnostic script automates the process of:
- Listing all active scraper sources
- Checking overall system statistics
- Identifying recent failed jobs
- Reviewing error logs
- Finding sources that haven't been fetched recently

## Script Functionality

### 1. Active Sources Listing
- Retrieves all active scraper sources from the database
- Displays key information: ID, name, URL, type, content type, last fetched time
- Shows configuration status (selectors, advance config, presets)

### 2. Overall Statistics
- Total sources count
- Active sources count
- Total articles scraped
- Job statistics (by status: pending, running, completed, failed)
- Queue statistics (by status)

### 3. Recent Failed Jobs
- Identifies jobs that failed in the last 24 hours
- Shows job ID, source name, job type, timestamp, and error message
- Helps identify which sources are currently experiencing issues

### 4. Recent Error Logs
- Reviews error-level logs from the last 24 hours
- Provides detailed error messages and source information
- Includes any additional details stored in the log entries

### 5. Stale Sources Detection
- Finds active sources that haven't been successfully fetched in the last 24 hours
- Helps identify sources that may have silent failures (not throwing errors but not succeeding)

## Implementation Details

### Required Dependencies
- ScraperModel class for database operations
- DateTime for time comparisons
- Standard PHP functionality

### Key Methods Used
- `$model->getActiveSources()` - Get all active scraper sources
- `$model->getOverallStats()` - Get system-wide statistics
- `$model->getJobs()` - Retrieve jobs with filtering by status
- `$model->getLogSummary()` - Get error log counts
- `$model->getLogs()` - Retrieve detailed log entries
- `$model->getAllSources()` - Get all sources (active and inactive)

### Output Format
The script produces a structured report with clear sections:
1. Header with timestamp
2. Active sources list with configuration details
3. Overall system statistics
4. Recent failed jobs details
5. Recent error logs
6. Stale sources identification
7. Footer

## Usage
The diagnostic script can be:
1. **Run manually** by administrators to troubleshoot issues
2. **Scheduled** to run periodically and generate reports
3. **Integrated** into a web interface for easy access
4. **Extended** to include additional diagnostic checks

## Benefits
- **Quick Issue Identification** - Rapidly see which sources are problematic
- **Historical Tracking** - Monitor trends over time
- **Proactive Maintenance** - Identify issues before they cause data loss
- **Configuration Validation** - Verify that source configurations are intact
- **Performance Monitoring** - Track fetching success rates

## Next Steps for Implementation
1. Create the actual PHP script in the appropriate location (e.g., `scripts/diagnostics/scraper-diagnostic.php`)
2. Set up proper error handling and logging within the script
3. Create a web interface to run and view the diagnostic results
4. Add scheduling capabilities (cron job) for automated regular runs
5. Extend with additional checks (selector validation, connectivity testing, etc.)

## Example Output Sections
```
=== SCRAPER DIAGNOSTIC REPORT ===
Generated at: 2026-03-30 22:05:00

1. ACTIVE SCRAPER SOURCES
-------------------------
Total active sources: 5

Source ID: 1
Name: Prothom Alo
URL: https://www.prothomalo.com/
Type: html
Content Type: articles
Last Fetched: 2026-03-30 21:45:00
Fetch Interval: 1800 seconds
Selectors: Set
Advance Config: Not Set
Preset: prothomalo

2. OVERALL STATISTICS
---------------------
Total Sources: 8
Active Sources: 5
Total Articles: 12450

Jobs Stats:
  pending: 2
  running: 1
  completed: 45
  failed: 3

Queue Stats:
  pending: 10
  completed: 150
  failed: 3

3. RECENT FAILED JOBS (LAST 24 HOURS)
-------------------------------------
Found 3 failed jobs:

Job ID: 1245
Source: BDNews24
Job Type: scrape
Created: 2026-03-30 21:30:00
Error: Timeout was reached

Job ID: 1246
Source: GSMArena BD
Job Type: scrape
Created: 2026-03-30 21:15:00
Error: CSS selector '.device-title' returned no elements

4. RECENT ERROR LOGS (LAST 24 HOURS)
------------------------------------
Total error logs: 8

Time: 2026-03-30 21:30:00
Source: BDNews24
Message: Timeout was reached
Details: {"url":"https://www.bdnews24.com/","timeout":30}

5. SOURCES NOT FETCHED IN LAST 24 HOURS
---------------------------------------
Found 2 stale sources:

Source ID: 3
Name: Daily Star
URL: https://www.thedailystar.net/
Last Fetched: 2026-03-29 20:30:00

=== END OF REPORT ===
```

## Files to Create
- `scripts/diagnostics/scraper-diagnostic.php` - Actual PHP script
- `app/Views/scraper/diagnostics/run.twig` - Web interface to execute script
- `app/Views/scraper/diagnostics/results.twig` - Web interface to view results
- Routes in ScraperController for diagnostic endpoints