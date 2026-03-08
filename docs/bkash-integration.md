bKash Integration Guide (Broxbhai)

সংক্ষিপ্ত পরিচিতি

এই ডকুমেন্টটি `broxbhai` প্রজেক্টে bKash Checkout/Callback ইন্টিগ্রেশন করার ধাপগুলো সহজ ভাষায় (বাংলায়) ব্যাখ্যা করে। এতে কনফিগারেশন, ফ্রন্টএন্ড ব্যবহার, সার্ভার সাইড কলব্যাক ভেরিফিকেশন এবং টেস্টিং কিভাবে করতে হবে তা রয়েছে।

প্রয়োজনীয় সেটিংস

1. App Security Settings (পছন্দসই):
   - `bkash_mode` = `sandbox` বা `production`
   - `bkash_app_key` = bKash-provided App Key
   - `bkash_auth_token` = bKash-provided bearer token (sandbox/production অনুযায়ী)

   বিকল্পভাবে পরিবেশ ভ্যারিয়েবল ব্যবহার করা যাবে:
   - `BKASH_MODE`
   - `BKASH_APP_KEY`
   - `BKASH_AUTH_TOKEN`

2. `site_url` সেট করা থাকলে callback URL এর জন্য ব্যবহার করা হবে, না হলে `/payments/bkash/callback` ব্যবহার করা হবে।

ফাইল/রুটস (এই প্রজেক্টে করা পরিবর্তনসমূহ)

- নতুন ক্লাস: `helpers/BkashGateway.php` — bKash API (create, execute, query) কলের জন্য হেলপার (classes/ থেকে সরানো হয়েছে)।
- কন্ট্রোলার: `controllers/ServiceApplicationController.php`
  - Apply হ্যান্ডলারে: `payment_mode=gateway` এবং gateway = `bkash` হলে `BkashGateway::createPayment` কল করা হবে।
  - Callback রুট: POST `/payments/bkash/callback` এবং GET `/payments/bkash/callback` যোগ করা হয়েছে।
- মডেল: `classes/ServiceApplicationModel.php`
  - `findByPaymentTransactionId()` এবং `completePayment()` মেথড যোগ করা হয়েছে।
- টেমপ্লেট: `templates/services/view.twig`
  - Gateway dropdown পরিবর্তে লোগো-বাটন দেখানো হবে ( `/assets/payments/{gateway}.svg` রাখলে ভালো)।

পেমেন্ট ফ্লো (সংক্ষিপ্ত)

1. ইউজার সার্ভিস ফর্ম পূরণ করে Apply চাপবে। যদি সার্ভিসটি প্রিমিয়াম হয় modal-এ "Pay via Gateway" চয়ন করলে লোগো বাটন থেকে bKash চয়ন করা যাবে।
2. ফর্ম সাবমিশনে সাইনআপ/গেস্ট আইডি ব্যবহার করে `ServiceApplicationModel::submit()` করা হবে।
3. যদি gateway init সফল হয়, `application_data._payment`-এ gateway response সংরক্ষিত হবে (উদাহরণ: `transaction_id` বা `paymentID`).
4. bKash সার্ভার থেকে সফল ট্রানজ্যাকশনের নোটিফিকেশন আসলে আমাদের callback রুট গ্রহণ করবে এবং ট্রানজ্যাকশনের `trxID`/`paymentID` দেখে সংশ্লিষ্ট application-কে `completePayment()` কল করে পেমেন্ট চিহ্নিত করবে এবং স্ট্যাটাস আপডেট করবে।

Callback ভ্যারিফিকেশন (Signatures)

bKash webhooks (Instant Payment Notification) একটি সিগনড পে‌লোড পাঠায় — ডকুমেন্টেশনে AWS SNS-ধাঁচের `Signature`, `SigningCertURL`, `SignatureVersion` ইত্যাদি উদাহরণ আছে। আমরা নিম্নলিখিতভাবে যাচাই করি:

