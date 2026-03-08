# Telegram Bot Modular System Setup Guide

## 1. Database Setup
Run the migration file located at:
`Database/migrations/2026-03-05_create_telegram_system_tables.sql`

This will create:
- `feature_flags`: To control module access.
- `devices`: To register Android SMS Gateway devices.
- `sms_logs`: To store incoming and outgoing messages.
- `telegram_user_mapping`: To map Telegram IDs to internal users.

## 2. Telegram Bot Configuration
1. Create a new bot via [@BotFather](https://t.me/botfather) and get the **API Token**.
2. Set the token in the **Admin Panel > Telegram Settings**.
   - **Bot Token**: Paste your API Token.
   - **Webhook Secret**: Generate a secure random string and paste it.
3. Alternatively, you can still use `.env` but Database settings will OVERRIDE them if present.
4. Register the webhook by visiting:
   `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/api/telegram/webhook&secret_token=<SECRET>`

## 3. Android Gateway Setup
1. The system expects a JSON-based webhook from the Android device.
2. Webhook Endpoint: `https://yourdomain.com/api/sms/incoming`
3. Payload format:
   ```json
   {
       "from": "+8801700000000",
       "message": "Hello from Android!",
       "device_id": 1,
       "sim": 1
   }
   ```

## 4. Admin Control
- Navigate to **Admin > Feature Flags** to enable/disable modules.
- Navigate to **Admin > Telegram Settings** to update bot credentials.
- Navigate to **Admin > SMS Logs** to monitor gateway activity.
