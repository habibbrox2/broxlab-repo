# GitHub Webhook Deployment Guide

## Overview

This guide explains how to set up automated deployments using GitHub Webhooks. When you push code to your GitHub repository, GitHub will send a webhook to your server, which can automatically trigger a deployment.

## Security Features

- **Signature Verification**: HMAC-SHA256 signature validation ensures only legitimate GitHub requests trigger deployments
- **Branch Filtering**: Only specified branch changes trigger deployments
- **Event Filtering**: Choose which GitHub events trigger deployments
- **Delivery Logging**: All webhook deliveries are logged for auditing

## Setup Steps

### Step 1: Configure Webhook in Admin Panel

1. Navigate to **Admin Panel → Deploy Tools → GitHub Webhook**
2. Enable the webhook by toggling "Enable GitHub Webhook"
3. Set your target branch (default: `main`)
4. Choose which events should trigger deployments:
   - **Push**: Triggers on any push to the repository
   - **Release**: Triggers when a new release is published
   - **Workflow Run**: Triggers when a GitHub Actions workflow runs
5. Optionally enable "Auto-deploy" to automatically run deployments
6. Click "Save Settings"

### Step 2: Generate Webhook Secret (Recommended)

1. Click "Generate New Secret" in the admin panel
2. Copy the generated secret
3. You'll need this for GitHub webhook configuration

### Step 3: Configure GitHub Repository

1. Go to your GitHub repository
2. Navigate to **Settings → Webhooks**
3. Click "Add webhook"
4. Configure the following:
   - **Payload URL**: `https://yourdomain.com/webhook/github.php`
   - **Content type**: `application/json`
   - **Secret**: Paste the secret from Step 2
   - **Events**: Select "Pushes" (or customize as needed)
5. Click "Add webhook"

### Step 4: Test the Webhook

1. In the admin panel, click "Test Webhook"
2. Check the "Recent Webhook Deliveries" section
3. You should see a new entry with status "Triggered" or "Ignored"

### Step 5: Push Code to Deploy

1. Make changes to your code
2. Push to the configured branch
3. GitHub will send a webhook to your server
4. If auto-deploy is enabled, deployment will start automatically
5. If not, you can manually trigger deployment from the Deploy Tools dashboard

## Webhook Endpoint

The webhook endpoint is: `POST /webhook/github.php`

This endpoint:
- Accepts GitHub webhook payloads
- Verifies the HMAC-SHA256 signature (if secret is configured)
- Filters by branch and event type
- Queues deployment jobs when conditions are met

## Troubleshooting

### Webhook Not Triggering

1. **Check the webhook URL**: Ensure it's accessible from the internet
2. **Verify the secret**: Make sure it matches in both GitHub and your admin panel
3. **Check branch**: Ensure you're pushing to the configured branch
4. **Review logs**: Check "Recent Webhook Deliveries" in the admin panel

### Deployment Not Starting

1. Ensure "Auto-deploy" is enabled
2. Check that the branch matches
3. Verify the deployment job is being created in the Jobs tab
4. Check server logs for any errors

### Signature Verification Failed

1. Ensure the webhook secret is correctly configured in GitHub
2. Regenerate the secret in admin panel and update GitHub
3. Check that the server is receiving the correct headers

## API Reference

### Webhook Endpoint

```
POST /webhook/github.php
```

**Headers:**
- `X-GitHub-Event`: The type of event (push, release, workflow_run)
- `X-GitHub-Delivery`: Unique delivery ID
- `X-Hub-Signature-256`: HMAC-SHA256 signature (**required**)

**Request Body:** GitHub webhook payload

**Response:**
```json
{
  "success": true,
  "message": "Deployment queued",
  "job_id": 123,
  "branch": "main",
  "event": "push"
}
```

### Admin API Endpoints

- `GET /admin/deploy-tools/webhook` - View webhook configuration
- `POST /admin/deploy-tools/webhook/update` - Update webhook settings
- `POST /admin/deploy-tools/webhook/test` - Test webhook endpoint
- `GET /admin/deploy-tools/webhook/logs` - Get webhook delivery logs

## Environment Variables

Production defaults are intentionally strict:

- `WEBHOOK_ADMIN_ACTIONS_ENABLED=0` (admin actions disabled by default)
- `WEBHOOK_AUTO_DEPLOY_ALLOWED=0` (server-side auto-deploy disabled by default)

The webhook system otherwise uses the existing database settings storage.

## Notes

- The webhook endpoint is public (no authentication required) but secured by signature verification
- Standalone webhook admin actions (`action=status|versions|rollback`) require `X-Api-Key` and are disabled unless `WEBHOOK_ADMIN_ACTIONS_ENABLED=1`
- All deployments are queued and executed asynchronously
- You can monitor deployment progress in the Deploy Tools dashboard
- Webhook deliveries are logged for auditing purposes
