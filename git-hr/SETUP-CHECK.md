# ✅ Setup Checklist — Auto-Branch Feature

এই checklist দিয়ে নিশ্চিত করুন সবকিছু সঠিকভাবে setup হয়েছে।

---

## 📋 প্রয়োজনীয় Setup

### ✔️ 1. `.env` File Check

**আপনার `.env` ফাইলে এই lines থাকা উচিত:**

```env
WORK_LOCATION=home        # আপনি এখানে কাজ করছেন
BRANCH_HOME=home          # বাসার branch নাম
BRANCH_OFFICE=office      # অফিসের branch নাম
```

**Tips:**
- `WORK_LOCATION` হতে পারে `home` অথবা `office`
- Branch names আপনি পছন্দমত রাখতে পারেন (default: `home` এবং `office`)

---

### ✔️ 2. npm Scripts Check

**আপনার `package.json` এ এই scripts থাকা উচিত:**

```json
{
  "scripts": {
    "git": "node git-hr/git-helper.js",
    "sync": "node git-hr/sync-clone.js",
    "branch": "node git-hr/branch-manager.js"
  }
}
```

---

### ✔️ 3. Branch System Initialize করুন

**শুধু প্রথমবার একবার করতে হবে:**

```bash
npm run branch setup
```

এটা করবে:
- GitHub এ `home` branch তৈরি করবে
- GitHub এ `office` branch তৈরি করবে
- সব branches push করবে

---

## 🎯 এখন কিভাবে ব্যবহার করবেন?

### Auto-Branch Feature ব্যবহার করুন

```bash
# আপনার .env এ যা WORK_LOCATION আছে সেই branch auto-use হবে
npm run git push "আমার কাজ"   # ← branch name দিতে হয়নি!
npm run git pull              # ← auto-detect করবে সঠিক branch
```

### Manual Branch নির্দিষ্ট করতে চান?

```bash
# এখনও এটা কাজ করে:
npm run git push main "মেসেজ"  # ← main branch এ explicit push
npm run git pull main          # ← main branch থেকে explicit pull
```

---

## 🔄 Daily Workflow

### সকালে বাসায় শুরু করছি:

```bash
# ১. স্ট্যাটাস চেক করুন
npm run branch status
# Output: Now working from: home

# ২. সর্বশেষ কোড pull করুন
npm run git pull

# ৩. কাজ করুন...
# ... code changes ...

# ৪. শেষে সংরক্ষণ করুন
npm run git push "সকালের কাজ সম্পন্ন"
```

### অফিসে যাচ্ছি:

```bash
# ১. Location পরিবর্তন করুন
npm run branch switch office
# এটা auto-update করবে: WORK_LOCATION=office

# ২. অফিসের branch এ switch হবে
# ... অটোমেটিক stash/fetch/checkout হবে

# ৩. এখন office branch auto-use হবে
npm run git push "অফিসে শুরু করলাম"
npm run git pull
```

---

## ⚠️ গুরুত্বপূর্ণ Notes

1. **প্রথম Setup:** শুধু একবার `npm run branch setup` করতে হবে
2. **Location Change:** সবসময় `npm run branch switch` দিয়ে পরিবর্তন করুন (manual `.env` edit করবেন না)
3. **Auto-detect:** branch name বাদ দিলে `.env` থেকে auto-detect হয়
4. **Manual Override:** branch name দিলে `.env` অগ্রাহ্য করবে (আগে setup-এ যেটা set করেছেন)

---

## 🆘 Troubleshooting

**Q: "Branch name not provided" error পাচ্ছি?**
```
A: আপনার .env এ WORK_LOCATION সেট আছে কিনা চেক করুন
   WORK_LOCATION=home   # বা office
```

**Q: কেন branch manual দিতে হচ্ছে?**
```
A: এটা normal - branch explicitly দিলে .env ignore হয়
   npm run git push main "message"  ← এটা সবসময় main এ যাবে
```

**Q: Auto-branch feature `npm run git pull` এ কাজ করছে না?**
```
A: .env file check করুন:
   1. WORK_LOCATION=home থাকা উচিত
   2. BRANCH_HOME=home থাকা উচিত
   3. তারপর npm run git pull করুন (branch name দেবেন না)
```

---

## 📚 আরও তথ্যের জন্য

- 👉 [AUTO-BRANCH.md](AUTO-BRANCH.md) — বিস্তারিত ফিচার গাইড
- 👉 [BRANCH-GUIDE.md](BRANCH-GUIDE.md) — Branch সিস্টেম গাইড
- 👉 [GIT-HELPER.md](GIT-HELPER.md) — সব Git কমান্ড রেফারেন্স
