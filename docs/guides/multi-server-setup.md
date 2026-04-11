# একাধিক Node.js সার্ভার ইনস্ট্যান্স চালানো

এই গাইডে দেখানো হয়েছে কিভাবে BroxLab প্রজেক্টে একাধিক Node.js সার্ভার ইনস্ট্যান্স একসাথে চালাতে হয়। প্রতিটি সার্ভার আলাদা পোর্টে চলবে এবং সম্পূর্ণ স্বাধীনভাবে কাজ করবে।

## উপলব্ধ সার্ভার

- **Unified Server**: AI র‍্যাগ সিস্টেম (পোর্ট: 3002, 3003)
- **AI Assistant**: AI চ্যাটবট সার্ভার (পোর্ট: 3001, 3004)

> ℹ️ `npm run node:start` is the single entrypoint that powers every Node server. Multi-server helpers simply set `PORT` (and `AI_ASSISTANT_PORT` for backwards compatibility) before running the same command.

## চালানোর উপায়

### ১. PM2 দিয়ে (প্রোডাকশনের জন্য সবচেয়ে ভালো)

```bash
# একাধিক সার্ভার চালু করুন
npm run all:start:multi

# সার্ভারের স্ট্যাটাস দেখুন
pm2 list

# লগ দেখুন
pm2 logs

# সব সার্ভার বন্ধ করুন
pm2 stop all
pm2 delete all
```

### ২. Node.js স্ক্রিপ্ট দিয়ে (ডেভেলপমেন্টের জন্য)

```bash
# একাধিক সার্ভার চালু করুন
npm run multi-server

# বা সরাসরি
node scripts/multi-server.js start
```

### ৩. Windows Batch File দিয়ে

```cmd
# Command Prompt থেকে
scripts\multi-server.bat
```

### ৪. PowerShell দিয়ে

```powershell
# একাধিক সার্ভার চালু করুন
.\scripts\multi-server.ps1

# সার্ভারের স্ট্যাটাস দেখুন
.\scripts\multi-server.ps1 -Status

# সব সার্ভার বন্ধ করুন
.\scripts\multi-server.ps1 -Stop
```

## সার্ভার URLs

সব সার্ভার চালু হলে এই URLs-এ অ্যাক্সেস করতে পারবেন:

- **Unified Server 1**: http://localhost:3002
- **Unified Server 2**: http://localhost:3003
- **AI Assistant 1**: http://localhost:3001
- **AI Assistant 2**: http://localhost:3004

## কনফিগারেশন কাস্টমাইজ করা

### PM2 কনফিগারেশন (src/ecosystem-multi.config.cjs)

```javascript
{
  "apps": [
    {
      "name": "unified-server-3002",
      "script": "node",
      "args": ["./node_modules/tsx/dist/cli.mjs", "src/index.ts"],
      "env": {
        "NODE_ENV": "production",
        "PORT": 3002
      }
    },
    // আরও সার্ভার যোগ করুন...
  ]
}
```

### এনভায়রনমেন্ট ভেরিয়েবল

- **Unified Server**: `PORT=3002`
- **AI Assistant**: `PORT=3001` (set `AI_ASSISTANT_PORT` for legacy tooling if needed)

## ট্রাবলশুটিং

### পোর্ট কনফ্লিক্ট

```bash
# কোন পোর্ট ব্যবহৃত হচ্ছে দেখুন
netstat -tulpn | grep :300
# বা Windows-এ
netstat -ano | findstr :300
```

### লগ দেখা

```bash
# PM2 লগ
pm2 logs

# ইন্ডিভিজুয়াল লগ ফাইল
tail -f logs/unified-server-3002.log
tail -f logs/ai-assistant-3001.log
```

### সার্ভার স্টপ করা

```bash
# PM2 দিয়ে
pm2 stop all
pm2 delete all

# প্রসেস দিয়ে
pkill -f "node.*src/index.ts"
pkill -f "tsx.*src/index.ts"
```

## পারফরম্যান্স কনসিডারেশন

- প্রতিটি সার্ভার ইনস্ট্যান্স আলাদা প্রসেস হিসেবে চলে
- মেমরি এবং CPU ব্যবহার দ্বিগুণ হবে
- লোড ব্যালেন্সিং এর জন্য nginx বা HAProxy ব্যবহার করুন
- ডাটাবেস কানেকশন পুল কনফিগার করুন

## মনিটরিং

সার্ভারের স্বাস্থ্য চেক করুন:

```bash
# Health endpoints
curl http://localhost:3002/health
curl http://localhost:3001/ai-health
```

## উন্নত ব্যবহার

### কাস্টম কনফিগারেশন

```bash
# আলাদা এনভায়রনমেন্ট ফাইল
cp .env .env.server1
cp .env .env.server2

# ভিন্ন ভিন্ন সেটিংস দিয়ে চালান
PORT=3005 node scripts/multi-server.js
```

### Docker দিয়ে

```dockerfile
# Dockerfile.multi
FROM node:20-alpine

# একাধিক সার্ভার কন্টেইনার তৈরি করুন
COPY . /app
WORKDIR /app

# PM2 দিয়ে চালান
CMD ["npm", "run", "all:start:multi"]
```
