# AI SYSTEM / OpenRouter Key (Documentation)

## Overview

This document explains how the AI SYSTEM system works in the BroxBhai project, specifically how OpenRouter API key handling works, and how the "Test Key" feature behaves.

এই ডকুমেন্টে BroxBhai প্রোজেক্টে AI সেটিংস সিস্টেম কিভাবে কাজ করে তা ব্যাখ্যা করা হয়েছে, বিশেষ করে OpenRouter API কী কীভাবে হ্যান্ডেল করা হয় এবং "Test Key" ফিচার কীভাবে কাজ করে।

---

## 1. Key Storage & Source Detection

### English
- The system stores the OpenRouter API key in the database (`ai_settings.openrouter_api_key`).
- When the key is retrieved, the system records whether it came from the database (`db`). If the key is missing or empty, it is considered missing.

### বাংলা
- সিস্টেমটি OpenRouter API কী ডাটাবেসে ( `ai_settings.openrouter_api_key` ) রাখে।
- যখন কী পড়া হয়, সিস্টেমটি রেকর্ড করে যে এটি ডাটাবেস (`db`) থেকে এসেছে। যদি কী অনুপস্থিত বা খালি হয়, তাহলে কী অনুপস্থিত বলে গণ্য করা হয়।

---

## 2. Admin UI Behavior (AI SYSTEM Page)

### English
- On the **AI SYSTEM** admin page, each provider row includes:
  - Provider name + icon
  - Current key status (Configured / Missing)
  - A **Test Key** button for OpenRouter

- The UI no longer shows any warning about the key "looking like an OpenAI key" (no `sk-` heuristic).
- The OpenRouter key status is derived purely from whether a key exists and where it comes from.

### বাংলা
- **AI SYSTEM** অ্যাডমিন পেইজে, প্রতিটি প্রোভাইডার রো-তে থাকে:
  - প্রোভাইডারের নাম ও আইকন
  - বর্তমান কী স্ট্যাটাস (কনফিগার্ড / অনুপস্থিত)
  - OpenRouter-এর জন্য **Test Key** বাটন

- UI-তে আর কোনো “OpenAI কী মনে হচ্ছে” ধরনের সতর্কতা দেখানো হয় না ( `sk-` চেক আর নেই )।
- OpenRouter কী স্ট্যাটাস কেবল কী আছে কি না এবং কোথা থেকে এসেছে তার উপর ভিত্তি করে নির্ধারণ করা হয়।

---

## 3. Test Key Endpoint & Behavior

### English
- The **Test Key** button triggers a backend request to `/admin/ai-settings/update-provider?action=test`.
- The backend uses `AIProvider::testConnection()` to attempt a connection to OpenRouter using the stored key.
- The response is returned transparently; any OpenRouter error (e.g., 401, invalid key, rate limits) is shown instead of a heuristic message.

### বাংলা
- **Test Key** বাটনটি ব্যাকএন্ডে `/admin/ai-settings/update-provider?action=test` রিকোয়েস্ট করে।
- ব্যাকএন্ড `AIProvider::testConnection()` ব্যবহার করে সংরক্ষিত কী দিয়ে OpenRouter-এ সংযোগ চেষ্টা করে।
- মেসেজটি সরাসরি রিটার্ন করা হয়; OpenRouter-এর কোনো ত্রুটি (যেমন 401, অবৈধ কী, রেট লিমিট) হিউরিস্টিক মেসেজের বদলে দেখানো হয়।

---

## 4. Frontend Assistant Key Status (Public Assistant)

### English
- The public assistant fetches `/api/ai-system/frontend` to learn which provider and key is configured.
- The assistant shows the OpenRouter key status (configured / missing), but no longer warns about OpenAI-style keys.

### বাংলা
- জনসাধারণের অ্যাসিস্ট্যান্ট `/api/ai-system/frontend` থেকে জানতে পারে কোন প্রোভাইডার ও কী কনফিগার করা আছে।
- অ্যাসিস্ট্যান্ট OpenRouter কী স্ট্যাটাস দেখায় (কনফিগার্ড / অনুপস্থিত), কিন্তু আর OpenAI-স্টাইল কী নিয়ে সতর্ক করে না।

---

## 5. Notes & Troubleshooting

### English
- If you get a 401 “User not found” or other OpenRouter error, the key is likely invalid or expired; update it via the AI SYSTEM page.
- The system no longer tries to guess key types based on prefix (`sk-`) — it relies on OpenRouter’s actual response.

### বাংলা
- যদি 401 “User not found” বা অন্য কোনো OpenRouter ত্রুটি দেখায়, তাহলে সম্ভবত কীটি অবৈধ বা মেয়াদোত্তীর্ণ; AI SYSTEM পেইজ থেকে কী আপডেট করুন।
- সিস্টেম আর কী প্রিক্স (`sk-`) দেখে কী টাইপ অনুমান করে না — এটি OpenRouter-এর আসল রেসপন্সের উপর নির্ভর করে।
