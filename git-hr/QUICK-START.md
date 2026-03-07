# দ্রুত শুরু — অফিস & বাসা সিঙ্ক

## ৩টি ধাপে শুরু করুন:

### 1️⃣ আপনার GitHub Repo সেট করুন

`.env` ফাইল খুলুন এবং আপনার repo যোগ করুন:

```env
GITHUB_REPO_URL=https://github.com/habibbrox2/broxlab.git
```

### 2️⃣ প্রথমবার সেটআপ

```bash
# GitHub থেকে সবকিছু ডাউনলোড করুন
npm run sync

# অথবা শুধু ক্লোন করুন
npm run git clone
```

### 3️⃣ প্রতিদিন ব্যবহার করুন

**অফিসে শেষে:**
```bash
npm run git push main "অফিসে কাজ সম্পূর্ণ"
```

**বাসায় পৌঁছে:**
```bash
npm run git pull main
```

**বাসায় শেষে:**
```bash
npm run git push main "বাসায় কাজ সম্পূর্ণ"
```

---

## 📚 বিস্তারিত গাইড

👉 **সম্পূর্ণ নির্দেশনা:** [SYNC-GUIDE.md](SYNC-GUIDE.md)

👉 **সব Git কমান্ডস:** [GIT-HELPER.md](GIT-HELPER.md)

---

## 🎯 প্রধান কমান্ডস

| কাজ | কমান্ড |
|-----|--------|
| **সিঙ্ক সেটআপ** | `npm run sync` |
| **পুশ করুন** | `npm run git push main "মেসেজ"` |
| **পুল করুন** | `npm run git pull main` |
| **স্ট্যাটাস** | `npm run git st` |
| **লগ দেখুন** | `npm run git lg` |

---

**প্রথমবার ভালো করে পড়ুন:** [SYNC-GUIDE.md](SYNC-GUIDE.md) ✅
