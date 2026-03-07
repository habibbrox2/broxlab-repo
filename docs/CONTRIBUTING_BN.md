# কোডবেসে কাজ করার সহজ নিয়ম

এই ফাইলটি আপনার জন্য — যারা এই রেপোতে কোড লিখবেন, বিল্ড করবেন, আর পাবলিশ করবেন।

---

## 🧰 প্রথম সেটআপ

1. রেপো ক্লোন করুন:
   ```bash
   git clone https://github.com/habibbrox2/broxlab.git
   cd broxbhai
   ```

2. প্রয়োজনীয় প্যাকেজ ইন্সটল করুন:
   ```bash
   npm ci
   ```

---

## 🚀 ডেভেলপমেন্ট ও বিল্ড

- ডেভ মোড (চেঞ্জ করলে স্বয়ংক্রিয়ভাবে রিলোড):
  ```bash
  npm run dev
  ```

- প্রোডাকশন বিল্ড:
  ```bash
  npm run build
  ```

---

## 🧹 `.gitignore` নিয়ম প্রয়োগ (local-only ফাইল tracking বন্ধ করার জন্য)

**প্রতিবার `.gitignore` আপডেট করলে** নিচের কমান্ড চালান:

```bash
npm run web-brox
```

এটি:
- `.gitignore`-এ মেলানো হয় এমন ফাইলগুলো Git থেকে আনট্র্যাক করবে (local এ থাকবে)
- একটি কমিট করবে এবং পুশ করবে

---

## ✅ Git workflow (সাধারণ নিয়ম)

1. ফাইল পরিবর্তন করার পর:
   ```bash
   git status
   git add .
   git commit -m "আপনার কমিট মেসেজ"
   ```

2. পুশ করতে:
   ```bash
   git push
   ```

---

## 📌 গিটহাব CI

প্রতি `push` বা `pull request` এ GitHub Actions চলবে:
- `npm run lint`
- `npm run build`

---

## ✍️ আরও সাহায্য দরকার?

প্রশ্ন থাকলে `README.md` দেখুন, অথবা রিপোতে ইস্যু খুলুন।
