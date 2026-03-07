# প্রথম ৫ মিনিটে Setup করুন

দুটি location এ কাজ করার জন্য দ্রুত সেটআপ।

---

## ⏱️ Step 1: GitHub URL Set করুন (১ মিনিট)

`.env` ফাইল খুলুন:

```env
GITHUB_REPO_URL=https://github.com/habibbrox2/broxlab.git
WORK_LOCATION=office          # আপনি এখন কোথায় আছেন?
```

---

## ⏱️ Step 2: Branches তৈরি করুন (২ মিনিট)

টার্মিনালে এক্সিকিউট করুন:

```bash
npm run branch setup
```

আউটপুট:
```
✔ Setting up branches for multi-location sync...
✔ office branch ready.
✔ home branch ready.
✔ All branches setup complete!
```

---

## ⏱️ Step 3: Verify করুন (১ মিনিট)

```bash
npm run branch status
```

আউটপুট:
```
  Current Location    : office
  Current Branch      : office
  Home Branch         : home
  Office Branch       : office

✔  You're on OFFICE branch
```

---

## ⏱️ Step 4: Remote এ Push করুন (১ মিনিট)

```bash
git push -u origin office home main
```

---

## ✅ সম্পন্ন!

এখন আপনার দুটি location সেটআপ হয়েছে! 🎉

---

## 🎯 এখন কি করবেন?

### প্রতিদিন অফিসে:
```bash
npm run branch switch office
npm run git push main "কাজ"
```

### প্রতিদিন বাসায়:
```bash
npm run branch switch home
npm run git pull main
npm run git push main "বাসার কাজ"
```

---

## 📖 আরও পড়ুন:

- [BRANCH-GUIDE.md](BRANCH-GUIDE.md) — বিস্তারিত গাইড
- [VISUAL-GUIDE.md](VISUAL-GUIDE.md) — ডায়াগ্রাম
- [INDEX.md](INDEX.md) — সব ডকুমেন্ট এক জায়গায়

---

**এখন কাজ শুরু করুন!** ✅
