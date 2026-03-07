# পুশ (Push) & ক্লোন (Clone) ফাইল লিস্ট

## 🔴 GitHub-এ পুশ হবে (অর্থাৎ অন্যজায়গায় ক্লোন হবে)

### 📁 মূল ফোল্ডার/ফাইলস

```
✅ app/                          # সব PHP কোড (Controllers, Models, Views)
✅ build/                        # বিল্ড কনফিগারেশন (esbuild, tailwind)
✅ Config/                       # কনফিগারেশন ফাইলস
✅ Database/                     # শুধু SQL স্কিমা ফাইলস (*.sql from database schema)
   ⚠️  Database/backups/ ❌       # ব্যাকআপস যাবে না
   ⚠️  Database/exports/ ❌       # এক্সপোর্টস যাবে না
✅ docs/                         # ডকুমেন্টেশন
✅ public_html/                  # ওয়েব রুট (assets, index.php)
   ⚠️  public_html/uploads/ ❌    # ইউজার আপলোড যাবে না
✅ scripts/                      # স্ক্রিপ্টস
✅ app/Views/                    # Twig ভিউ ফাইলস
✅ git-hr/                       # নতুন Git Helper টুলস ✨
```

### 📄 মূল ফাইলস

```
✅ .env.example                  # টেমপ্লেট (এটা যাবে)
❌ .env                          # লোকাল কনফিগ (যাবে না - ignored)
✅ .gitignore                    # Git ignore ফাইল
✅ composer.json                 # PHP ডিপেন্ডেন্সিস
✅ package.json                  # Node ডিপেন্ডেন্সিস ও স্ক্রিপ্টস
✅ package-lock.json             # Lock ফাইল
✅ README.md                     # প্রজেক্ট README
✅ CONTRIBUTING.md              # Contribution গাইড
✅ LICENSE                       # লাইসেন্স
```

---

## 🔵 স্থানীয়ভাবে থাকবে (GitHub-এ যাবে না)

### ❌ Ignored Folders

```
❌ node_modules/                 # NPM প্যাকেজস (npm install দিয়ে আসবে)
❌ vendor/                       # Composer প্যাকেজস (composer install দিয়ে আসবে)
❌ dist/                         # বিল্ট ফাইলস (npm run build দিয়ে আসবে)
❌ storage/                      # রানটাইম স্টোরেজ
❌ Database/backups/             # লোকাল ডাটাবেস ব্যাকআপস
❌ Database/exports/             # লোকাল ডাটাবেস এক্সপোর্টস
❌ app/uploads/                  # লোকাল ইউজার আপলোডস
❌ public_html/uploads/          # লোকাল ইউজার আপলোডস
❌ .idea/                        # IDE সেটিংস
```

### ❌ Ignored Files

```
❌ .env                          # লোকাল ডাটাবেস ক্রেডেনশিয়ালস (IMPORTANT!)
❌ .env.local                    # লোকাল পরিবেশ ওভাররাইডস
❌ *.log                         # লগ ফাইলস
❌ *.zip, *.tar.gz               # কম্প্রেসড ফাইলস
❌ broxbhai.zip                  # প্রজেক্ট ব্যাকআপ
❌ _backup/                      # ব্যাকআপ ফোল্ডার
❌ .DS_Store                     # macOS সিস্টেম ফাইল
❌ Thumbs.db                     # Windows সিস্টেম ফাইল
❌ *.swp, *.swo, *~              # এডিটর টেম্প ফাইলস
```

---

## 🔄 প্রতিদিন কাজের সময় কি সিঙ্ক হবে?

