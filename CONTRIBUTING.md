# Simple Rules for Working on the Codebase

This file is for you — those who will write code, build, and publish in this repo.

---

## 🧰 First Setup

1. Clone the repo:
   ```bash
   git clone https://github.com/habibbrox2/broxlab.git
   cd broxbhai
   ```

2. Install necessary packages:
   ```bash
   npm ci
   ```

---

## 🚀 Development and Build

- Dev mode (auto reload on changes):
  ```bash
  npm run dev
  ```

- Production build:
  ```bash
  npm run build
  ```

---

## 🧹 Apply .gitignore rules (to stop tracking local-only files)

**Every time you update .gitignore** run the following command:

```bash
npm run web-brox
```

This:
- Will untrack files matching .gitignore from Git (will remain local)
- Will commit and push

---

## ✅ Git workflow (general rules)

1. After changing files:
   ```bash
   git status
   git add .
   git commit -m "Your commit message"
   ```

2. To push:
   ```bash
   git push
   ```

---

## 📌 GitHub CI

GitHub Actions will run on every push or pull request:
- `npm run lint`
- `npm run build`

---

## ✍️ Need more help?

If you have questions, see README.md, or open an issue in the repo.