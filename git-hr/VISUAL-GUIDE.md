# Branch System Visual Guide

দুটি স্থান থেকে সংরক্ষিত কোডিং এর জন্য ভিজ্যুয়াল গাইড।

---

## 🎨 সিস্টেম ডায়াগ্রাম

```
GitHub Repository (Remote)
│
├── main (Primary)
│   │
│   ├── Merge from home
│   │   when needed
│   │
│   └── Merge from office
│       when needed
│
├── home branch
│   │
│   ├─ Update 1 (বাসা)
│   ├─ Update 2 (বাসা)
│   └─ Pulls from office
│
└── office branch
    │
    ├─ Update 1 (অফিস)
    ├─ Update 2 (অফিস)
    └─ Pulls from home
```

---

## 🔄 দৈনন্দিন কাজের প্রবাহ

### দিন ১ - অফিস

```
Office Day 1:
─────────────────────────────────────

AM 10:00  - Arrive at office
          npm run branch switch office
          ✓ Now on: office branch

AM 10:05  - Start coding
          ... code changes ...

PM 12:00  - Midday save
          npm run git push office "morning work"
          ✓ Saved to office branch

PM 05:00  - End of day
          npm run git push office "day 1 complete"
          ✓ All changes saved

GitHub:
  office: [commit1] [commit2] [commit3] ← Ready for home
```

### দিন ১ সন্ধ্যা - বাসা

```
Home Evening Day 1:
─────────────────────────────────────

PM 07:00  - Arrive at home
          npm run branch switch home

           ✓ Automatic:
           - Switched to home branch
           - Pulled latest from office
           - .env updated to WORK_LOCATION=home

PM 07:05  - Verify changes
          npm run branch status
          
          Current Location    : home
          Current Branch      : home
          ✓ All office commits available

PM 07:10  - Continue work
          ... code changes ...

PM 10:00  - Evening save
          npm run git push main "evening work"

GitHub:
  home: [commit4] [commit5] ← New work
        (office commits merged in)
```

### দিন ২ সকাল - অফিস

```
Office Day 2 Morning:
─────────────────────────────────────

AM 10:00  - Back to office
          npm run branch switch office

          ✓ Automatic:
          - Switched to office branch
          - Pulled all home commits
          - .env updated to WORK_LOCATION=office

AM 10:05  - Verify new changes
          npm run branch status
          
          ✓ All home commits available!

AM 10:10  - Continue work
          npm run git push office "day 2 morning"

GitHub:
  office: [all commits from home] + [new commits]
```

---

## 📊 Timeline এ সব Commits

```
Timeline Visualization:
════════════════════════════════════════════════════════

Time    │ Day 1 Office │ Day 1 Home  │ Day 2 Office │ Main
────────┼──────────────┼─────────────┼──────────────┼─────
AM 10   │ ✓ Branch SW  │             │              │
        │ ✓ Commit 1   │             │              │
────────┼──────────────┼─────────────┼──────────────┼─────
PM 12   │ ✓ Commit 2   │             │              │
        │ Push to repo │             │              │
────────┼──────────────┼─────────────┼──────────────┼─────
PM 05   │ ✓ Commit 3   │             │              │
        │ ▶ End Day 1  │             │              │
────────┼──────────────┼─────────────┼──────────────┼─────
PM 07   │              │ ✓ Branch SW │              │
        │              │ ✓ Pull All  │              │
        │              │ ✓ Commit 4  │              │
────────┼──────────────┼─────────────┼──────────────┼─────
PM 10   │              │ ✓ Commit 5  │              │
        │              │ ▶ End Day   │              │
────────┼──────────────┼─────────────┼──────────────┼─────
AM 10+1 │              │             │ ✓ Branch SW  │
(Day 2) │              │             │ ✓ Pull All   │
        │              │             │ ✓ Commit 6+  │
────────┼──────────────┼─────────────┼──────────────┼─────

সারাংশ: সব commits সিঙ্ক থাকে!
```

---

## 🔀 Branch Switching কিভাবে কাজ করে?

