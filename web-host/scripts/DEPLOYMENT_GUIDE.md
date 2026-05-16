# BroxLab ডেপ্লয়মেন্ট সেটআপ গাইড

**আপডেট করা হয়েছে**: May 15, 2026  
**ভাষা**: বাংলা

---

## 1. ভূমিকা

এই গাইডটি ব্রোক্সল্যাবের প্রোডাকশন ডিপ্লয় সেটআপ শুরু থেকে শেষ পর্যন্ত ব্যাখ্যা করে। লক্ষ্য হচ্ছে সার্ভার প্রস্তুতি, গিট ক্লোন সমস্যা, ডিপ্লয় স্ক্রিপ্ট ব্যবহার, ব্যাকআপ, ক্লিনআপ এবং রোলব্যাক কার্যকর করা।

## 2. পূর্বশর্ত

সার্ভারে নিম্নলিখিত কমান্ড ইনস্টল থাকতে হবে:

- `bash`
- `git`
- `node`
- `npm`
- `php`
- `mysqldump`
- `mysql`
- `curl` বা `wget`
- `jq` (ঐচ্ছিক)

আরও:

- `github.com` ঠিকভাবে DNS থেকে রেজোলভ করা
- ডাটাবেস অ্যাক্সেস যোগ্যতা
- ফাইল লেখার অনুমতি

## 3. ফোল্ডার স্ট্রাকচার

প্রধান ফোল্ডার কাঠামো:

```
/home/tdhuedhn/broxlab/
├── app/
│   ├── current -> releases/<timestamp>
│   ├── releases/
│   └── shared/
│       ├── .env
│       ├── backups/
│       │   ├── code/
│       │   └── database/
│       └── storage/
└── logs/
```

## 4. স্ক্রিপ্ট সমূহ ও তাদের কাজ

### 4.1 `deploy.sh`

- প্রধান ডিপ্লয় স্ক্রিপ্ট
- কোড ক্লোন করে নতুন রিলিজ তৈরি করে
- পুরাতন নোড সার্ভার বন্ধ করে
- নতুন রিলিজে সিমলিঙ্ক করে
- `USE_HTTPS=true` দিলে HTTPS ক্লোন ফোর্স করে
- SSH ক্লোন ব্যর্থ হলে HTTPS ফallback করে

### 4.2 `backup.sh`

- বর্তমান রিলিজের কোড ব্যাকআপ তৈরি করে
- পুরনো ব্যাকআপ স্বয়ংক্রিয়ভাবে মুছে ফেলে

### 4.3 `cleanup.sh`

- পুরানো রিলিজ, ব্যাকআপ, ডাটাবেস ব্যাকআপ এবং লগ ফাইল পরিষ্কার করে
- বিভিন্ন রিটেনশন নিয়ম অনুসারে কাজ করে

### 4.4 `database-backup.sh`

- `mysqldump` দিয়ে ডাটাবেস ব্যাকআপ তৈরি করে
- ব্যাকআপ ফাইলটি gzip ফরম্যাটে সংরক্ষণ করে
- টেম্প ফাইল ব্যবহার করে সেফ ব্যাকআপ তৈরি করে

### 4.5 `database-restore.sh`

- ব্যাকআপ থেকে ডাটাবেস পুনরুদ্ধার করে
- রিস্টোরের আগে ব্যাকআপ যাচাই করে
- ইন্টারেক্টিভ কনফার্মেশন চায়

### 4.6 `rollback.sh`

- পূর্ববর্তী রিলিজে দ্রুত ফেরত নিয়ে আসে
- রোলব্যাকের আগে নিরাপদ ব্যাকআপ তৈরি করে
- পুনরায় নোড সার্ভার চালু করে

## 5. `.env` কনফিগারেশন

`app/shared/.env` বা `app/shared/.env` এ অন্তত নিম্নোক্ত ভ্যালু থাকতে হবে:

```
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=broxlab
```

সাধারণ সিক্রেট:

- `JWT_SECRET`
- `CSRF_SECRET`
- `NODE_SERVICE_API_KEY`

`deploy.sh` যদি সিক্রেট খুঁজে না পায়, তাহলে তা নিজে জেনারেট করে নেবে।

## 6. সার্ভার পারমিশন

```bash
mkdir -p /home/tdhuedhn/broxlab/app/releases
mkdir -p /home/tdhuedhn/broxlab/app/shared/backups/code
mkdir -p /home/tdhuedhn/broxlab/app/shared/backups/database
mkdir -p /home/tdhuedhn/broxlab/app/shared/storage
mkdir -p /home/tdhuedhn/broxlab/logs
chmod -R 775 /home/tdhuedhn/broxlab
```

> নোট: ইউজার ও গ্রুপ আপনার সার্ভারের সাথে মিলিয়ে পরিবর্তন করুন।

## 7. GitHub Actions ডিপ্লয় সেটআপ

`.github/workflows/deploy.yml` GitHub থেকে সার্ভারে SSH করে ডিপ্লয় চালায়।

