# BroxBhai Repository Sync Guide

বহুস্থানে (অফিস ও বাসায়) একটি প্রজেক্টে কাজ করার জন্য এই গাইড অনুসরণ করুন।

---

## সেটআপ (প্রথমবার)

### ১. GitHub Repo URL সেট করুন

`.env` ফাইল খুলুন এবং আপনার GitHub repository URL আপডেট করুন:

```env
# .env
GITHUB_REPO_URL=https://github.com/habibbrox2/broxlab.git
```

**উদাহরণ:**
```env
GITHUB_REPO_URL=https://github.com/habibbrox2/broxlab.git
```

### ২. স্থান ১-এ সম্পূর্ণ প্রজেক্ট কনফিগার করুন

অফিসে প্রথম সেটআপ:
```bash
npm install
npm run build
```

---

## দৈনন্দিন কর্মপ্রবাহ

### **অফিসে কাজ শেষে (স্থান ১)**

সব পরিবর্তন সংরক্ষণ ও পুশ করুন:

```bash
# ১. সব ফাইল স্টেজ করুন এবং কমিট করুন
npm run git push main "অফিসে কাজ সম্পূর্ণ"

# ২. ডাটাবেজ ব্যাকআপ নিন (ঐচ্ছিক)
# MySQL থেকে ডাটাবেজ এক্সপোর্ট করুন:
mysqldump -u [user] -p [database_name] > Database/backups/backup_$(date +%Y%m%d_%H%M%S).sql

# ৩. ব্যাকআপ ফাইল ও অন্যান্য ফাইল পুশ করুন
npm run git push main "ডাটাবেজ ব্যাকআপ + ফাইল আপডেট"
```

### **বাসায় পৌঁছে (স্থান ২)**

সর্বশেষ কোড ও ডাটাবেজ সিঙ্ক করুন:

#### **প্রথমবার (নতুন ক্লোন):**
```bash
# ⚠️  WARNING: এই কমান্ড বর্তমান ডিরেক্টরির tracked ফাইলগুলো রিমুভ করে দেবে!
# পুরো প্রজেক্ট ক্লোন করুন (auto setup সহ)
npm run sync

# অথবা ম্যানুয়ালি:
node git-hr/sync-clone.cjs

# .env ফাইল আপডেট করুন (লোকাল ডাটাবেজ ক্রেডেনশিয়ালস)
# উদাহরণ:
# DB_HOST=localhost
# DB_USER=root
# DB_PASS=your_password
```

**⚠️ গুরুত্বপূর্ণ:** `npm run sync` কমান্ড বর্তমান ডিরেক্টরির সব tracked ফাইল (যেগুলো GitHub-এ পুশ হয়) রিমুভ করে তারপর সরাসরি বর্তমান ডিরেক্টরিতে ক্লোন করে। এটি কোন সাবফোল্ডারে ক্লোন করে না।

#### **দ্বিতীয় ও পরবর্তী বার (ইতিমধ্যে ক্লোন করা):**
```bash
# সর্বশেষ পরিবর্তন পুল করুন
npm run git pull main

# ডাটাবেজ ব্যাকআপ ইমপোর্ট করুন (যদি নতুন ব্যাকআপ থাকে)
mysql -u [user] -p [database_name] < Database/backups/[backup_file].sql

# নতুন নির্ভরতা ইনস্টল করুন
npm install
composer install

# বিল্ড করুন
npm run build
```

### **বাসায় কাজ শেষে (স্থান ২)**

কোড পুশ করুন যাতে অফিসে সিঙ্ক করতে পারেন:

```bash
npm run git push main "বাসায় কাজ সম্পূর্ণ"
```

### **অফিসে ফিরে (স্থান ১)**

সর্বশেষ পরিবর্তন পুল করুন:

```bash
# সর্বশেষ কোড পুল করুন
npm run git pull main

# নতুন নির্ভরতা ইনস্টল করুন
npm install

# ডাটাবেজ ব্যাকআপ ইমপোর্ট করুন (যদি নতুন ব্যাকআপ থাকে)
mysql -u [user] -p [database_name] < Database/backups/[backup_file].sql
```

---

## Git Helper কমান্ডস

### **ক্লোন করুন**

```bash
# .env থেকে URL রিড করে ক্লোন করুন
npm run git clone

# নির্দিষ্ট URL ব্যবহার করে ক্লোন করুন
npm run git clone https://github.com/user/repo.git

# নির্দিষ্ট ডাইরেক্টরিতে ক্লোন করুন
npm run git clone https://github.com/user/repo.git my-folder
npm run git cl https://github.com/user/repo.git my-folder
```

