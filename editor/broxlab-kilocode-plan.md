# BroxLab — Kilo Code কনফিগারেশন পরিকল্পনা

## লক্ষ্য
`.kilocode/` ফোল্ডারের rules এবং skills তৈরি করা যা BroxLab এর PHP full-stack প্রজেক্টে
Kilo Code Agent কে সঠিকভাবে গাইড করবে।

## ফাইল স্ট্রাকচার (তৈরি করতে হবে)

```
broxlab-repo/
└── .kilocode/
    ├── rules/
    │   ├── coding.md           # কোড স্টাইল ও naming convention
    │   ├── performance.md      # পারফরম্যান্স ও optimization rules
    │   └── security.md         # Security ও sensitive file rules
    ├── rules-code/
    │   └── php-patterns.md     # PHP 8 specific patterns
    └── skills/
        ├── ai-feature/
        │   └── SKILL.md        # AI module কাজের জন্য skill
        ├── api-design/
        │   └── SKILL.md        # API endpoint তৈরির skill
        └── db-query/
            └── SKILL.md        # MySQL query লেখার skill
```

---

## TODO তালিকা

### ধাপ ১ — .kilocoderules পর্যালোচনা ও migrate
- [ ] বিদ্যমান `broxlab.agent.md` ও `.kilocoderules` (যদি থাকে) পড়া
- [ ] `.kilocode/rules/` ফোল্ডার তৈরি করা

### ধাপ ২ — coding.md তৈরি
- [ ] PHP 8 naming convention (camelCase methods, PascalCase classes)
- [ ] PSR-4 autoloading অনুসরণের নিয়ম
- [ ] Twig template ব্যবহারের নিয়ম
- [ ] JavaScript (esbuild/npm) কোড স্টাইল
- [ ] Tailwind CSS class ordering নিয়ম
- [ ] Git commit message format (Conventional Commits)

### ধাপ ৩ — performance.md তৈরি
- [ ] MySQL query optimization নিয়ম (N+1 এড়ানো, index ব্যবহার)
- [ ] Cache (storage/cache/) ব্যবহারের নিয়ম
- [ ] Asset bundle size নিয়ম (esbuild output)
- [ ] AI API call batching ও rate limiting নিয়ম
- [ ] Log rotation ও storage/ ক্লিনআপ নিয়ম

### ধাপ ৪ — security.md তৈরি
- [ ] সংবেদনশীল ফাইল তালিকা (.env, Config/Db.php, Config/Constants.php)
- [ ] CSRF protection ব্যবহারের নিয়ম
- [ ] Input sanitization চেকলিস্ট
- [ ] API key এক্সপোজ না করার নিয়ম

### ধাপ ৫ — rules-code/php-patterns.md তৈরি
- [ ] Custom framework Controller লেখার প্যাটার্ন
- [ ] Model (PDO query) লেখার প্যাটার্ন
- [ ] Middleware তৈরির প্যাটার্ন
- [ ] Module (app/Modules/) স্ট্রাকচার

### ধাপ ৬ — skills/ai-feature/SKILL.md তৈরি
- [ ] AI conversation flow বোঝানো
- [ ] OpenRouter/Anthropic API call প্যাটার্ন
- [ ] Knowledge base integration নিয়ম
- [ ] Streaming response হ্যান্ডেলিং
- [ ] Self-improving agent feedback loop বোঝানো

### ধাপ ৭ — skills/api-design/SKILL.md তৈরি
- [ ] BroxLab API endpoint naming convention (/api/*)
- [ ] Request/Response format (JSON)
- [ ] Error handling pattern
- [ ] Authentication middleware ব্যবহার

### ধাপ ৮ — skills/db-query/SKILL.md তৈরি
- [ ] PDO prepared statement প্যাটার্ন
- [ ] Model এর query method লেখার নিয়ম
- [ ] Transaction ব্যবহারের নিয়ম
- [ ] Migration/schema আপডেটের নিয়ম

### ধাপ ৯ — পরীক্ষা
- [ ] VS Code reload করে skills detection যাচাই
- [ ] একটি test task দিয়ে rules কার্যকর কিনা দেখা

---

## গুরুত্বপূর্ণ নোট

- `.kilocoderules` এখন **deprecated** — নতুন সিস্টেম `.kilocode/rules/` ফোল্ডার
- `broxlab.agent.md` বিদ্যমান আছে — এটি পড়ে rules এ merge করতে হবে
- Skills এর `name` field অবশ্যই ফোল্ডারের নামের সাথে মিলতে হবে
- VS Code reload না করলে নতুন skills কাজ করবে না
