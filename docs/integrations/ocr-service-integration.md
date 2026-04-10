# OCR Service Integration Guide

## Summary
Documentation of the Tesseract.js OCR service, its architecture, and how BroxLab consumes extracted text for automation workflows.

## Purpose
Help engineers start, monitor, and secure the OCR service while keeping service fallbacks and dependency notes centralized.

## Key Actions
- Run the OCR service via the provided scripts (`start-ocr-service.bat`, `node src/ocr-service.js`, or PM2`) on port 7020 by default.\n- Provide the optional OCR.space fallback key when capturing images that fail in-house extraction.\n- Pipe output through `NodeOCRClient.php` so controllers can reuse sanitized text.

## Related References
- `docs/integrations/scraper-api.md` for the upstream scraping inputs.\n- `docs/project/project-context.md` for where OCR fits inside the platform architecture.\n- `docs/guides/coding-standards.md` for input sanitization requirements.

## Service Overview
BroxLab now includes a **Tesseract.js-based OCR service** running on port 7020. This service provides cloud-free, fast text extraction from images via REST API endpoints.

## Service Architecture

```
PHP Code (app/Controllers, app/Models)
    ↓
NodeOCRClient.php (PHP HTTP wrapper)
    ↓
Express.js OCR Service (port 7020)
    ↓
Tesseract.js (JavaScript OCR engine)
```

The service also falls back to **OCR.space API** (cloud service with paid key K81289438988957) if needed.

## Starting the Service

### Option 1: Windows Batch Script
```powershell
.\start-ocr-service.bat
```

### Option 2: Direct Node.js Command
```bash
node src/ocr-service.js
```

### Option 3: Background Process (PM2)
```bash
npm install -g pm2  # Install PM2 if not already installed
pm2 start src/ocr-service.js --name "ocr-service"
pm2 save
pm2 startup
```

### Option 4: Set Custom Port
```bash
OCR_SERVICE_PORT=8000 node src/ocr-service.js
```

## REST API Endpoints

### 1. Health Check
**GET** `/ocr/health`

Returns service status and available engines.

**Response:**
```json
{
  "status": "ok",
  "service": "Tesseract.js OCR Service",
  "port": 7020,
  "engines": ["tesseract.js"],
  "version": "1.0.0",
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

---

### 2. Single Image OCR
**POST** `/ocr/tesseract/image`

Extract text from a single image.

**Input Methods:**
- **File Upload:** Multipart form with `image` file
- **Base64 JSON:** Send `{"image": "base64-encoded-data"}` 
- **Query Parameter:** `?image=base64-encoded-data`

**Parameters:**
- `lang` (optional): Language code (default: `eng`)
  - Supported: `eng`, `deu`, `fra`, `spa`, `ita`, `por`, `rus`, `jpn`, `chi_sim`, `chi_tra`, etc.

**Request Examples:**

**cURL with file upload:**
```bash
curl -X POST \
  -F "image=@image.png" \
  -F "lang=eng" \
  http://localhost:7020/ocr/tesseract/image
```

**cURL with base64:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"image":"iVBORw0KGgoAAAANSUhEUgAA...", "lang":"eng"}' \
  http://localhost:7020/ocr/tesseract/image
```

**JavaScript/Node.js:**
```javascript
const FormData = require('form-data');
const fs = require('fs');
const axios = require('axios');

async function ocrImage(imagePath) {
  const form = new FormData();
  form.append('image', fs.createReadStream(imagePath));
  form.append('lang', 'eng');
  
  const response = await axios.post('http://localhost:7020/ocr/tesseract/image', form, {
    headers: form.getHeaders()
  });
  
  return response.data;
}
```

**Response:**
```json
{
  "success": true,
  "engine": "tesseract.js",
  "text": "Extracted text from image",
  "confidence": 0.92,
  "language": "eng",
  "boxes": [...],
  "words": [...],
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

---

### 3. Batch Image Processing
**POST** `/ocr/tesseract/batch`

Process multiple images at once.

**Input:**
- Multiple `images` files via multipart form
- OR `images` array of base64 strings in JSON body

**Parameters:**
- `lang` (optional): Language code (default: `eng`)

**Request Example (file upload):**
```bash
curl -X POST \
  -F "images=@image1.png" \
  -F "images=@image2.png" \
  -F "images=@image3.png" \
  -F "lang=eng" \
  http://localhost:7020/ocr/tesseract/batch
```

**Request Example (base64 array):**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "images": ["base64-string-1", "base64-string-2"],
    "lang": "eng"
  }' \
  http://localhost:7020/ocr/tesseract/batch
```

**Response:**
```json
{
  "success": true,
  "engine": "tesseract.js",
  "totalProcessed": 2,
  "results": [
    {
      "filename": "image1.png",
      "success": true,
      "text": "Text from first image",
      "confidence": 0.93
    },
    {
      "filename": "image2.png",
      "success": true,
      "text": "Text from second image",
      "confidence": 0.91
    }
  ],
  "language": "eng",
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

---

### 4. Auto-detect OCR
**POST** `/ocr/auto`

Attempts Tesseract.js first, with built-in fallback to OCR.space API.

**Input:** Same as single image OCR

**Response:** 
- Success: Same as Tesseract.js response
- Fallback: Returns OCR.space response or error

---

## PHP Integration

### Using NodeOCRClient.php

```php
<?php
require_once 'app/Helpers/NodeOCRClient.php';

$client = new NodeOCRClient('http://localhost:7020');

// Extract text from image file
$result = $client->extractTextFromImage('/path/to/image.png', 'eng');

if ($result['success']) {
    echo "Extracted text: " . $result['text'];
    echo "Confidence: " . $result['confidence'];
} else {
    echo "Error: " . $result['error'];
}
```

### Using OCRService.php (with fallback)

```php
<?php
require_once 'app/Helpers/OCRService.php';

$ocr = new OCRService();

// Automatically tries Node.js service first, then falls back to OCR.space API
$text = $ocr->extractTextFromImage('/path/to/image.png');
echo $text;
```

### Batch Processing

```php
<?php
require_once 'app/Helpers/NodeOCRClient.php';

$client = new NodeOCRClient('http://localhost:7020');

$images = [
    '/path/to/image1.png',
    '/path/to/image2.png',
    '/path/to/image3.png'
];

$results = $client->extractTextFromImages($images, 'eng');

foreach ($results as $result) {
    if ($result['success']) {
        echo "Image: " . $result['filename'] . "\n";
        echo "Text: " . $result['text'] . "\n\n";
    }
}
```

## Configuration

### Environment Variables

Create a `.env` file with:

```env
# OCR Service Configuration
OCR_SERVICE_URL=http://localhost:7020
OCR_SERVICE_PORT=7020
OCR_SERVICE_ENABLED=true

# Fallback: OCR.Space API (cloud service with paid tier)
OCR_SPACE_API_KEY=K81289438988957
OCR_SPACE_API_URL=https://api.ocr.space/parse/image
```

### NodeOCRClient Configuration

```php
<?php
// Override service URL
$client = new NodeOCRClient('http://your-custom-ocr-server:7020');

// Handle service unavailable
if (!$client->isHealthy()) {
    // Fall back to OCRService (uses OCR.space API)
    $ocr = new OCRService();
    $text = $ocr->extractTextFromImage($imagePath);
}
```

## Supported Languages

Tesseract.js supports 100+ languages via ONNX model:

**Common languages:**
- English: `eng`
- German: `deu`
- French: `fra`
- Spanish: `spa`
- Italian: `ita`
- Portuguese: `por`
- Russian: `rus`
- Japanese: `jpn`
- Chinese (Simplified): `chi_sim`
- Chinese (Traditional): `chi_tra`

**Full list:** https://github.com/naptha/tesseract.js/blob/master/docs/api.md

## Performance Tips

### 1. Language-Specific OCR
If you know the language, specify it explicitly for faster processing:
```php
$result = $client->extractTextFromImage($imagePath, 'deu'); // German
```

### 2. Resize Large Images
Downscale to reasonable DPI (100-150) before OCR:
```php
// Pseudo-code for image resizing
$image = imagecreatefromfile($path);
$width = imagesx($image);
$height = imagesy($image);
$newWidth = max(100, $width / 4);
$newHeight = max(100, $height / 4);
imagescale($image, $newWidth, $newHeight);
```

### 3. Cache Results
Store OCR results to avoid re-processing:
```php
$cacheKey = 'ocr_' . md5_file($imagePath);
$cached = Cache::get($cacheKey);
if (!$cached) {
    $cached = $client->extractTextFromImage($imagePath);
    Cache::set($cacheKey, $cached);
}
```

### 4. Batch Processing
Use `/ocr/tesseract/batch` for multiple images instead of individual requests.

## Troubleshooting

### Service Not Running
```bash
# Check if port 7020 is in use
netstat -ano | findstr :7020

# Find process using port
taskkill /PID <PID> /F

# Restart service
node src/ocr-service.js
```

### Out of Memory
Tesseract.js loads language models into memory. For multiple languages:
```bash
# Increase Node.js memory limit
node --max-old-space-size=4096 src/ocr-service.js
```

### Service Timeout
If processing takes too long, increase PHP timeout:
```php
set_time_limit(120); // 2 minutes for large images
```

### Fallback to Cloud OCR
If local service unavailable, OCRService.php automatically uses OCR.space API:
```php
try {
    $result = $client->extractTextFromImage($path);
} catch (Exception $e) {
    // Automatically falls back to OCR.space in OCRService
    $ocr = new OCRService();
    $result = $ocr->extractTextFromImage($path);
}
```

## API Rate Limits

- **Local Service (Tesseract.js):** No rate limits
- **Cloud Fallback (OCR.space):** 
  - Free tier: 25 requests/day
  - Paid tier (K81289438988957): Based on token limit (up to 4M tokens)

## File Structures

### Key Files
- `src/ocr-service.js` - Express.js OCR service
- `app/Helpers/NodeOCRClient.php` - PHP client
- `app/Helpers/OCRService.php` - OCR wrapper with fallback
- `app/Routes/AISystemRoutes.php` - API routes
- `.env` - Configuration

### Upload Directory
- `/storage/ocr-temp/` - Temporary files during processing (auto-cleaned, shared across deployments)

## Production Deployment

### Using PM2 (Node.js Process Manager)
```bash
# Install PM2
npm install -g pm2

# Start service
pm2 start src/ocr-service.js --name "ocr-service"

# Make it auto-restart on server reboot
pm2 startup
pm2 save

# View logs
pm2 logs ocr-service

# Restart
pm2 restart ocr-service
```

### Using systemd (Linux)
Create `/etc/systemd/system/ocr-service.service`:
```ini
[Unit]
Description=Tesseract.js OCR Service
After=network.target

[Service]
Type=simple
User=web
WorkingDirectory=/var/www/broxlab
ExecStart=/usr/bin/node src/ocr-service.js
Restart=always
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl daemon-reload
sudo systemctl enable ocr-service
sudo systemctl start ocr-service
sudo systemctl status ocr-service
```

## Testing

### cURL Test Script
```bash
#!/bin/bash

echo "Testing OCR Service Health..."
curl -s http://localhost:7020/ocr/health | jq .

echo "Testing Single Image OCR..."
curl -s -X POST \
  -F "image=@test-image.png" \
  -F "lang=eng" \
  http://localhost:7020/ocr/tesseract/image | jq .

echo "Testing Service Availability..."
curl -I http://localhost:7020/ocr/health
```

## Summary

| Feature | Tesseract.js | OCR.space |
|---------|-------------|-----------|
| **Availability** | Local (Node.js) | Cloud |
| **Cost** | Free | Free (25/day), Paid tier |
| **Speed** | Fast (after model load) | Variable (network dependent) |
| **Accuracy** | High (95%+ for clear text) | High |
| **No Internet** | ✓ Works offline | ✗ Requires connection |
| **Dependency** | Node.js installation | API key |
| **Multi-language** | ✓ 100+ languages | ✓ 100+ languages |
| **Web Hosting** | ✓ Works everywhere | ✓ Requires curl |

---

**Last Updated:** 2024-01-15
**Status:** Production Ready