1. রিকোয়েস্ট JSON বডি যদি AWS SNS-ধাঁচে হয় (`Type`, `Message`, `Signature`, `SigningCertURL`), তাহলে `verify_sns_signature()` ফাংশন ব্যবহার করে ভেরিফাই করি।
2. ভেরিফিকেশন স্টেপগুলো সংক্ষেপে:
   - `SignatureVersion` চেক (কখনো 1 দেখা যায়)।
   - `SigningCertURL` একটি HTTPS URL এবং হোস্ট `amazonaws.com` অথবা `bka.sh`/`bkash` সম্পর্কিত হতে হবে (প্রোডাকশনে আপনার টিম যদি ভিন্ন হোস্ট জানায় সেটা ব্যবহার করুন)।
   - সার্টিফিকেট ফেচ করে (HTTPS). সার্টিফিকেট থেকে পাবলিক কী নিয়ে `Signature` (base64) ভেরিফাই করতে হবে।
   - আমরা AWS SNS Signature Version 1 অনুযায়ী `stringToSign` তৈরি করি (Notification/SubscriptionConfirmation টাইপের জন্য আলাদা ফিল্ড অর্ডার আছে) এবং OpenSSL (`openssl_verify`) দিয়ে যাচাই করি।

3. যদি ভেরিফিকেশন ব্যর্থ হয়, সার্ভার 403 রিটার্ন করে এবং ইভেন্ট প্রোসেস করা হবে না।

সিকিউরিটি টিপস

- `SigningCertURL` গ্রহণের সময় হোস্ট যাচাই করুন (trusted hosts) এবং HTTPS বাধ্যতামূলক রাখুন।
- সার্টিফিকেট বা পাবলিক কী স্থানীয়ভাবে ক্যাশ করলে latency কমে এবং ডস থেকে কিছুটা সুরক্ষা পাওয়া যায় — তবে ক্যাশে টেকনিক্যালি এক্সপিারি চেক যোগ করুন।
- GET রিডাইরেক্ট রুটটি সাধারণত ইউজার ব্রাউজার রিডাইরেক্টের জন্য; প্রকৃত নোটিফিকেশন/যাচাই সবসময় POST (webhook) এ করুন।

টেস্টিং

1. Sandbox credentials লাগবে — `bkash_mode=sandbox` এবং ব্যবহারযোগ্য `BKASH_APP_KEY` ও `BKASH_AUTH_TOKEN` যোগ করুন।
2. সার্ভার চালু করে সার্ভিস পেজে apply করে gateway=bkash চয়ন করুন।
3. স্যান্ডবক্সে createPayment সফল হলে response-এ `paymentID` থাকবে — সেটি application_data-তে সেভ হবে।
4. Webhook-ইমিট (sandbox) বা ম্যানুয়াল POST করে `/payments/bkash/callback` এ JSON পাঠান: যদি SNS-ধাঁচে পাঠাতে চান, নিশ্চিত সিগনেচার/SigningCertURLসহ পাঠান; ডেভ-টেস্টের জন্য আপনি signature ভেরিফিকেশন বাইপাস করে দ্রুত প্রোসেস করতে পারেন (কিন্তু প্রোডাকশনে অবশ্যই ভেরিফাই চালাবেন)।

উদাহরণ (সাধারণ POST, ডেভ টেস্ট):

POST /payments/bkash/callback
Content-Type: application/json

{
  "paymentID": "PAYMENT12345"
}

এবং সিস্টেম সেই paymentID খুঁজে `findByPaymentTransactionId()` চালাবে এবং `completePayment()` অ্যাপ্লিকেশন আপডেট করবে।

Deployment checklist

- [ ] `BKASH_APP_KEY`, `BKASH_AUTH_TOKEN`, `BKASH_MODE` পরিবেশভেরিয়েবল বা `app_security_settings` এ যুক্ত করা
- [ ] TLS 1.2+ চালু (bKash TLS מינিমাম চাহিদা)
- [ ] `/payments/bkash/callback` URL bKash technical team কে দেওয়া
- [ ] `/assets/payments/bkash.svg` (logo) যোগ করা

Help / Next steps

চাইলে আমি callback-এ certificate caching, retry logic, অথবা অ্যাডমিন UI-তে gateway_response দেখানোর জন্য একটি ছোট প্যানেল যোগ করে দিতে পারি।

---
Broxbhai — bKash Integration Notes
