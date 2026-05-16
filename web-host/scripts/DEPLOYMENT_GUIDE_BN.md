# Broxlab ডিপ্লয়মেন্ট গাইড (বাংলা)

## উদ্দেশ্য
এই নথি `scripts/deploy.sh` স্ক্রিপ্টের ব্যবহার ও প্রয়োজনীয় ধাপগুলো বাংলা ভাষায় বর্ণনা করে — নতুন রিলিজ তৈরি, শেয়ার্ড রিসোর্স লিংক, ডিপেনডেন্সি ইনস্টল, অ্যাসেট বিল্ড, নোড সার্ভার পুনরায় চালু, হেলথ-চেক এবং অটো-রোলব্যাক।

## পূর্বপ্রয়োজনীয়তা
- রিমোট সার্ভারে Git, Node.js, npm ইনস্টল থাকতে হবে।
- যদি প্রজেক্টে PHP থাকে: `php` এবং `composer` থাকা প্রয়োজন।
- `shared/.env` ফাইল তৈরি ও কনফিগার করা থাকতে হবে (`$BASE/app/shared/.env`)।
- `scripts/deploy.sh` এ `GIT_REPO`, `REF`, `NODE_HEALTH_URL` ইত্যাদি environment ভ্যারিয়েবল কনফিগার করা যাবে।

## কিভাবে চালাবেন
1. সার্ভারে লগইন করুন এবং ডিরেক্টরিতে যান:

```bash
cd /home/tdhuedhn/broxlab
```

2. ডিপ্লয় স্ক্রিপ্ট চালান (ডিফল্ট: `main` ব্রাঞ্চ):

```bash
scripts/deploy.sh --base /home/tdhuedhn/broxlab --repo git@github.com:yourorg/yourrepo.git --ref main
```

- ড্রাই-রান:
  ```bash
  scripts/deploy.sh --dry-run
  ```
- HTTPS ক্লোন করতে:
  ```bash
  scripts/deploy.sh --use-https
  ```
- যদি আপনি সার্ভার স্বয়ংক্রিয়ভাবে শুরু করতে না চান:
  ```bash
  scripts/deploy.sh --no-start
  ```

## গুরুত্বপূর্ণ পরিবেশ ভ্যারিয়েবল
- BASE (ডিফল্ট: /home/tdhuedhn/broxlab)
- GIT_REPO (গিট রিপোজিটরি URL)
- REF (ব্রাঞ্চ বা শা: ডিফল্ট main)
- KEEP_RELEASES (সংখ্যা, ডিফল্ট 3)
- NODE_ENV (production)
- NODE_HEALTH_URL (নোড অ্যাপের হেলথ-এন্ডপয়েন্ট — optional)
- SKIP_BUILD, DRY_RUN, USE_HTTPS

## রিলিজ ও শেয়ার্ড ফাইল
- নতুন রিলিজ `app/releases/<timestamp>` ফোল্ডারে ক্লোন করা হবে।
- শেয়ার্ড ফাইলগুলি `app/shared`-এ থাকবে এবং নতুন রিলিজে symlink করা হবে (যেমন `.env`, `storage/uploads`)।
- `app/current` হল বর্তমান সিমলিঙ্ক যা সক্রিয় রিলিজ নির্দেশ করে এবং `public_html` সিমলিঙ্কটি `app/current/public_html`-কে নির্দেশ করবে।

## রোলব্যাক
- যদি নতুন রিলিজ শুরু হয় না বা হেলথ-চেক ফেল করে, স্ক্রিপ্ট স্বয়ংক্রিয়ভাবে পূর্ববর্তী রিলিজে রোলব্যাক করবে (যদি পাওয়া যায়)।
- ম্যানুয়াল রোলব্যাকের জন্য `web-host/scripts/rollback.sh` ব্যবহার করুন।

## লগ ও ডিবাগিং
- লগ ফাইল: `$BASE/logs/node-server_<timestamp>.log`
- ডিপ্লয় ত্রুটি হলে `/home/deploy/broxlab/app/releases`-এ নতুন রিলিজ মুছে ফেলা হবে এবং `app/current` আগের রিলিজে ফিরিয়ে আনা হবে (যদি উপস্থিত থাকে)।

## GitHub Actions (সংক্ষেপে)
- `.github/workflows/deploy.yml` ওয়ার্কফ্লো পুশে SSH ব্যবহার করে রিমোটে `deploy.sh` কল করতে পারে।
- ওয়ার্কফ্লো-তে সিক্রেটস: `HOST`, `USER`, `SSH_KEY_BASE64` ইত্যাদি থাকতে হবে।

## ডিপ্লয় সিক্রেট চেকলিস্ট
- `HOST`: রিমোট সার্ভার এক্সেস করার হোস্ট বা IP
- `USER`: SSH লগইন ইউজার
- `SSH_KEY_BASE64`: base64-এ এনকোড করা SSH প্রাইভেট কী
- `REMOTE_BASE`: রিমোট বেস পাথ (ডিফল্ট `/home/$USER/broxlab`)
- `SSH_PORT`: SSH পোর্ট (ডিফল্ট `22`)
- `KEEP_RELEASES`: সার্ভারে রাখতে চান রিলিজের সংখ্যা

## সহজ চেকলিস্ট
- [ ] shared/.env কনফিগার করা আছে
- [ ] Node ও PHP প্রয়োজনীয়তা ইনস্টল করা আছে
- [ ] রিপো ও রেফ ঠিক আছে (`GIT_REPO`, `REF`)
- [ ] `NODE_HEALTH_URL` সঠিক (যদি হেলথ-চেক চান)

## সাহায্যপ্রাপ্তি
প্রয়োজনে রিমোট সার্ভারে লগ ফাইলগুলো দেখুন এবং প্রাসঙ্গিক ত্রুটি মেসেজগুলো কপি করে দিন — আমি সাহায্য করব।

---
ফাইল অবস্থান: `web-host/scripts/DEPLOYMENT_GUIDE_BN.md`
