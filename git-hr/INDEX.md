# Git HR - ডকুমেন্টেশন ইনডেক্স

আপনার Git Helper টুলসের সম্পূর্ণ গাইড।

---

## 📚 সবকিছু এক জায়গায়

### 🆕 নতুন ব্যবহারকারীর জন্য

1. **[QUICK-START.md](QUICK-START.md)** — ৩ মিনিটে শুরু করুন
   - প্রথম setup কিভাবে করতে হয়
   - প্রথম কমান্ডস চালাতে হয়

2. **[BRANCH-GUIDE.md](BRANCH-GUIDE.md)** — অফিস/বাসা এ কাজ করুন ✨
   - দুটি আলাদা branch setup করুন
   - লোকেশন স্যুইচ করুন
   - প্রতিদিনের workflow

### 📖 বিস্তারিত রেফারেন্স

3. **[SYNC-GUIDE.md](SYNC-GUIDE.md)** — সম্পূর্ণ মাল্টি-লোকেশন গাইড
   - প্রথমবার setup থেকে দৈনন্দিন কাজ
   - Git commands ব্যাখ্যা
   - সমস্যা সমাধান

4. **[GIT-HELPER.md](GIT-HELPER.md)** — সব Git কমান্ডস
   - ১৫টির বেশি command
   - প্রতিটি command এর উদাহরণ
   - Aliases দ্রুত ব্যবহারের জন্য

5. **[FILES-LIST.md](FILES-LIST.md)** — কি push/clone হবে?
   - কোন ফাইল GitHub যাবে
   - কোন ফাইল লোকাল থাকবে
   - Security checklist

---

## 🚀 দ্রুত কমান্ডস

### প্রথমবার
```bash
npm run sync                  # সবকিছু ডাউনলোড করুন
npm run branch setup          # Branch সিস্টেম সেটআপ করুন
```

### প্রতিদিন
```bash
# অফিসে যাওয়ার সময়
npm run branch switch office

# বাসায় যাওয়ার সময়
npm run branch switch home

# কাজ শেষে সংরক্ষণ করুন
npm run git push main "কাজ শেষ"

# পরের দিন শুরু করার আগে pull করুন
npm run git pull main
```

---

## 📋 ডকুমেন্টেশন ম্যাপ

```
git-hr/
├── 🆕 BRANCH-GUIDE.md
│   └── অফিস/বাসা দুটি branch সিস্টেম
│
├── QUICK-START.md
│   └── ৩ ধাপে দ্রুত শুরু
│
├── SYNC-GUIDE.md
│   └── সম্পূর্ণ workflow গাইড (বাংলা)
│
├── GIT-HELPER.md
│   └── সব git command রেফারেন্স
│
├── FILES-LIST.md
│   └── কি পুশ/ক্লোন হবে তার লিস্ট
│
└── README.md
    └── এই ফোল্ডারের সারাংশ
```

---

## 🎯 আপনি কি করতে চান?

### "আমি নতুন, কোথা থেকে শুরু করব?"
👉 **[QUICK-START.md](QUICK-START.md)** পড়ুন

### "আমি অফিস ও বাসা থেকে কাজ করি"
👉 **[BRANCH-GUIDE.md](BRANCH-GUIDE.md)** পড়ুন ✨

### "আমি সব git কমান্ড জানতে চাই"
👉 **[GIT-HELPER.md](GIT-HELPER.md)** পড়ুন

### "কোন ফাইল push হবে?"
👉 **[FILES-LIST.md](FILES-LIST.md)** পড়ুন

### "সম্পূর্ণ detailedওয়ার্কফ্লো দেখতে চাই"
👉 **[SYNC-GUIDE.md](SYNC-GUIDE.md)** পড়ুন

---

## 🔧 সব স্ক্রিপ্টস