### 7.1 প্রয়োজনীয় গোপনীয়তা

GitHub repository সিক্রেটস:

- `HOST` - সার্ভারের হোস্টনেম বা আইপি
- `USER` - SSH ইউজার
- `SSH_KEY_BASE64` - base64-এ এনকোড করা প্রাইভেট SSH কী
- `SSH_KEY_PASSPHRASE` - (ঐচ্ছিক) যদি আপনার প্রাইভেট কী পাসফ্রেজ দ্বারা সুরক্ষিত থাকে

### 7.2 আপডেট করা ডিপ্লয় কমান্ড

ওয়ার্কফ্লোতে এখন নিম্নোক্ত কমান্ড পাঠানো হচ্ছে:

```bash
cd /home/tdhuedhn/broxlab
export USE_HTTPS=true
bash web-host/scripts/deploy.sh
```

এটি GitHub SSH ক্লোন সমস্যা থাকলে HTTPS ফallback সক্ষম করে।

## 8. ডিপ্লয় প্রক্রিয়া

### 8.1 প্রথম ডিপ্লয়

1. সার্ভারে মূল ডিরেক্টরি তৈরি করুন
2. ফোল্ডার কাঠামো তৈরি করুন
3. `shared/.env` তৈরি করুন
4. GitHub সিক্রেট কনফিগার করুন
5. `main` ব্রাঞ্চ পুশ করুন
6. GitHub Actions চালু হলে ডিপ্লয় শুরু হবে

### 8.2 ম্যানুয়াল ডিপ্লয়

```bash
cd /home/tdhuedhn/broxlab
bash web-host/scripts/deploy.sh
```

### 8.3 ট্রায়াল মোড

```bash
bash web-host/scripts/deploy.sh --dry-run
```

## 9. GitHub ক্লোন সমস্যা সমাধান

`ssh: Could not resolve hostname github.com` ত্রুটি সাধারণত নির্দেশ করে:

- DNS সমস্যা
- ইন্টারনেট নেটওয়ার্ক ইস্যু
- গিট ক্লোন জন্য SSH ব্লক

### সমাধান

- নিশ্চিত করুন যে আপনার পাসফ্রেজ-প্রোটেক্টেড কী যদি ব্যবহার করেন, তাহলে `SSH_KEY_PASSPHRASE` গিটহাব সিক্রেটস-এ সেট করা আছে।
- যদি সার্ভার `publickey` অস্বীকার করে এবং পরবর্তীতে `password` প্রম্পট করে, তাহলে `~/.ssh/authorized_keys`-এ সঠিক পাবলিক কী আছে কিনা এবং ফাইল অনুমতিগুলি সঠিক কিনা তা চেক করুন।

```bash
nslookup github.com
ping -c 1 github.com
```

`deploy.sh` এখন HTTPS ফallback সমর্থন করে। যদি SSH ক্লোন ব্যর্থ হয়, তাহলে এটি HTTPS URL এ পুনরায় চেষ্টা করবে।

- যদি সার্ভার `github.com` DNS রেজোলভিং করতে না পারে, স্ক্রিপ্ট এখন GitHub SSH IP ফallback চেষ্টা করবে।

> যদি সার্ভার `github.com` রেজোলভই না করতে পারে, তাহলে প্রথমে DNS ও নেটওয়ার্ক ঠিক করতে হবে।

## 10. ব্যাকআপ ও রোলব্যাক কমান্ড

### 10.1 কোড ব্যাকআপ

```bash
bash web-host/scripts/backup.sh --keep 5
```

### 10.2 ডাটাবেস ব্যাকআপ

```bash
bash web-host/scripts/database-backup.sh --keep 5
```

### 10.3 রোলব্যাক

```bash
bash web-host/scripts/rollback.sh
```

## 11. সাধারণ সমস্যা ও সমাধান

- `git` না থাকলে ডিপ্লয় কাজ করবে না
- `mysqldump` না থাকলে ডাটাবেস ব্যাকআপ ব্যর্থ হবে
- যদি `npm run build:prod` ব্যর্থ হয়, `node_modules` পুনরায় ইনস্টল করুন
- `USE_HTTPS=true` দিয়ে HTTPS ক্লোন ব্যবহার করুন

## 12. সারাংশ

এই গাইডটি শুরু থেকে শেষ পর্যন্ত ব্রোক্সল্যাবের প্রোডাকশন ডিপ্লয় সেটআপ ব্যাখ্যা করে। এখন ডিপ্লয় স্ক্রিপ্ট SSH গিট ক্লোন সমস্যার জন্য HTTPS ফallback সহ কাজ করবে এবং GitHub Actions ডিপ্লয় কমান্ডে `USE_HTTPS=true` পাঠানো হচ্ছে।

ডিপ্লয় কাজের প্রধান চাবিকাঠি হচ্ছে সঠিক সার্ভার DNS, `.env` কনফিগারেশন, এবং পর্যাপ্ত পারমিশন।
