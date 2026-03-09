# Repo Migration / Sync Steps

এই ফাইলটি রেকর্ড করছে কি কি কমান্ড ও পরিবর্তন করা হয়েছে যাতে বর্তমান লোকাল রিপোটা `habibbrox2/broxlab-repo` রিমোটের সাথে সিঙ্ক হয়।

---

## 1) রিমোট আপডেট

1. বর্তমান রিমোট দেখার জন্য:

```powershell
git remote -v
```

2. রিমোট আপডেট করে নতুন রিপোতে নির্দেশ করতে:

```powershell
git remote set-url origin git@github.com:habibbrox2/broxlab-repo.git
```

> পরে authentication issues এড়াতে personal access token (PAT) ব্যবহার করে HTTPS URL-এ পরিবর্তন করা হয়েছে:

```powershell
git remote set-url origin https://<----->@github.com/habibbrox2/broxlab-repo.git
```


## 2) `.gitignore`-এ রাখা ফাইল/ফোল্ডারগুলো "লোকাল" রাখার নির্দেশ

`.gitignore`-এ নিম্নের আইটেমগুলো ছিলঃ

- `/vendor/`
- `.env`, `.env.local`, `.env.production`, `.env.example`
- `*.log`, `/logs/`, `storage/logs/`
- `/cache/`, `storage/cache/`, `storage/tmp/`, `.tmp.*`
- `/uploads/`, `public_html/uploads/`, `public_html/assets/uploads/`
- `/Database/`, `db.sql`
- `Config/broxlab-firebase.json`
- `node_modules/`, `build/`
- `.vscode/`, `.idea/`
- `.DS_Store`, `Thumbs.db`
- `composer.lock`, `package-lock.json`
- `.tmp.*`, `.tmp.driveupload`, `vs-code-ex/`

এভাবে `.gitignore`-এ রাখা হলে Git pull/push/checkout ইত্যাদির সময় এই ফাইলগুলো লোকালি অক্ষত থাকবে (যদি আগে ট্র্যাক করা না থাকে)।


## 3) লোকাল পরিবর্তন কমিট এবং রিবেস/রিসেট

1. প্রথমে স্ট্যাটাস চেক করা:

```powershell
git status --porcelain=v1
```

2. সব ফাইল স্টেজ করা (কিন্তু `.gitignore` বাদ রাখা):

```powershell
git add --all
git reset -- .gitignore
```

3. কমিট তৈরি করা:

```powershell
git commit -m "Migrate repository to broxlab-repo (excluding .gitignore)"
```

4. যদি কমিট বা রিবেস অসম্পূর্ণ হয় বা ভুল হয়, তাহলে পূর্বের স্টেটে ফেরত:

```powershell
git reset --hard HEAD~1
```


## 4) রিমোট থেকে সিঙ্ক করার ধাপ

1. রিমোট থেকে ফেচ করা:

```powershell
git fetch origin
```

2. লোকাল ব্রাঞ্চকে রিমোট মেইন অনুযায়ী রিসেট করা:

```powershell
git reset --hard origin/main
```

> এটি কাজ করবে যদি আপনার লোকাল ব্রাঞ্চ `main` থাকে এবং `origin/main` থেকে আপডেট নেয়া হয়।


## 5) রিবেস/মিডল স্টেট ঠিক করা

সম্ভাব্য “You are currently rebasing” বা অর্ধেকের মতো অবস্থার জন্য:

```powershell
git rebase --abort
```

যদি `.git/rebase-merge` বা `.git/rebase-apply` ডিরেক্টরি থেকে থাকে, তখন তা মুছে দেয়া হয়েছে:

```powershell
Remove-Item -Recurse -Force .git\rebase-merge -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force .git\rebase-apply -ErrorAction SilentlyContinue
```


## 6) বর্তমান রিপো এখন কী অবস্থায়

- লোকাল রিপো এখন `origin/main` (broxlab-repo) এর সাথে **ম্যাচ** করে।
- `.gitignore`-এ থাকা ফাইল/ফোল্ডারগুলো লোকালেই থাকবে, Git থেকে ডিলিট হবে না (যদি তারা আগে থেকে ট্র্যাক না থাকে)।

---

> **যে কোনো সমস্যায় থাকলে** (যেমন আবার `rebase`-এ আটকে যাওয়া, বা কোন ফাইল হারিয়ে যাওয়া), সেই মুহূর্তের `git status` এবং `git log -1 --oneline --decorate` শেয়ার করুন।