### **কমিট ও পুশ করুন**

```bash
# অটোম্যাটিক কমিট মেসেজ সহ
npm run git push main

# কাস্টম মেসেজ সহ
npm run git push main "feat: নতুন ফিচার যোগ করা"

# ফোর্স পুশ (safe - force-with-lease)
npm run git push main "fix: bug" --force
```

### **পুল করুন**

```bash
npm run git pull main
```

### **ব্র্যাঞ্চ ম্যানেজমেন্ট**

```bash
# নতুন ব্র্যাঞ্চ তৈরি করুন
npm run git new feature/auth-system

# নির্দিষ্ট বেস থেকে
npm run git new feature/auth --from develop

# ব্র্যাঞ্চ সুইচ করুন
npm run git co main

# সব ব্র্যাঞ্চ দেখুন
npm run git brnh

# ব্র্যাঞ্চ ডিলিট করুন
npm run git del old-feature --remote
```

### **মার্জ করুন**

```bash
# সাধারণ মার্জ
npm run git mg feature/auth main

# স্কোয়াশ মার্জ
npm run git mg feature/auth main --squash
```

### **স্ট্যাটাস ও লগ**

```bash
# রিপোজিটরি স্ট্যাটাস দেখুন
npm run git st

# সম্প্রতি কমিটস দেখুন (ডিফল্ট 10)
npm run git lg

# ২০টি কমিট দেখুন
npm run git lg --n 20
```

### **অন্যান্য**

```bash
# শেষ কমিট আন্ডু করুন (soft - পরিবর্তন saved থাকে)
npm run git un

# হার্ড আন্ডু (পরিবর্তন discard হবে)
npm run git un --hard

# স্টেশ করুন
npm run git sh save "WIP: কাজ চলছে"
npm run git sh pop
npm run git sh list

# ডিফ দেখুন
npm run git df
npm run git df main
```

---

## সাধারণ সমস্যা সমাধান

### **কনফ্লিক্ট হলে কি করব?**

যদি `pull` বা `merge` করার সময় কনফ্লিক্ট হয়:

```bash
# কনফ্লিক্ট রিজলভ করুন (ম্যানুয়ালি ফাইল এডিট করুন)
# তারপর:
npm run git push main "কনফ্লিক্ট সমাধান"
```

### **ভুলবশত কমিট করলে?**

```bash
# সফট আন্ডু (পরিবর্তন রাখে)
npm run git un

# অথবা হার্ড আন্ডু (সবকিছু ডিসকার্ড)
npm run git un --hard
```

### **একাধিক কমিট একসাথে করতে চান?**

```bash
# Squash merge ব্যবহার করুন
npm run git mg feature/branch main --squash
```

---

## সেরা অনুশীলন

✅ **করুন:**
- প্রতিদিনের শেষে পুশ করুন
- পরিষ্কার কমিট মেসেজ লিখুন
- ডাটাবেজ ব্যাকআপ নিয়মিত রাখুন
- প্রধান পরিবর্তনের আগে পুল করুন

❌ **করবেন না:**
- `.env` ফাইল কখনও পুশ করবেন না (already in .gitignore)
- সরাসরি `main` এ কাজ করবেন না (feature branches ব্যবহার করুন)
- বড় ডাটাবেজ ডাম্প সরাসরি কমিট করবেন না (backups/ ডাইরেক্টরিতে রাখুন)

---

## দ্রুত রেফারেন্স

| কাজ | কমান্ড |
|-----|--------|
| সিঙ্ক সেটআপ | `npm run sync` |
| ক্লোন করুন | `npm run git clone` |
| পুশ করুন | `npm run git push main "মেসেজ"` |
| পুল করুন | `npm run git pull main` |
| স্ট্যাটাস | `npm run git st` |
| লগ | `npm run git lg` |
| কমিটস দেখুন | `npm run git lg --n 20` |
| নতুন ব্র্যাঞ্চ | `npm run git new feature/name` |
| সুইচ ব্র্যাঞ্চ | `npm run git co branch-name` |
| মার্জ | `npm run git mg source target` |
| সাহায্য | `npm run git -- --help` |

---

**প্রশ্ন থাকলে README.md বা CONTRIBUTING.md দেখুন।**