| স্ক্রিপ্ট | উদ্দেশ্য | বেশি ব্যবহার হয়? |
|-----------|---------|------------------|
| `npm run git` | সব git command | ✅ প্রতিদিন |
| `npm run sync` | Clone + setup | একবার শুরুতে |
| `npm run branch` | Location switch | ✅ প্রতিদিন |

---

## 💡 প্রয়োজনীয় জ্ঞান

### Beginner (নতুন)?
- [ ] QUICK-START.md পড়েছেন?
- [ ] প্রথমবার `npm run sync` চালিয়েছেন?
- [ ] `npm run git st` দিয়ে status দেখেছেন?

### Intermediate (নিয়মিত)?
- [ ] SYNC-GUIDE.md বুঝেছেন?
- [ ] Daily workflow follow করছেন?
- [ ] Commit messages লিখছেন?

### Advanced (দুই জায়গা থেকে)?
- [ ] BRANCH-GUIDE.md পড়েছেন? ✨
- [ ] Branch setup করেছেন?
- [ ] `npm run branch switch` ব্যবহার করছেন?

---

## ❓ সাধারণ প্রশ্ন

**Q: প্রথমবার কি করব?**
A: [QUICK-START.md](QUICK-START.md) → [BRANCH-GUIDE.md](BRANCH-GUIDE.md)

**Q: প্রতিদিন কি করব?**
A: 
1. কাজ করুন
2. `npm run git push main "মেসেজ"` চালান
3. পরের দিন `npm run git pull main` চালান

**Q: দুটি জায়গায় কাজ করছি?**
A: [BRANCH-GUIDE.md](BRANCH-GUIDE.md) দেখুন!

**Q: কি ফাইল GitHub যাবে?**
A: [FILES-LIST.md](FILES-LIST.md) দেখুন

**Q: সব command দেখতে চাই?**
A: `npm run git -- --help` চালান

---

## 🎓 সেরা অনুশীলন

✅ **প্রতিদিন শুরুতে pull করুন**
```bash
npm run git pull main
```

✅ **প্রতিদিন শেষে push করুন**
```bash
npm run git push main "আজকের কাজ"
```

✅ **দুই জায়গা থেকে কাজ করলে branch ব্যবহার করুন**
```bash
npm run branch switch home
npm run branch switch office
```

❌ **`.env` কখনও commit করবেন না**

❌ **`node_modules/` push করবেন না**

❌ **ম্যানুয়ালি branch switch করবেন না** - `npm run branch switch` দিন

---

## 📞 দ্রুত সাহায্য

```bash
# সব কমান্ড দেখুন
npm run git -- --help
npm run branch -- --help

# বর্তমান status দেখুন
npm run branch status
npm run git st

# কাজ সংরক্ষণ করুন
npm run git sh save "name"
```

---

## 📝 সবার জন্য Checklist

### প্রথম দিন
- [ ] Repository clone করেছেন
- [ ] Dependencies install করেছেন (`npm install`)
- [ ] `.env` configure করেছেন
- [ ] Database import করেছেন
- [ ] `npm run build` চালিয়েছেন

### Branch setup (দুই জায়গা থেকে কাজ করলে)
- [ ] `npm run branch setup` চালিয়েছেন
- [ ] `npm run branch status` দিয়ে verify করেছেন
- [ ] [BRANCH-GUIDE.md](BRANCH-GUIDE.md) পড়েছেন ✨

### প্রতিদিন
- [ ] সকালে `npm run git pull main` কমান্ড দিয়েছেন
- [ ] কাজ করেছেন
- [ ] সন্ধ্যায় `npm run git push main` দিয়েছেন
- [ ] পরের দিনের জন্য প্রস্তুত করেছেন

---

**এখন সবকিছু জানছেন!** শুরু করুন 🚀

**সবচেয়ে গুরুত্বপূর্ণ:** দুটি জায়গা থেকে কাজ করলে **[BRANCH-GUIDE.md](BRANCH-GUIDE.md)** পড়ুন! ✨
