# BROXBHAI — Update & Maintenance Instructions
**Project:** broxlab-repo | **User:** tdhuedhn | **Version:** 4.0

---

## নিয়মিত Update প্রক্রিয়া

### স্বয়ংক্রিয় Update (সাধারণ পদ্ধতি)
GitHub-এ `main` branch-এ push করলেই:
1. Webhook trigger হবে
2. Site files backup → `/home/tdhuedhn/repo/site_broxbhai_*/`
3. Database backup → `/home/tdhuedhn/repo/db/sitebroxbhai_*.sql`
4. `git pull` চলবে
5. Email/Slack notification আসবে (চালু থাকলে)

```bash
# Local machine থেকে সাধারণ deploy
git add .
git commit -m "আপনার পরিবর্তনের বিবরণ"
git push origin main
# → Webhook অটো trigger হবে
```

---

## github.php ফাইল Update করার নিয়ম

`github.php` তে কোনো পরিবর্তন করলে:

1. **cPanel → File Manager** → `/public_html/webhook/github.php` খুলুন
2. পরিবর্তন করুন (**শুধু Configuration section**, অর্থাৎ উপরের ৫০ লাইনের মধ্যে)
3. **Save** করুন
4. Test: `?action=status&api_key=KEY` দিয়ে যাচাই করুন

### কনফিগ পরিবর্তনের তালিকা

| কী পরিবর্তন করবেন | Variable | কখন করবেন |
|---|---|---|
| Branch পরিবর্তন | `$target_branch` | `main` → অন্য branch deploy করতে |
| Backup বন্ধ | `$create_backup = false` | Disk space কম হলে সাময়িক |
| Backup সংখ্যা | `$max_backups` | বেশি/কম রাখতে চাইলে |
| Email চালু | `$email_enabled = true` | Notification দরকার হলে |
| Slack চালু | `$slack_enabled = true` | Team notification দরকার হলে |
| IP Whitelist | `$enable_ip_whitelist = true` | অতিরিক্ত নিরাপত্তায় |

---

## Rollback করার পদ্ধতি

### Step 1 — Version তালিকা দেখুন
```
GET https://yourdomain.com/webhook/github.php?action=versions&api_key=আপনার_KEY
```

Response:
```json
{
  "versions": [
    { "version_tag": "v20240115120000", "commit_hash": "abc1234", "description": "Update header", "created_at": "2024-01-15 12:00:00" },
    { "version_tag": "v20240114090000", "commit_hash": "def5678", "description": "Fix bug", "created_at": "2024-01-14 09:00:00" }
  ]
}
```

### Step 2 — নির্দিষ্ট Version-এ ফিরুন
```
GET https://yourdomain.com/webhook/github.php?action=rollback&version=v20240114090000&api_key=আপনার_KEY
```

**⚠️ সতর্কতা:** Rollback করার আগে বর্তমান অবস্থার backup অটো তৈরি হয় (`_prerollback` নামে)।

### Manual Rollback (SSH দিয়ে)
```bash
# পুরনো commit-এ ফিরতে
cd /home/tdhuedhn/BROXBHAI
git log --oneline -10          # সব commit দেখুন
git reset --hard COMMIT_HASH   # নির্দিষ্ট commit-এ যান
```

---

## Backup ব্যবস্থাপনা

### Backup কোথায় আছে
```bash
# Site backup দেখুন
ls -lh /home/tdhuedhn/repo/site_broxbhai_*/

# DB backup দেখুন
ls -lh /home/tdhuedhn/repo/db/sitebroxbhai_*.sql
```

### পুরনো Backup মুছুন (Disk Space বাঁচাতে)
```bash
# সব site backup দেখুন
ls /home/tdhuedhn/repo/ | grep site_broxbhai

# নির্দিষ্ট backup মুছুন
rm -rf /home/tdhuedhn/repo/site_broxbhai_2024-01-10_120000

# নির্দিষ্ট DB backup মুছুন
rm /home/tdhuedhn/repo/db/sitebroxbhai_2024-01-10_120000.sql
```

### DB Backup থেকে Restore করুন
```bash
mysql -u your_db_user -p your_db_name < /home/tdhuedhn/repo/db/sitebroxbhai_2024-01-15_120000.sql
```

---

## নিরাপত্তা রক্ষণাবেক্ষণ

### Admin API Key পরিবর্তন (৩ মাস পরপর)
1. `github.php` খুলুন
2. `$admin_api_key` নতুন value দিন
3. Save করুন
4. নতুন key দিয়ে status test করুন

### Webhook Secret পরিবর্তন
1. `github.php` এ `$webhook_secret` পরিবর্তন করুন
2. GitHub → Webhooks → Edit → Secret-ও একই করুন
3. Save করুন

---

## Deployment Log দেখা

cPanel → phpMyAdmin → `deploy_webhook_logs` table:

```sql
-- সর্বশেষ ১০টি deployment
SELECT delivery_id, event_type, deployment_status, version_tag, created_at
FROM deploy_webhook_logs
ORDER BY created_at DESC
LIMIT 10;

-- শুধু ব্যর্থ deployment
SELECT * FROM deploy_webhook_logs
WHERE deployment_status = 'failed'
ORDER BY created_at DESC;
```

---

## Disk Space মনিটরিং

```bash
# মোট backup কত জায়গা নিচ্ছে
du -sh /home/tdhuedhn/repo/

# Site backup আলাদাভাবে
du -sh /home/tdhuedhn/repo/site_broxbhai_*/

# DB backup আলাদাভাবে
du -sh /home/tdhuedhn/repo/db/
```

---

## Quick Reference

| কাজ | Command/URL |
|---|---|
| Status দেখা | `?action=status&api_key=KEY` |
| Versions দেখা | `?action=versions&api_key=KEY` |
| Rollback | `?action=rollback&version=vXXX&api_key=KEY` |
| Manual pull | `cd /home/tdhuedhn/BROXBHAI && git pull origin main` |
| DB restore | `mysql -u user -p db < backup.sql` |
| Log দেখা | phpMyAdmin → deploy_webhook_logs |
