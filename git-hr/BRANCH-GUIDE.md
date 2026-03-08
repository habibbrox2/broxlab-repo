# Multi-Location Branch System

দুটি আলাদা location-এ নিরাপদভাবে কাজ করার জন্য এই সিস্টেম ডিজাইন করা হয়েছে।

---

## 🎯 সিস্টেম কিভাবে কাজ করে?

```
main (Primary branch)
│
├── home (বাসার কোড)
│   └── প্রতিদিনের পরিবর্তন
│
└── office (অফিসের কোড)
    └── প্রতিদিনের পরিবর্তন
```

প্রতিটি location এর আলাদা branch থাকে যাতে:
- বাসা ও অফিসের কোড আলাদা থাকে
- একসাথে কাজ করার সময় conflict না হয়
- স্বয়ংক্রিয়ভাবে সঠিক branch ব্যবহার হয়

---

## ⚙️ প্রথমবার Setup

### ১. Branch তৈরি করুন (শুধু একবার)

```bash
npm run branch setup
```

এটা করবে:
- `home` branch তৈরি করবে
- `office` branch তৈরি করবে
- Remote এ push করবে

### ২. আপনার লোকেশন সেট করুন

`.env` ফাইলে সেট করুন:

```env
# যেখানে আছেন:
WORK_LOCATION=home
# অথবা
WORK_LOCATION=office

# ব্র্যাঞ্চ নামগুলি (ডিফল্ট):
BRANCH_HOME=home
BRANCH_OFFICE=office
```

---

## 📍 দৈনন্দিন ব্যবহার

### **প্রথমদিন অফিসে:**

```bash
# লোকেশন Set করুন (Office)
npm run branch switch office

# এখন সব push/pull অফিস branch এ হবে
npm run git push main "অফিসে কাজ"
```

আউটপুট:
```
ℹ  Switching to office location...
ℹ  Using branch: office
✔  Switched to office location!

Now working from: office
Branch: office
```

### **অফিসে কাজ শেষে (এক-এক ঘণ্টা আগে):**

```bash
# সব কাজ commit করুন
npm run git push office "অফিসে শেষ আপডেট"
```

### **বাসায় পৌঁছে:**

```bash
# লোকেশন পরিবর্তন করুন
npm run branch switch home

# এখন home branch এ আছেন
# অফিসের সব আপডেট আটোমেটিক pull হবে
npm run git pull main
```

### **বাসায় কাজ করুন:**

```bash
# আপনার পরিবর্তনগুলি বাসার branch এ সংরক্ষিত হবে
npm run git push main "বাসায় কাজ"
```

### **next day অফিসে প্রবেশ:**

```bash
# লোকেশন পরিবর্তন করুন
npm run branch switch office

# বাসার সব আপডেট pull হবে
npm run git pull main

# অফিসে কাজ চালিয়ে যান
npm run git push main "অফিসে আবার শুরু"
```

---

## 🔄 সম্পূর্ণ ওয়ার্কফ্লো উদাহরণ

### **Day 1 - অফিসে:**

```bash
# সকালে অফিসে এসেছি
npm run branch switch office

# কাজ শুরু করি
# ... code changes ...

# দুপুর ১২টায় অগ্রগতি সংরক্ষণ
npm run git push office "সকাল ১০-১২: features এ কাজ"

# সন্ধ্যা ৫টায় অফিস ছেড়ে যাচ্ছি
npm run git push office "অফিস শেষ - সব compile"
git log --oneline -n 5
```

### **Day 1 Evening - বাসায়:**

```bash
# বাসায় পৌঁছেছি
npm run branch switch home

✔  Switched to home location!
Now working from: home
Branch: home

# বাসার branch এ pull করুন (যদি আগে কাজ ছিল)
npm run branch sync

# বাসায় কাজ শুরু করি
# ... code changes ...

# রাত ১০টায় সংরক্ষণ করি
npm run git push main "বাসায় রাত feature add"
```

### **Day 2 - অফিসে সকালে:**

```bash
# অফিসে ফিরেছি
npm run branch switch office

# বাসার সব changes pull করুন
npm run branch sync

✔  Sync complete!

# এখন বাসার সব কাজ আপনার অফিসে আছে
# কাজ চালিয়ে যান
npm run git push main "দিন ২ সকাল"
```

