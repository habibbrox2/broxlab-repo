<?php

/**
 * Script to generate Telegram bot documentation
 */
$content = '# Telegram Bot System Documentation

## Overview
This document describes how to set up, configure, and use the Telegram bot system for BroxLab.

## Features
- SMS Gateway - Send and receive SMS through Telegram
- SIM Routing - Configure automatic SMS forwarding  
- Device Control - Control Android devices remotely
- Web Scraper - Scrape websites directly from Telegram
- PDF Tools - Merge and split PDF files

## Prerequisites
1. Telegram Bot Token - Get from @BotFather on Telegram
2. Public HTTPS URL - Your server must be accessible via HTTPS
3. MySQL Database - For storing sessions, logs, and user mappings
4. PHP 8.0+ - With cURL extension

## Installation

### Step 1: Run Database Migration
```
php scripts/migrate_telegram_rate_limit.php
```

This creates:
- telegram_sessions
- telegram_rate_limit  
- telegram_activity_log
- telegram_user_mapping

### Step 2: Configure Bot Token
1. Go to Admin Panel: /admin/telegram-settings
2. Enter your Bot Token from @BotFather
3. Set a Webhook Secret Token (or generate one)
4. Enter your Public HTTPS URL
5. Click Save Configuration

### Step 3: Set Webhook
Click Set Webhook Now button in the admin panel.

## Configuration

### Environment Variables
```
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_admin_chat_id
TELEGRAM_WEBHOOK_SECRET=your_secret_token
```

### Feature Flags
Enable/disable from /admin/feature-flags:
- telegram_panel - Main bot
- sms_gateway - SMS features
- remote_device - Device control
- sim_routing - SIM routing

## User Authorization

### Link Telegram Account
```sql
INSERT INTO telegram_user_mapping 
(telegram_user_id, telegram_username, user_id, is_authorized, is_admin)
VALUES (123456789, username, 1, 1, 1);
```

### Authorization Levels
- user - Basic access
- admin - Admin features
- super_admin - Full access

## Commands

### Bot Commands
- /start - Start the bot
- /menu - Show main menu

### Menu Options
1. SMS - Send SMS
2. Incoming - View SMS logs
3. Scraper - Scrape websites
4. Device - Control devices
5. PDF - PDF tools

## API Endpoints

- POST /api/telegram/webhook - Webhook
- GET /api/telegram/health - Health check

## Troubleshooting

### Webhook Not Working
1. Verify bot token
2. Check HTTPS URL
3. Check webhook secret
4. Review telegram_activity_log

### Rate Limiting
Check telegram_rate_limit table

### User Not Authorized
Check telegram_user_mapping table

## Security Best Practices
1. Always use HTTPS
2. Set webhook secret
3. Enable IP verification
4. Review logs regularly
';

file_put_contents('docs/TELEGRAM_BOT_DOCS.md', $content);
echo "Documentation created: docs/TELEGRAM_BOT_DOCS.md\n";
