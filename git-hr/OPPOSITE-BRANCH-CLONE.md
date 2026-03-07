# Opposite Branch Clone Feature 🔄

> ✨ **নতুন ফিচার**: `npm run sync` করলে opposite location এর branch clone হয়!

---

## 🎯 কি এটা কি করে?

যখন আপনি `npm run sync` দিয়ে repository clone করেন, এটি **আপনার বর্তমান location এর opposite branch** ক্লোন করে।

### মানে কি?

```
আপনি OFFICE এ আছেন (WORK_LOCATION=office)
                ↓
npm run sync
                ↓
🏠 HOME branch ক্লোন হয়
(home location এর সব কোড পাবেন)
```

```
আপনি HOME এ আছেন (WORK_LOCATION=home)
                ↓
npm run sync
                ↓
🏢 OFFICE branch ক্লোন হয়
(office location এর সব কোড পাবেন)
```

---

## 🤔 এটা কেন দরকারি?

অফিসে বসে HOME branch থেকে কোড clone করতে পারবেন, এবং বাসায় বসে OFFICE branch থেকে কোড clone করতে পারবেন। সবসময় **অন্য location এর latest কোড** পাবেন!

---

## 📋 কিভাবে কাজ করে?

### ১. `.env` Configuration:

```env
WORK_LOCATION=office         # আপনি এখন office এ আছেন
BRANCH_HOME=home             # home location এর branch
BRANCH_OFFICE=office         # office location এর branch
```

### ২. Clone করুন:

```bash
npm run sync
```

### ৩. কি হয়?

Script এটা করে:
1. `.env` থেকে `WORK_LOCATION` পড়ে → `office`
2. Opposite branch determine করে → `home` (কারণ আপনি office এ আছেন)
3. **⚠️ বর্তমান ডিরেক্টরির tracked ফাইলগুলো রিমুভ করে**
4. **সরাসরি বর্তমান ডিরেক্টরিতে clone করে** (কোন সাবফোল্ডারে নয়)
5. Dependencies বসায় (composer, npm)
6. Setup প্রিন্ট করে

**⚠️ WARNING:** এই কমান্ড বর্তমান ডিরেক্টরির সব tracked ফাইল (যেগুলো GitHub-এ পুশ হয়) রিমুভ করে দেবে!

---

## 🔄 Workflow উদাহরণ

### **সকালে অফিসে شুরু করছি:**

```bash
# .env তে WORK_LOCATION=office আছে
npm run sync

# Output দেখবেন:
# 📍 Current location: OFFICE → Cloning home branch
# (You'll get the HOME location's code)
# 
# Cleaning Existing Tracked Files...
# Found X tracked files/folders to remove...
# Removed X tracked files/folders.
#
# এখন home branch ক্লোন হয়েছে!
```

### **সন্ধ্যায় বাসায় যাচ্ছি:**

```bash
# অফিস থেকে নতুন কোড pull করলেন
# বাসায় গিয়ে .env পরিবর্তন করুন:
WORK_LOCATION=home

# এখন clone করুন:
npm run sync

# Output দেখবেন:
# 📍 Current location: HOME → Cloning office branch
# (You'll get the OFFICE location's code)
#
# Cleaning Existing Tracked Files...
# Found X tracked files/folders to remove...
# Removed X tracked files/folders.
#
# এখন office branch ক্লোন হয়েছে!
```

---

## 📌 গুরুত্বপূর্ণ নোটস

### ✅ সঠিক উপায়:

```bash
# প্রথমবার setup:
npm run sync              # ← opposite branch auto-clone

# এর পর push/pull:
npm run git push "msg"    # ← auto-detect yourself
npm run git pull
```

### ❌ এড়িয়ে চলুন:

```bash
# Manual branch clone করবেন না:
git clone --branch home ...  # ❌ manual করার দরকার নেই

# .env manually edit করে branch context ভুল করবেন না
WORK_LOCATION=office         # ✅ correct
WORK_LOCATION=HOME           # ❌ wrong (case-sensitive!)
```

---

## 💡 উদাহরণ Flow

```
Day 1 - Office:
  - .env: WORK_LOCATION=office
  - npm run sync
  - ✅ Clones home branch
  - Do work...
  - npm run git push "office work"

Day 1 - Evening Commute:
  - Go home
  - Update .env: WORK_LOCATION=home
  - npm run git pull
  - ✅ Gets all office work

Day 2 - Home:
  - .env: WORK_LOCATION=home  
  - npm run git pull
  - ✅ Get latest code
  - Do work...
  - npm run git push "home work"

Day 2 - Morning Commute:
  - Go to office
  - Update .env: WORK_LOCATION=office
  - npm run git pull
  - ✅ Gets all home work
```

---

## 🆘 Troubleshooting

**Q: "branch does not exist" error পাচ্ছি?**
```
A: নিশ্চিত করুন remote এ branches আছে:
   git branch -r                    # দেখাবে remote branches
   
   যদি নেই তাহলে:
   npm run branch setup              # first-time setup
```

**Q: Clone হচ্ছে কিন্তু wrong branch?**
```
A: .env তে WORK_LOCATION check করুন:
   cat .env | grep WORK_LOCATION
   
   যদি wrong হয় তো পরিবর্তন করুন এবং retry করুন
```

**Q: Manual branch দিয়ে clone করতে চাই?**
```
A: সবসময় possible:
   git clone --branch main https://github.com/...
   
   অথবা npm script use করুন:
   npm run git clone
```

---

## 📚 Related Documentation

- [AUTO-BRANCH.md](AUTO-BRANCH.md) — Auto-detect push/pull
- [BRANCH-GUIDE.md](BRANCH-GUIDE.md) — Full branch system
- [SETUP-CHECK.md](SETUP-CHECK.md) — Setup verification
- [GIT-HELPER.md](GIT-HELPER.md) — All git commands

---

## ✨ Summary

```
npm run sync = Clone the OPPOSITE branch based on WORK_LOCATION
             = সব সময় অন্য location এর latest code পান!
```