---

## 📝 সব কমান্ডস

```bash
# Setup (শুধু প্রথমবার)
npm run branch setup

# লোকেশন পরিবর্তন করুন
npm run branch switch home
npm run branch switch office

# বর্তমান লোকেশন দেখুন
npm run branch status

# Safe sync (স্বয়ংক্রিয় branch)
npm run branch sync

# সাহায্য দেখুন
npm run branch -- --help
```

---

## 🔍 Branch Status দেখুন

```bash
npm run branch status
```

আউটপুট:
```
──────────────────────────────────────────────────────
  Location Status
──────────────────────────────────────────────────────

  Current Location    : home
  Current Branch      : home
  Home Branch         : home
  Office Branch       : office

✔  You're on HOME branch
```

---

## ⚠️ গুরুত্বপূর্ণ নোটস

### ✅ করুন:

1. **লোকেশন পরিবর্তনের সময় সবসময় switch করুন:**
   ```bash
   npm run branch switch home  # বাসায় যাওয়ার সময়
   npm run branch switch office  # অফিসে যাওয়ার সময়
   ```

2. **Local .env সেট করুন প্রতিটি স্থানে:**
   ```bash
   # প্রতিটি স্থানে আলাদা DB credentials
   WORK_LOCATION=home/office
   DB_HOST=localhost (বাসা) বা direct-server (অফিস)
   DB_USER=different-user
   DB_PASS=different-password
   ```

3. **প্রতিদিন শেষে push করুন:**
   ```bash
   npm run git push main "End of day"
   ```

### ❌ করবেন না:

1. **ম্যানুয়ালি branch switch করবেন না:**
   ```bash
   # ❌ এটা করবেন না:
   git checkout office
   
   # ✅ এটা করুন:
   npm run branch switch office
   ```

2. **`.env` কমিট করবেন না** (স্বয়ংক্রিয়ভাবে ignored)

3. **এক সাথে দুটি location থেকে push করবেন না**
   - একবারে এক জায়গা থেকেই কাজ করুন
   - লোকেশন পরিবর্তনের আগে সব commit করুন

---

## 🆘 সমস্যা সমাধান

### প্রশ্ন: "I'm on the wrong branch!"

```bash
# আপনার বর্তমান লোকেশন চেক করুন
npm run branch status

# সঠিক লোকেশনে switch করুন
npm run branch switch home
```

### প্রশ্ন: "Merge conflicts?"

```bash
# ম্যানুয়ালভাবে resolve করুন (ফাইল এডিট করুন)
# তারপর:
npm run git push main "conflict resolved"
```

### প্রশ্ন: "বাসার changes অফিসে দেখছি না?"

```bash
# Sync করুন:
npm run branch sync

# অথবা manually:
npm run git pull main
```

---

## 📊 Branch Management চার্ট

```
Timeline:
─────────────────────────────────────────────

Day 1 - Office:
main ─────────────────────────────────────
      └─ office: commit1, commit2, commit3

Day 1 - Home Evening:
main ─────────────────────────────────────
      ├─ office: commit1, commit2, commit3
      └─ home: commit4, commit5

Day 2 - Office Morning:
main ─────────────────────────────────────
      ├─ office: commit1-3, commit4-5 (synced!)
      └─ home: commit4, commit5

এক দেখুন কিভাবে সব commits একসাথে এগিয়ে যায়?
```

---

## 🎓 সেরা সাজেশনস

1. **প্রতিটি লোকেশনে আলাদা DB ব্যবহার করুন** - এতে data sync সমস্যা হবে না

2. **প্রতিদিন শুরুতে pull করুন** - অন্য লোকেশনের সব আপডেট পেতে

3. **প্রতিদিন শেষে push করুন** - কোড সুরক্ষিত রাখতে

4. **Descriptive commit messages লিখুন** - পরে কোন পরিবর্তন কখন হয়েছে তা বুঝতে

---

**এই সিস্টেমে আপনি নিরাপদে দুই জায়গায় কাজ করতে পারেন!** ✅
