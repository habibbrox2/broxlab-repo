# 🎯 Auto-Branch Feature — `.env` থেকে Automatic Branch Selection

> ✨ **নতুন ফিচার**: এখন আপনি branch name না দিয়েই push/pull করতে পারবেন!

---

## কিভাবে কাজ করে?

আপনার `.env` ফাইল এ যা `WORK_LOCATION` সেট করা আছে, সেই অনুযায়ী automatic branch নির্বাচিত হবে।

### ✅ সেটআপ (`.env` এ)

```env
# আপনি এখন কোথায় আছেন? (home বা office)
WORK_LOCATION=home

# বিভিন্ন location এর branch নাম
BRANCH_HOME=home       # বাসা থেকে কাজ করার সময়
BRANCH_OFFICE=office   # অফিস থেকে কাজ করার সময়
```

---

## 📝 ব্যবহার

### ❌ পুরনো উপায় (এখনও কাজ করে)

```bash
npm run git push main "আমার কমিট"
npm run git pull main
```

### ✅ নতুন উপায় (Auto-detect)

```bash
# Branch name দিতে হবে না — .env থেকে নিবে!
npm run git push "আমার কমিট"
npm run git pull
```

---

## 🔄 কিভাবে?

1. **Home থেকে কাজ করছেন?**
   ```env
   WORK_LOCATION=home
   ```
   → `npm run git push` → **home branch** এ push হবে

2. **Office থেকে কাজ করছেন?**
   ```env
   WORK_LOCATION=office
   ```
   → `npm run git push` → **office branch** এ push হবে

---

## 📋 সব কমান্ডস — এখন ২ উপায়ে কাজ করে

| কমান্ড | পুরনো উপায় | নতুন উপায় (Auto) |
|--------|------------|-------------------|
| **Push** | `npm run git push main "msg"` | `npm run git push "msg"` |
| **Pull** | `npm run git pull main` | `npm run git pull` |

---

## 💡 উদাহরণ

### দৈনন্দিন কর্মপ্রবাহ (Home)

**সকালে বাসা থেকে শুরু:**
```bash
# .env: WORK_LOCATION=home
npm run git pull           # ← home branch থেকে pull
# ... কাজ করুন ...
npm run git push "সকালের কাজ সম্পন্ন"  # ← home branch এ push
```

**অফিসে পৌঁছে:**
```bash
# .env এ পরিবর্তন করুন: WORK_LOCATION=office
npm run branch switch office   # ← Location পরিবর্তন করুন (branch-manager দিয়ে)
npm run git pull              # ← office branch থেকে pull
```

---

## ⚠️ গুরুত্বপূর্ণ

- **`.env` সেট করতে হবে** — নাহলে error হবে
- **`npm run branch` দিয়ে location পরিবর্তন করুন** — manual `.env` edit করবেন না (branch-manager auto-update করবে)
- **Branch name দিতে পারেন** — `npm run git push main "msg"` এখনও কাজ করবে (explicit branch override)

---

## 🆘 Troubleshooting

**Q: "Branch name not provided" error পাচ্ছি?**  
A: আপনার `.env` এ `WORK_LOCATION` সেট করুন:
```env
WORK_LOCATION=home    # বা office
```

**Q: Branch explicitly দিতে চাই (auto-detect চাই না)?**  
A: সব সময় কাজ করে:
```bash
npm run git push main "message"  # ← main branch এ push (auto-ignore করবে)
```

---

## 📚 আরও তথ্য

- 📖 [BRANCH-GUIDE.md](BRANCH-GUIDE.md) — সম্পূর্ণ branch ম্যানেজমেন্ট
- 🚀 [SYNC-GUIDE.md](SYNC-GUIDE.md) — অফিস-বাসা সিঙ্ক প্রক্রিয়া
- ⚡ [FIRST-5-MIN.md](FIRST-5-MIN.md) — দ্রুত শুরু করুন
