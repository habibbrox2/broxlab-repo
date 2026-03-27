# cPanel WebHost এ Cron Job সেটআপ (BroxLab / BroxBhai)

## খুব গুরুত্বপূর্ণ (Invalid crontab / "bad command" এড়াতে)

`docs/CPANEL_CRONJOBS.md` ফাইলটা **ডকুমেন্টেশন**—এটা কখনোই `crontab docs/CPANEL_CRONJOBS.md` দিয়ে install করবেন না।

`"-":3: bad command` / `Invalid crontab file, can't install.` সাধারণত এই কারণে হয়:
- আপনি Markdown টেক্সট (যেমন `-` bullet, `---`, ```bash code fence, heading) সহ কোনো ফাইলকে `crontab` হিসেবে install দিয়েছেন
- cPanel Cron Jobs UI-র **Command** বক্সে পুরো cron line (`*/15 * * * * ...`) পেস্ট করেছেন

সমাধান:
- **cPanel UI হলে:** Schedule আলাদা করে সেট করবেন, **Command বক্সে শুধু command অংশ** দিবেন (cron schedule `*/15 * * * *` এখানে দিবেন না)
- **SSH/Server crontab হলে:** crontab ফাইলে **শুধু cron লাইন** থাকবে (এক লাইনে schedule + command)

Windows থেকে কপি-পেস্ট করলে line ending (CRLF) সমস্যা করতে পারে। চেষ্টা করুন **LF** রাখতে:
- VS Code: status bar এ "CRLF" → "LF"
- অথবা server এ: `dos2unix cron_jobs.txt` (যদি available থাকে)

এই ডকুমেন্টে cPanel (shared web hosting) এ Cron Job সেট করে `scripts/` এর CLI PHP worker গুলো চালানোর নিয়ম দেখানো হলো—বিশেষ করে AutoContent pipeline (collect → process → publish → retry)।

> নোট: cPanel সার্ভারে path/`php` বাইনারি location হোস্টভেদে আলাদা হয়। নিচের কমান্ডে `YOUR_USER` এবং `YOUR_APP_PATH` ঠিক করে বসাবেন।

---

## 1) দরকার হবে (Prerequisites)

1. **প্রোজেক্টের absolute path** (Linux): সাধারণত এরকম হয়:
   - `/home/YOUR_USER/public_html/...` অথবা
   - `/home/YOUR_USER/your-project/...`

2. **PHP CLI path**: অনেক cPanel এ থাকে:
   - `/usr/local/bin/php` (কমন)
   - `/usr/bin/php`
   - অথবা শুধু `php` (যদি PATH এ available থাকে)

3. **Node path** (Scraper চালাতে): অনেক host এ থাকে:
   - `/usr/local/bin/node`
   - `/usr/bin/node`
   - অথবা শুধু `node`

**কীভাবে বের করবেন**
- **SSH থাকলে:** `which php`, `php -v`, `which node`, `node -v`
- **SSH না থাকলে:** cPanel → **Software** → **Select PHP Version** / **MultiPHP Manager** থেকে PHP version দেখুন, অথবা হোস্ট সাপোর্টে জিজ্ঞেস করুন "PHP CLI binary path"।

---

## 2) cPanel এ Cron Jobs কোথায়

1. cPanel লগইন করুন  
2. **Advanced** → **Cron Jobs**  
3. "Add New Cron Job" সেকশনে:
   - Schedule (minute/hour/day/month/weekday) সেট করুন  
   - Command বসান (**শুধু command অংশ**; `*/15 * * * *` এখানে দিবেন না)  
   - চাইলে Cron output email সেট করুন (Debug এর জন্য ভালো; পরে log redirect করে email বন্ধ রাখুন)

---

## 3) Recommended Cron (AutoContent Pipeline)

এই repo তে admin panel এও recommended crontab দেখানো আছে (AutoContent Settings page)। সাধারণত এই ৪টা স্ক্রিপ্টই লাগবে:

- `scripts/autocontent_collect.php`
- `scripts/autocontent_process.php`
- `scripts/autocontent_publish.php`
- `scripts/autocontent_retry.php`

### Option A — Basic (email spam কমাতে log file এ redirect)

`YOUR_APP_PATH` উদাহরণ: `/home/YOUR_USER/broxlab`

#### A1) cPanel UI (Recommended) — "Command" field only (schedule UI থেকে সেট করবেন)

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (collect):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_collect.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
```

Command (process):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_process.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
```

Command (publish):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_publish.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
```

Command (retry):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_retry.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
```

Recommended schedules:
- collect: `*/15 * * * *`
- process: `*/10 * * * *`
- publish: `*/5 * * * *`
- retry: `*/30 * * * *`

#### A2) Linux crontab (SSH) — full cron lines (schedule + command)

```cron
*/15 * * * * /usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_collect.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
*/10 * * * * /usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_process.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
*/5  * * * * /usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_publish.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
*/30 * * * * /usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/autocontent_retry.php >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_autocontent.log 2>&1
```

#### Template (placeholders)

```cron
*/15 * * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/autocontent_collect.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_autocontent.log 2>&1
*/10 * * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/autocontent_process.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_autocontent.log 2>&1
*/5  * * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/autocontent_publish.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_autocontent.log 2>&1
*/30 * * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/autocontent_retry.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_autocontent.log 2>&1
```

> যদি `/usr/local/bin/php` না কাজ করে, `/usr/bin/php` বা `php` ট্রাই করুন।

### Option B — Overlap আটকাতে `flock` (যদি সার্ভারে available থাকে)

কখনও স্ক্রিপ্ট বেশি সময় নিলে একই cron আবার run হয়ে overlap হতে পারে। সেক্ষেত্রে `flock` ব্যবহার করলে একসাথে ১টা run নিশ্চিত করা যায়।

```bash
*/10 * * * * flock -n /tmp/brox_autocontent_process.lock /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/autocontent_process.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_autocontent.log 2>&1
```

---

## 4) BDNews24 Scheduler (ঐচ্ছিক)

যদি `scripts/bdnews24-scheduler.php` ব্যবহার করেন:

```bash
* * * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/bdnews24-scheduler.php >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/scraper.log 2>&1
```

---

## 5) GSMArena Scraper (News + Devices)

এই প্রোজেক্টে Node-based scraper আছে (`src/scraper/index.js`) যেটা GSMArena থেকে:
- `gsmarena_news` → `autocontent_articles` এ collect করে (পরে existing `autocontent_process.php` + `autocontent_publish.php` দিয়ে posts হবে)
- `gsmarena_devices` → `mobiles/mobile_specs/mobile_images` এ সরাসরি insert করে
- `gsmarena_bd_devices` → `https://www.gsmarena.com.bd/` থেকে `mobiles/mobile_specs/mobile_images` এ সরাসরি insert করে (BDT price mapping)
- `thedailystar_today` → `https://bangla.thedailystar.net/todays-news` থেকে `autocontent_articles` এ collect করে (পরে process/publish cron দিয়ে posts হবে)

### Recommended cron (shared hosting friendly)

#### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Commands (paste into "Command" field):
```bash
/usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_news --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_news.log 2>&1
```
```bash
/usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_devices --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_devices.log 2>&1
```
```bash
/usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_bd_devices --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_bd_devices.log 2>&1
```
```bash
/usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=thedailystar_today --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_thedailystar_today.log 2>&1
```

Recommended schedules (Assumption):
- `gsmarena_news`: `*/15 * * * *`
- `thedailystar_today`: `*/15 * * * *`
- `gsmarena_devices`: `0 * * * *`
- `gsmarena_bd_devices`: `30 * * * *`

#### Linux crontab (SSH) — full cron lines (schedule + command)
```cron
*/15 * * * * /usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_news --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_news.log 2>&1
0 * * * * /usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_devices --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_devices.log 2>&1
30 * * * * /usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=gsmarena_bd_devices --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_gsmarena_bd_devices.log 2>&1
*/15 * * * * /usr/local/bin/node /home/tdhuedhn/broxlab/app/current/src/scraper/index.js --source=thedailystar_today --max=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/cron_thedailystar_today.log 2>&1
```

#### Template (placeholders)
```cron
*/15 * * * * /usr/local/bin/node /home/YOUR_USER/YOUR_APP_PATH/src/scraper/index.js --source=gsmarena_news --max=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_gsmarena_news.log 2>&1
0 * * * * /usr/local/bin/node /home/YOUR_USER/YOUR_APP_PATH/src/scraper/index.js --source=gsmarena_devices --max=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_gsmarena_devices.log 2>&1
30 * * * * /usr/local/bin/node /home/YOUR_USER/YOUR_APP_PATH/src/scraper/index.js --source=gsmarena_bd_devices --max=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_gsmarena_bd_devices.log 2>&1
*/15 * * * * /usr/local/bin/node /home/YOUR_USER/YOUR_APP_PATH/src/scraper/index.js --source=thedailystar_today --max=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/cron_thedailystar_today.log 2>&1
```

> নোট: `node` এর path হোস্টিংভেদে আলাদা হতে পারে (`/usr/local/bin/node`, `/usr/bin/node`, বা শুধু `node`)।

---

## 6) Logging & Permissions

1. Log ফাইলের জন্য ফোল্ডার থাকা দরকার:
   - `storage/logs/`
2. Shared hosting এ permission ইস্যু হলে `storage/` এবং `storage/logs/` writeable কিনা দেখুন।
3. Debug করার সময় cron output email অনরাখলে error দ্রুত ধরতে পারবেন; stable হলে log redirect করে email বন্ধ রাখুন।

---

## 7) Common সমস্যা ও সমাধান (Troubleshooting)

### "php: command not found"
- `php` এর জায়গায় full path দিন: `/usr/local/bin/php` বা `/usr/bin/php`

### "Permission denied"
- `storage/logs/` writeable কিনা দেখুন
- Script path ঠিক কিনা নিশ্চিত করুন

### Script চলেছে কিনা বুঝবো কীভাবে?
- `storage/logs/cron_autocontent.log` এ নতুন লাইন আসছে কিনা দেখুন
- cPanel Cron Jobs এর "Email" এ output আসছে কিনা দেখুন

### Overlap / duplicate run হচ্ছে
- Interval কমিয়ে দিন, অথবা Option B (`flock`) ব্যবহার করুন

---

## 8) Teletalk Jobs Scraper

Teletalk government jobs scraper দিয়ে সরকারি চাকরির বিজ্ঞপ্তি স্বয়ংক্রিয়ভাবে সংগ্রহ করা যায়।

### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (daily at 6 AM):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/teletalk-scraper.log 2>&1
```

Command (twice daily):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/teletalk-scraper.log 2>&1
```

Recommended schedules:
- Daily: `0 6 * * *` (6 AM daily)
- Twice daily: `0 6,18 * * *` (6 AM and 6 PM)
- Every 6 hours: `0 */6 * * *`

### Linux crontab (SSH) — full cron lines (schedule + command)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/teletalk-scraper.log 2>&1
```

### Template (placeholders)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/teletalk-scraper.log 2>&1
```

### Command Options

- `--max-pages=N`: Maximum number of pages to scrape (1-10, default: 3)
- `--verbose`: Enable verbose output for debugging

Example with options:
```bash
/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/teletalk-scraper.php --max-pages=5 --verbose >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/teletalk-scraper.log 2>&1
```

### Features

- Automatic duplicate detection
- Email notifications to admins when new jobs are found
- Detailed logging to `logs/teletalk-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications

---

## 9) MobileDokan Phone Scraper

MobileDokan phone scraper দিয়ে মোবাইল ফোনের তথ্য স্বয়ংক্রিয়ভাবে সংগ্রহ করা যায়।

### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (daily at 6 AM):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/mobiledokan-scraper.log 2>&1
```

Command (twice daily):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/mobiledokan-scraper.log 2>&1
```

Recommended schedules:
- Daily: `0 6 * * *` (6 AM daily)
- Twice daily: `0 6,18 * * *` (6 AM and 6 PM)
- Every 6 hours: `0 */6 * * *`

### Linux crontab (SSH) — full cron lines (schedule + command)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/mobiledokan-scraper.log 2>&1
```

### Template (placeholders)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/mobiledokan-scraper.log 2>&1
```

### Command Options

- `--max-pages=N`: Maximum number of pages to scrape (1-10, default: 3)
- `--verbose`: Enable verbose output for debugging

Example with options:
```bash
/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/mobiledokan-scraper.php --max-pages=5 --verbose >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/mobiledokan-scraper.log 2>&1
```

### Features

- Automatic duplicate detection
- Email notifications to admins when new phones are found
- Detailed logging to `logs/mobiledokan-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Bengali text encoding support
- JavaScript embedded data extraction

---

## 10) BDNews24 Article Scraper

BDNews24 article scraper দিয়ে bangla.bdnews24.com থেকে বাংলা নিউজ আর্টিকেল স্বয়ংক্রিয়ভাবে সংগ্রহ করা যায়।

### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (daily at 6 AM):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/bdnews24-scraper.log 2>&1
```

Command (twice daily):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/bdnews24-scraper.log 2>&1
```

Recommended schedules:
- Daily: `0 6 * * *` (6 AM daily)
- Twice daily: `0 6,18 * * *` (6 AM and 6 PM)
- Every 6 hours: `0 */6 * * *`

### Linux crontab (SSH) — full cron lines (schedule + command)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/bdnews24-scraper.log 2>&1
```

### Template (placeholders)

```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/bdnews24-scraper.log 2>&1
```

### Command Options

- `--max-pages=N`: Maximum number of pages to scrape (1-20, default: 10)
- `--verbose`: Enable verbose output for debugging

Example with options:
```bash
/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/bdnews24-scraper.php --max-pages=10 --verbose >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/bdnews24-scraper.log 2>&1
```

### Features

- Automatic duplicate detection
- Email notifications to admins when new articles are found
- Detailed logging to `logs/bdnews24-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Bengali text encoding support (UTF-8)
- Cursor-based pagination for infinite scroll
- Category and published date extraction

---

## 11) GSMArena News Scraper

Automated scraping of GSMArena news articles from gsmarena.com/news

### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (every 6 hours):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/gsmarena-news-scraper.php --max-pages=5 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/gsmarena-news-scraper.log 2>&1
```

Command (daily):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/gsmarena-news-scraper.php --max-pages=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/gsmarena-news-scraper.log 2>&1
```

Recommended schedules:
- Every 6 hours: `0 */6 * * *` (recommended for news)
- Every 12 hours: `0 */12 * * *`
- Daily: `0 6 * * *` (6 AM daily)

### Linux crontab (SSH) — full cron lines (schedule + command)

```cron
0 */6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-news-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-news-scraper.log 2>&1
```

### Template (placeholders)

```cron
0 */6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-news-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-news-scraper.log 2>&1
```

### Command Options

- `--max-pages=N`: Maximum number of pages to scrape (1-20, default: 5)
- `--verbose`: Enable verbose output for debugging

Example with options:
```bash
/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-news-scraper.php --max-pages=10 --verbose >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-news-scraper.log 2>&1
```

### Features

- Automatic duplicate detection
- Email notifications to admins when new articles are found
- Detailed logging to `logs/gsmarena-news-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- News ID extraction from URL
- Date parsing with multiple format support
- Rate limiting (1 second delay between requests)

---

## 12) GSMArena Devices Scraper

Automated scraping of GSMArena mobile devices from gsmarena.com

### cPanel UI (Command field only) — schedule UI থেকে সেট করবেন

Example paths:
- Current release: `/home/tdhuedhn/broxlab/app/current`
- Shared logs: `/home/tdhuedhn/broxlab/app/shared/storage/logs`

Command (daily at 2 AM):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/gsmarena-devices-scraper.php --max-pages=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/gsmarena-devices-scraper.log 2>&1
```

Command (twice daily):
```bash
/usr/local/bin/php /home/tdhuedhn/broxlab/app/current/scripts/cron/gsmarena-devices-scraper.php --max-pages=10 >> /home/tdhuedhn/broxlab/app/shared/storage/logs/gsmarena-devices-scraper.log 2>&1
```

Recommended schedules:
- Daily: `0 2 * * *` (2 AM daily - recommended)
- Twice daily: `0 2,14 * * *` (2 AM and 2 PM)
- Every 12 hours: `0 */12 * * *`

### Linux crontab (SSH) — full cron lines (schedule + command)

```cron
0 2 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-devices-scraper.php --max-pages=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-devices-scraper.log 2>&1
```

### Template (placeholders)

```cron
0 2 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-devices-scraper.php --max-pages=10 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-devices-scraper.log 2>&1
```

### Command Options

- `--max-pages=N`: Maximum number of pages to scrape (1-50, default: 10)
- `--verbose`: Enable verbose output for debugging

Example with options:
```bash
/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/gsmarena-devices-scraper.php --max-pages=20 --verbose >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/gsmarena-devices-scraper.log 2>&1
```

### Features

- Automatic duplicate detection
- Email notifications to admins when new devices are found
- Detailed logging to `logs/gsmarena-devices-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Specification extraction from device pages
- Brand extraction from device name (50+ brands supported)
- URL-friendly slug generation
- Rate limiting (2 second delay between requests)

---

## 13) Security Notes

- Cron command এ কখনও **API key/token** লিখবেন না।
- Log ফাইল public web root এ expose হচ্ছে কিনা খেয়াল করুন (সাধারণত `storage/` webroot-এর বাইরে/প্রটেক্টেড রাখা ভালো)।
- অপ্রয়োজনেখুব frequent cron দিবেন না (shared hosting এ resource limit থাকতে পারে)।
