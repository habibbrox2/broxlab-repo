# Public Assistant Behavior (Documentation)

## Overview

This document describes how the public-facing assistant (chat widget) behaves in the BroxBhai project, including how it selects providers, uses API keys, falls back to Puter, and displays key status.

এই ডকুমেন্টে BroxBhai প্রোজেক্টের পাবলিক অ্যাসিস্ট্যান্ট (চ্যাট উইজেট) কীভাবে কাজ করে তা ব্যাখ্যা করা হয়েছে — কোন প্রোভাইডার ব্যবহার হয়, কীভাবে কী ব্যবহৃত হয়, Puter-এ কিভাবে fallback ঘটে, এবং কী স্ট্যাটাস কিভাবে দেখানো হয়।

---

## 1. Provider & Key Initialization

### English
- The assistant initializes by calling `/api/ai-settings/frontend`.
- That endpoint returns which provider is configured for the frontend (e.g., `openrouter`, `fireworks`, or a custom provider) and the API key for that provider.
- The assistant stores the provider and model in memory, and uses the key to make requests.

### বাংলা
- অ্যাসিস্ট্যান্ট `/api/ai-settings/frontend` এ কল করে শুরু হয়।
- সেই এন্ডপয়েন্টটি বলে দেয় কোন প্রোভাইডার ফ্রন্টএন্ডের জন্য কনফিগার করা আছে (যেমন `openrouter`, `fireworks`, বা একটি কাস্টম প্রোভাইডার) এবং সেই প্রোভাইডারের API কী কী।
- অ্যাসিস্ট্যান্ট সেই প্রোভাইডার ও মডেল মনে রাখে এবং কী ব্যবহার করে রিকোয়েস্ট পাঠায়।

---

## 2. OpenRouter Key Status Display

### English
- When OpenRouter is the selected provider, the assistant shows a small status message:
  - **Configured** if an API key exists.
  - **Missing** if no key is configured.
- It no longer warns about “OpenAI-style” keys; the status is based only on key presence.

### বাংলা
- OpenRouter নির্বাচন করা হলে, অ্যাসিস্ট্যান্ট একটি ছোট স্ট্যাটাস বার দেখায়:
  - কী থাকলে **Configured**।
  - কী না থাকলে **Missing**।
- আর “OpenAI-স্টাইল” কী নিয়ে কোনো সতর্কতা দেখানো হয় না; স্ট্যাটাস শুধুমাত্র কী আছে কিনা তার উপর ভিত্তি করে।

---

## 3. Fallback to Puter (Client-side)

### English
- If the configured provider fails (for example, OpenRouter returns a 401 or the network fails), the assistant will optionally fallback to Puter.js.
- Puter is a client-side JavaScript bundle used only for fallback; it is not managed in the backend provider list or configuration pages.

### বাংলা
- যদি কনফিগার করা প্রোভাইডার ব্যর্থ হয় (উদাহরণস্বরূপ, OpenRouter 401 দেয় বা নেটওয়ার্ক সমস্যা হয়), অ্যাসিস্ট্যান্ট ঐচ্ছিকভাবে Puter.js-এ fallback করবে।
- Puter একটি ক্লায়েন্ট-সাইড জাভাস্ক্রিপ্ট বান্ডেল, যা শুধুমাত্র fallback-এর জন্য ব্যবহৃত হয়; এটি ব্যাকএন্ডের প্রোভাইডার লিস্ট বা কনফিগারেশন পেজে থাকে না।

---

## 4. Error Reporting

### English
- Errors from the chosen provider are shown directly in the chat UI (e.g., invalid key, rate limits, network errors).
- The system does not perform additional heuristics or key-type guessing; it simply shows whatever the provider returns.

### বাংলা
- নির্বাচিত প্রোভাইডার থেকে যে কোনো ত্রুটি সরাসরি চ্যাট UI-তে দেখানো হয় (যেমন: অবৈধ কী, রেট লিমিট, নেটওয়ার্ক ত্রুটি)।
- সিস্টেম আর কোনো অতিরিক্ত হিউরিস্টিক বা কী-টাইপ অনুমান করে না; এটি যা রিটার্ন করে তাই দেখায়।

---

## 5. How to Update the Key

### English
- Update the OpenRouter key via the AI Settings admin page.
- After updating, the assistant will automatically use the new key on the next load (or after a page refresh).

### বাংলা
- OpenRouter কী অ্যাডমিনের AI Settings পেজ থেকে আপডেট করুন।
- আপডেটের পর, অ্যাসিস্ট্যান্ট পরবর্তী লোড (বা পেজ রিফ্রেশ) এ নতুন কী ব্যবহার করবে।