```
Step 1: Switch location
────────────────────────────────────

  $ npm run branch switch home
  
  ✓ Stashes any unsaved work
  ✓ Fetches latest from remote
  ✓ Checks out 'home' branch
  ✓ Updates .env WORK_LOCATION=home
  
  Result: You're on home branch


Step 2: All about the branch
────────────────────────────────────

  Branch-specific data:
  
  ├─ .env WORK_LOCATION=home    (auto-updated)
  ├─ branch=home                (auto switched)
  ├─ Your home edits            (available here)
  ├─ Office edits               (available here via pulls)
  └─ All synchronized!


Step 3: Work & Push
────────────────────────────────────

  $ npm run git push main "home changes"
  
  ✓ Commits changes to 'home' branch
  ✓ Pushes to origin/home
  ✓ Ready for office to pull
```

---

## 💻 কমান্ড ফ্লো চার্ট

```
Start Work Day
    │
    ▼
┌─────────────────────────────────┐
│ npm run branch switch [location] │  (office/home)
│ - Stash unsaved work            │
│ - Update .env WORK_LOCATION     │
│ - Checkout branch               │
│ - Pull latest changes           │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ npm run branch status            │  (optional verify)
│ Shows: Location, Branch, Updates │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ Work on Code                     │
│ ... make changes ...             │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ npm run git push main [message]  │  (save progress)
│ - Stages changes                 │
│ - Creates commit                 │
│ - Pushes to current branch       │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ Change Location?                 │
│ (Office→Home | Home→Office)      │
│ YES: Go to START                 │
│ NO: Repeat from "Work on Code"   │
└─────────────────────────────────┘
```

---

## 🎯 State Diagram

```
NOT_SET_UP
    │
    ├─ npm run branch setup
    │  ├─ Creates home branch
    │  ├─ Creates office branch
    │  └─ Pushes to remote
    │
    ▼
READY_AT_OFFICE
    │
    ├─ npm run branch switch office
    │  └─ .env WORK_LOCATION=office
    │
    ▼
WORKING_AT_OFFICE
    │
    ├─ npm run git push main "work"
    │  └─ Commit to office branch
    │
    ├─ GOING_HOME
    │  └─ npm run branch switch home
    │
    ▼
WORKING_AT_HOME
    │
    ├─ npm run git push main "work"
    │  └─ Commit to home branch
    │
    ├─ GOING_OFFICE
    │  └─ npm run branch switch office
    │
    ▼
(Back to WORKING_AT_OFFICE)
```

---

## 📍 আপনি কোথায় আছেন?

```
Real-time Status:
═════════════════════════════════

$ npm run branch status

──────────────────────────────────
  Location Status
──────────────────────────────────

  Current Location    : office  ◄──── .env WORK_LOCATION
  Current Branch      : office  ◄──── Active git branch
  Home Branch         : home
  Office Branch       : office

✔  You're on OFFICE branch       ◄──── Verification

```

---

## 📈 Growth of Repository

```
Day 1 - Office:
────────────────

main ─────┬──C1──C2──C3────────────
          │
        office

Day 1 - Home Evening:
────────────────────

main ─────┬──C1──C2──C3────────────  (office commits)
          │                         ↙ Pull
        home ─────C4──C5────────────


Day 2 - Office Morning:
──────────────────────

main ─────┬──C1──C2──C3─C4─C5────  (home commits merged)
          │           ↗
        office ─────────────C6──C7── (continue office work)
        

রেজাল্ট: সব commits একটি সিঙ্ক্রোনাইজড history তৈরি করে!
```

---

## 🎓 Key Concepts

### 1. **Branch = Location**
   - `office` branch = অফিসের কোড
   - `home` branch = বাসার কোড
   - Independent কিন্তু সিঙ্ক করা

### 2. **Automatic Sync**
   - জায়গা পরিবর্তনের সময় pull হয়
   - অন্যের commits স্বয়ংক্রিয় পাওয়া যায়

### 3. **Safety**
   - Conflicts এড়ানো যায়
   - প্রতিটি location আলাদা থেকে যায়
   - Main branch সুরক্ষিত থাকে

### 4. **Simplicity**
   - একটি কমান্ড: `npm run branch switch`
   - সব বাকিটা স্বয়ংক্রিয়

---

## ✅ সাকসেস চেকলিস্ট

এই diagram ব্যবহার করে আপনি:

- [ ] বুঝেছেন branch system কিভাবে কাজ করে
- [ ] জানেন কখন switch করতে হয়
- [ ] বুঝেছেন সব changes safe থাকে
- [ ] প্রস্তুত দুটি জায়গা থেকে কাজ করতে

---

**এখন সবকিছু পরিষ্কার!** 🎉
