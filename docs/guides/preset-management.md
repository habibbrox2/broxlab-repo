# Web Scraping Preset Management

The enhanced web scraping system now includes comprehensive preset management with AI-powered selector detection capabilities.

## Features

### 1. **Preset List Management**

- **View All Presets**: Browse presets organized by category
- **Create New Presets**: Build custom presets from scratch
- **Edit Existing Presets**: Modify preset configurations
- **Delete Presets**: Remove unused presets
- **Apply Presets**: Quickly create scraper sources from presets

### 2. **AI-Powered Selector Detection**

- **Automatic Analysis**: AI analyzes website structure and detects optimal CSS selectors
- **Content Type Detection**: Automatically identifies content types (articles, blog posts, products, etc.)
- **Selector Recommendations**: Provides multiple selector options with confidence scores
- **One-Click Application**: Apply detected selectors directly to preset forms

### 3. **Preset Structure**

Each preset contains:

- **Basic Info**: Name, description, category, content type
- **Selectors**: CSS/XPath selectors for content extraction
- **Advanced Config**: Timeout, user agent, retry settings
- **Example URLs**: Sample URLs where the preset works

## Usage Guide

### Accessing Preset Management

Navigate to `/admin/scraper/presets` in the admin panel under "Web Scraping Menu" > "Presets".

### Creating a New Preset

1. **Manual Creation**:
   - Click "Create Preset" button
   - Fill in basic information (name, description, category, content type)
   - Define CSS selectors for content extraction
   - Configure advanced settings (timeouts, user agents)
   - Add example URLs

2. **AI-Assisted Creation**:
   - Click "Create Preset" button
   - Click "AI Selector Detection" button
   - Enter a website URL
   - Select expected content type
   - AI analyzes the site and suggests selectors
   - Apply the detected selectors to the form

### Editing Presets

- Click the "Edit" button on any preset card
- Modify any field as needed
- Use AI detection to update selectors for changed websites

### Applying Presets

- Click "Apply Preset" on any preset card
- Enter a custom name (optional)
- System creates a new scraper source with the preset configuration
- Redirects to source edit page for fine-tuning

### AI Selector Detection Process

1. **URL Analysis**: System fetches and analyzes the provided URL
2. **Structure Detection**: AI identifies page structure and content patterns
3. **Selector Generation**: Creates optimized CSS selectors for various elements
4. **Confidence Scoring**: Rates selector reliability (0-1 confidence score)
5. **Recommendations**: Provides alternative selector options

## API Endpoints

### AI Selector Detection

```http
POST /api/v1/scraper/presets/ai-detect
Content-Type: application/json

{
  "url": "https://example.com",
  "content_type": "articles",
  "csrf_token": "..."
}
```

**Response:**

```json
{
  "success": true,
  "selectors": {
    "title": "h1.article-title",
    "content": ".article-content",
    "date": ".publish-date",
    "author": ".author-name"
  },
  "confidence": 0.87,
  "content_type": "articles",
  "recommendations": [
    "Consider using .post-content for broader content matching",
    "Date selector has medium confidence, consider fallbacks"
  ]
}
```

### Preset CRUD Operations

- **Create/Update**: `POST /admin/scraper/presets/save`
- **Delete**: `DELETE /admin/scraper/presets/{key}`
- **List**: `GET /admin/scraper/presets/list`

## Integration with Existing System

### Automatic Preset Detection

When creating scraper sources, the system automatically suggests matching presets based on:

- URL pattern matching
- Content type similarity
- Category relevance

### Preset Inheritance

Sources created from presets inherit all configuration but can be customized independently.

### Preset Updates

Changes to presets don't affect existing sources (snapshot approach), ensuring stability.

## Best Practices

### Preset Creation

1. **Use Descriptive Names**: Clear, descriptive preset names
2. **Test Selectors**: Verify selectors work across multiple pages
3. **Include Examples**: Provide 3-5 example URLs
4. **Document Limitations**: Note any site-specific restrictions

### AI Detection Usage

1. **Choose Representative URLs**: Use typical content pages, not homepages
2. **Verify Results**: Always test AI-detected selectors
3. **Combine Approaches**: Use AI detection as starting point, then refine manually
4. **Update Regularly**: Re-run AI detection when sites change

### Preset Maintenance

1. **Monitor Effectiveness**: Track success rates of applied presets
2. **Update Selectors**: Refresh selectors when sites change layout
3. **Categorize Properly**: Use consistent category naming
4. **Document Changes**: Keep change history for troubleshooting

## Troubleshooting

### Common Issues

1. **AI Detection Fails**
   - Ensure URL is accessible and returns HTML
   - Try different pages from the same site
   - Check for anti-bot measures (Cloudflare, etc.)

2. **Selectors Not Working**
   - Website layout may have changed
   - Use browser developer tools to inspect current selectors
   - Re-run AI detection or update manually

3. **Preset Application Errors**
   - Check for duplicate source names
   - Verify database connectivity
   - Ensure proper permissions

### Debug Mode

Enable detailed logging by checking application logs for preset-related operations.

## Future Enhancements

- **Bulk Preset Operations**: Import/export presets
- **Preset Versioning**: Track changes over time
- **Community Presets**: Shared preset repository
- **Advanced AI Features**: Machine learning-based selector optimization
- **Performance Analytics**: Track preset effectiveness metrics
