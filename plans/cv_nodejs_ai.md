# CV Builder Node.js Microservice

## Service Structure

### Location
`node_cv_service/` (new directory in project root)

### Dependencies
- `express` - Web framework
- `multer` - File upload handling
- `pdf-parse` - PDF text extraction
- `mammoth` - DOCX text extraction
- `axios` - HTTP client for AI API calls
- `dotenv` - Environment variables
- `cors` - CORS support
- `helmet` - Security headers
- `express-rate-limit` - Rate limiting

### API Endpoints

#### 1. Improve Text
**POST** `/ai/improve`

Request:
```json
{
  "text": "Led a team of developers",
  "type": "bullet" // or "paragraph"
}
```

Response:
```json
{
  "improved": "Led a team of 5 developers to deliver 3 major projects on time",
  "score": 85
}
```

#### 2. ATS Score
**POST** `/ai/ats-score`

Request:
```json
{
  "cv": {
    "summary": "...",
    "experience": [...],
    "education": [...],
    "skills": [...]
  }
}
```

Response:
```json
{
  "score": 75,
  "feedback": {
    "keywords": { "found": ["PHP", "MySQL"], "missing": ["AWS", "Docker"] },
    "readability": "Good",
    "sections": { "complete": true, "missing": [] }
  },
  "suggestions": [
    "Add more technical skills",
    "Quantify your achievements"
  ]
}
```

#### 3. Keyword Extraction
**POST** `/ai/keyword-extract`

Request:
```json
{
  "job_description": "We are looking for a senior PHP developer with experience in MySQL and AWS..."
}
```

Response:
```json
{
  "keywords": ["PHP", "MySQL", "AWS", "developer", "senior"],
  "importance": {
    "PHP": 10,
    "MySQL": 8,
    "AWS": 7
  }
}
```

#### 4. CV Import (Parse PDF/DOCX)
**POST** `/cv/import`

Request: `multipart/form-data`
- file: PDF or DOCX file

Response:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "summary": "Experienced developer...",
  "experience": [
    {
      "company": "Tech Corp",
      "position": "Developer",
      "start_date": "2020-01",
      "end_date": "2023-01",
      "description": "Developed web applications"
    }
  ],
  "education": [...],
  "skills": [...]
}
```

## Project Structure

```
node_cv_service/
├── src/
│   ├── index.js           # Entry point
│   ├── routes/
│   │   ├── ai.js          # AI endpoints
│   │   └── cv.js          # Import endpoint
│   ├── services/
│   │   ├── parser.js      # PDF/DOCX parsing
│   │   ├── ai_client.js   # AI API calls
│   │   └── ats.js         # ATS scoring logic
│   ├── middleware/
│   │   ├── rateLimiter.js # Rate limiting
│   │   └── validator.js   # Input validation
│   └── utils/
│       ├── logger.js      # Logging
│       └── fileHandler.js # File upload handling
├── uploads/               # Temporary file storage
├── package.json
└── .env                  # Environment variables (do not commit)
```

## Environment Variables

```
PORT=3001
AI_API_KEY=your_api_key_here
AI_API_URL=https://api.example.com/v1
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100
```

## Security

- Validate file types before processing
- Limit file size to 5MB
- Rate limit all endpoints
- Sanitize file names
- Use helmet for security headers
- CORS configuration for allowed origins

## Error Handling

- Return proper HTTP status codes (200, 400, 429, 500)
- Log errors with stack traces
- Return user-friendly error messages

## Integration with PHP

PHP will call the Node.js service via HTTP. Example:

```php
$ch = curl_init('http://localhost:3001/ai/improve');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $text]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);
```