### **অফিসে কাজ শেষে:**
```bash
npm run git push main "অফিসে কাজ"
```
**এটা পুশ করবে:**
- অ্যাপ কোড (app/, public_html/*.php, ইত্যাদি)
- বিল্ড কনফিগ (build/, tailwind.config.js)
- ডকুমেন্টেশন (.md ফাইলস)
- কনফিগ ফাইলস (.env.example, package.json)

**এটা পুশ করবে না:**
- .env (লোকাল ডাটাবেস পাসওয়ার্ড)
- node_modules/ (স্বয়ংক্রিয় ডাউনলোড হবে)
- Database/backups/ (.gitignore এ আছে)
- uploads/ (ইউজার ডাটা)

### **বাসায় পৌঁছে ক্লোন/পুল করলে:**
```bash
npm run git pull main
npm install         # dependencies ডাউনলোড
npm run build       # assets বিল্ড
```
**এটা আসবে:**
- সব নতুন কোড চেঞ্জেস
- আপডেটেড package.json
- সব ডকুমেন্টেশন

**এটা আসবে না:**
- আপনার অফিসের .env (নিজে তৈরি করতে হবে)
- অফিসের database data (ম্যানুয়ালি ইমপোর্ট করতে হবে)
- অফিসের uploads (ম্যানুয়ালি কপি করতে হবে অথবা sync করতে হবে)

---

## 📋 সেটআপ ফাইলস (একবার করতে হবে)

প্রতিটি নতুন স্থানে প্রথমবার:

```bash
# ১. ক্লোন করুন
npm run sync
# অথবা
node git-hr/sync-clone.js

# এটা করবে:
✅ Repository clone
✅ npm install (node_modules ডাউনলোড)
✅ composer install (vendor ডাউনলোড)
✅ .env তৈরি করবে (.env.example থেকে)

# ২. আপনার লোকাল সেটিংস যোগ করুন
# .env এডিট করুন:
# - DB_HOST
# - DB_USER
# - DB_PASS
# ইত্যাদি

# ৩. ডাটাবেস সেটআপ করুন
mysql -u [user] -p [database] < Database/[schema].sql

# ৪. বিল্ড করুন
npm run build
```

---

## 🎯 সারাংশ

| কাজ | ফাইল | যাবে? | বিস্তারিত |
|-----|------|-------|-----------|
| **কোড সম্পাদন** | `app/`, `public_html/` | ✅ | সব পরিবর্তন sync হবে |
| **কনফিগ গাইড** | `.env.example` | ✅ | টেমপ্লেট শেয়ার হবে |
| **লোকাল সেটিংস** | `.env` | ❌ | প্রতি স্থানে আলাদা |
| **ডিপেন্ডেন্সিস** | `node_modules/`, `vendor/` | ❌ | `npm install` কমান্ড থেকে আসবে |
| **বিল্ট ফাইলস** | `dist/`, `public_html/assets/*/dist/` | ❌ | `npm run build` থেকে আসবে |
| **ডাটাবেস স্কিমা** | `Database/*.sql` | ✅ | স্ট্রাকচার sync হবে |
| **ডাটা ব্যাকআপস** | `Database/backups/` | ❌ | ম্যানুয়ালি sync করতে হবে |
| **ইউজার আপলোডস** | `uploads/`, `app/uploads/` | ❌ | ম্যানুয়ালি sync করতে হবে |

---

## ⚠️ গুরুত্বপূর্ণ নোটস

1. **`.env` কখনও পুশ করবেন না** ← সবচেয়ে গুরুত্বপূর্ণ
2. **প্রতিটি স্থানে আলাদা `.env`** (DB পাসওয়ার্ড ভিন্ন হতে পারে)
3. **ডাটাবেস ম্যানুয়ালি সিঙ্ক করতে হবে** (কোডে যোগ করবেন না)
4. **ইউজার আপলোডস কোডে যাবে না** (uploads/ .gitignore এ আছে)
5. **Dependencies স্বয়ংক্রিয়ভাবে ডাউনলোড হবে** (npm install করলে)

---

এখন আপনি জানেন সবকিছু ক্লোন/পুশ হওয়ার সময় কি হবে! 🎉
