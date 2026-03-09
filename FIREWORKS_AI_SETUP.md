# Fireworks AI Integration Guide

## Current Status

✅ **Successfully Configured:**
- Fireworks AI provider is in the database (ID: 8)
- 13 models available
- API key configured: `fw_J...DcXe`
- Endpoint: `https://api.fireworks.ai/inference/v1/chat/completions`

⚠️ **Needs Activation:**
- Provider status: **Inactive** (needs to be activated in admin panel)
- Connection test needs model verification

## Setup Steps

### 1. Activate Fireworks AI Provider

Go to **Admin Panel → AI Settings** (`/admin/ai-settings`)

- Scroll down to the **AI Providers** table
- Find **Fireworks AI**
- Toggle the Status switch to **Active**

### 2. Test Connection

In the Fireworks AI row, click the **Test** button to verify the connection.

### 3. Configure Default Provider (Optional)

To use Fireworks AI as your default provider:

**Frontend (Website Assistant):**
- Select "Frontend AI (Assistant)" dropdown
- Choose **Fireworks AI** (or keep as Puter.js)

**Backend (AutoContent):**
- Select "Backend AI Provider (AutoContent)" dropdown
- Choose **Fireworks AI**

### 4. Select Model

Choose a model from the available list:

**Recommended Models:**
- `llama-v3.1-405b-instruct` - Most powerful Llama model
- `llama-v3.1-70b-instruct` - Fast & capable 70B variant
- `qwen2-72b-instruct` - Good alternative
- `mixtral-8x7b-instruct-v0.1` - Smaller, faster

### Available Models

| Model ID | Display Name |
|----------|--------------|
| `llama-v3.1-70b-instruct` | Llama 3.1 70B |
| `llama-v3.1-405b-instruct` | Llama 3.1 405B |
| `llama-v3-70b-instruct` | Llama 3 70B |
| `llama-v3-8b-instruct` | Llama 3 8B |
| `qwen2-72b-instruct` | Qwen2 72B |
| `qwen2-7b-instruct` | Qwen2 7B |
| `mixtral-8x7b-instruct-v0.1` | Mixtral 8x7B |
| `phi-3.5-mini-instruct` | Phi-3.5 Mini |
| `gemma2-9b-instruct` | Gemma 2 9B |
| `deepseek-coder-v2-instruct` | DeepSeek Coder V2 |
| `deepseek-llm-67b-chat` | DeepSeek LLM 67B |
| `minimax-m2.1` | MiniMax M2.1 |
| `minimax-m2.5` | MiniMax M2.5 |

## API Key Management

### Method 1: Admin Panel (Recommended)

1. Go to `/admin/ai-settings`
2. Scroll to **API Keys** section
3. Paste your API key in the **Fireworks AI API Key** field
4. Click **Save Settings**

### Method 2: Environment Variable

Add to your `.env` file:
```
FIREWORKS_API_KEY=fw_your_api_key_here
```

### Account ID (for model listing)

If you want the admin panel to automatically fetch your deployed models, 
also set your account identifier:
```
FIREWORKS_ACCOUNT_ID=accounts/your-account-id
```

This value appears in the Fireworks dashboard (top left or profile).
It is only needed for remote model listing; manual configuration still works.

### Getting Your API Key

1. Visit: https://console.fireworks.ai
2. Sign up or log in
3. Go to **API Keys** section
4. Create a new API key
5. Copy and safe (you'll only see it once!)

## How to Use

### 1. For Website Assistant (Frontend)
- When users interact with the website assistant chat
- Select "Fireworks AI" as Frontend Provider
- The assistant will use Fireworks AI models

### 2. For Content Generation (AutoContent)
- When generating content via AutoContent feature
- Select "Fireworks AI" as Backend Provider
- Content will be generated using Fireworks AI

### 3. Direct API Usage (Advanced)
In your PHP code:
```php
$aiProvider = new AIProvider($mysqli);

$result = $aiProvider->callAPI(
    'fireworks',
    'llama-v3.1-70b-instruct',
    'Your prompt here'
);

if ($result['success']) {
    echo $result['content'];
} else {
    echo 'Error: ' . $result['error'];
}
```

## Troubleshooting

### Connection Test Fails with 404

**Issue:** Model not found or not deployed
- **Solution:** Check if the model name is correct
- Visit https://fireworks.ai/docs/models for current model availability
- Use the exact model identifier provided in the admin panel

### API Key Not Working

**Issue:** Authentication fails
- **Solution:** Verify API key is correct (sensitive to spaces/characters)
- Generate a new API key from console.fireworks.ai
- Make sure API key is updated in admin panel

### Rate Limits Exceeded

**Issue:** Getting rate limit errors
- **Solution:** Check your Fireworks AI plan
- Consider using fallback providers
- Enable fallback in AI Settings: "Enable Provider Fallback"

### Streaming Not Working

Fireworks AI supports streaming (configured to true)
- Used for real-time response generation
- Automatically handled by the system
- No additional configuration needed

## Security Notes

- API keys are stored encrypted in the database
- Never commit API keys to version control
- Use environment variables for production
- Rotate API keys periodically
- Monitor API usage in console.fireworks.ai

## Testing Command

Run the integration test script:
```bash
php scripts/test-fireworks-integration.php
```

This verifies:
- Database connection
- Provider configuration
- API key setup
- Connection status
- Available models

## Documentation References

- **Fireworks AI Docs:** https://docs.fireworks.ai
- **API Reference:** https://docs.fireworks.ai/api/
- **Quickstart:** https://docs.fireworks.ai/getting-started/quickstart
- **Models:** https://fireworks.ai/docs/models

## Support

If you encounter issues:
1. Check the test script output: `php scripts/test-fireworks-integration.php`
2. Review error logs in `/storage/logs/`
3. Visit Fireworks AI support: https://github.com/FireworksAI/python-sdk/issues
4. Check BroxBhai documentation for general setup help
