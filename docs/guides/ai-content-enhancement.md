# AI Content Enhancement System

The web scraping system now includes comprehensive AI-powered content enhancement that automatically improves scraped data before publishing.

## Overview

The AI Content Enhancement system processes raw scraped content through multiple AI-driven improvements:

- **Content Cleaning**: Removes HTML artifacts, ads, and irrelevant content
- **Language Enhancement**: Improves grammar, readability, and flow
- **Title Optimization**: Creates SEO-friendly, engaging titles
- **Summary Generation**: Produces concise, compelling summaries
- **SEO Optimization**: Generates meta titles, descriptions, and keywords
- **Taxonomy Suggestion**: Recommends categories and tags
- **Content Analysis**: Estimates reading time and word count

## Architecture

### AiContentEnhancer Class

Located at `app/Modules/AutoContent/AiContentEnhancer.php`, this class orchestrates the entire enhancement process.

### Integration Points

- **Data Collection**: Raw content from web scraping
- **AI Processing**: Multiple AI calls for different enhancements
- **Database Updates**: Enhanced content stored back to database
- **Publishing**: Enhanced content published to main content tables

## Enhancement Pipeline

### 1. Content Enhancement

**Input**: Raw scraped HTML content
**Process**: AI removes artifacts, improves readability
**Output**: Clean, well-formatted content

### 2. Title Optimization

**Input**: Original title + content preview
**Process**: AI creates engaging, SEO-friendly titles
**Output**: Optimized title under 60 characters

### 3. Summary Generation

**Input**: Full content
**Process**: AI extracts key points into 2-3 sentences
**Output**: Compelling summary for meta description

### 4. SEO Optimization

**Input**: Title, summary, content sample
**Process**: AI analyzes for SEO opportunities
**Output**: SEO title, description, keywords, and score (0-100)

### 5. Taxonomy Suggestion

**Input**: Title + content preview
**Process**: AI suggests relevant categories and tags
**Output**: Category list and tag array

## Usage

### Automatic Processing

The system automatically processes content through the collection script:

```bash
php scripts/collect-and-publish-data.php
```

This runs:

1. Data collection from sources
2. AI enhancement of collected content
3. Publishing to main content tables

### Manual Processing

Process specific articles with AI enhancement:

```php
use App\Modules\AutoContent\AiContentEnhancer;

$enhancer = new AiContentEnhancer($mysqli);
$result = $enhancer->processBatch(5); // Process 5 articles

echo "Processed: {$result['processed']}, Failed: {$result['failed']}";
```

### Single Article Enhancement

Enhance individual articles:

```php
$article = [
    'id' => 123,
    'title' => 'Original Title',
    'content' => '<p>Raw HTML content...</p>'
];

$enhanced = $enhancer->enhanceArticle($article);
```

## AI Prompts

The system uses specialized prompts for different enhancement types:

### Content Enhancement

```
You are a professional content editor. Clean and enhance the following scraped content...
```

### Title Optimization

```
You are a SEO expert. Optimize this title for better engagement and SEO...
```

### SEO Optimization

```
Analyze this content and provide SEO optimization data...
```

### Taxonomy Suggestion

```
Analyze this content and suggest appropriate categories and tags...
```

## Configuration

### AI Provider Settings

- **Provider**: kilo (default)
- **Model**: auto
- **Temperature**: Varies by task (0.5-0.8)
- **Max Tokens**: 100-1500 depending on task

### Processing Limits

- **Batch Size**: 5 articles per run (configurable)
- **Content Length**: Max 2000 characters for enhancement
- **Timeout**: 30 seconds per AI call

## Database Schema

Enhanced articles include additional fields:

```sql
-- Enhanced content fields added to web_scraping_articles
ALTER TABLE web_scraping_articles ADD COLUMN (
    summary TEXT,
    excerpt VARCHAR(255),
    seo_title VARCHAR(255),
    seo_description TEXT,
    seo_keywords VARCHAR(255),
    seo_score INT,
    categories JSON,
    tags JSON,
    reading_time VARCHAR(50),
    word_count INT,
    enhanced_at DATETIME,
    enhancement_version VARCHAR(10)
);
```

## Monitoring & Analytics

### Processing Metrics

- **Success Rate**: Percentage of successfully enhanced articles
- **Average SEO Score**: Mean SEO score across processed content
- **Processing Time**: Time taken for enhancement
- **Error Rates**: AI call failures and recovery

### Content Quality Metrics

- **Readability Score**: Content clarity and flow
- **SEO Performance**: Title length, keyword density
- **Engagement Potential**: Title click-worthiness

## Error Handling

### AI Call Failures

- **Fallback Content**: Uses basic cleaning if AI fails
- **Retry Logic**: Automatic retries for transient failures
- **Graceful Degradation**: Continues processing other articles

### Content Issues

- **Empty Content**: Skips enhancement for empty articles
- **Encoding Issues**: Handles UTF-8 encoding problems
- **HTML Artifacts**: Robust HTML cleaning

## Benefits

### Content Quality

- **Professional Polish**: AI-enhanced readability and flow
- **SEO Optimization**: Better search engine visibility
- **Engagement**: More compelling titles and summaries

### Operational Efficiency

- **Automated Processing**: No manual content editing required
- **Scalable**: Handles large volumes of content
- **Consistent Quality**: Uniform enhancement across all content

### Business Impact

- **Higher Rankings**: Better SEO performance
- **Increased Traffic**: More engaging content attracts visitors
- **Time Savings**: Eliminates manual content optimization

## Future Enhancements

- **Multi-language Support**: Content enhancement in multiple languages
- **Custom Enhancement Rules**: Domain-specific enhancement logic
- **A/B Testing**: Compare enhancement effectiveness
- **User Feedback Integration**: Learn from content performance
- **Advanced Analytics**: Deep content quality metrics

## Troubleshooting

### Common Issues

1. **AI Call Timeouts**
   - Reduce batch size
   - Check AI provider status
   - Increase timeout limits

2. **Poor Enhancement Quality**
   - Review AI prompts
   - Adjust temperature settings
   - Provide more context in prompts

3. **Database Connection Issues**
   - Check MySQL connection
   - Verify table permissions
   - Monitor connection pool

### Debug Mode

Enable detailed logging:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Check logs in storage/logs/ for enhancement details
```

## Integration Examples

### WordPress Integration

```php
// Publish enhanced content to WordPress
$wordpress = new WordPressAPI();
$wordpress->createPost([
    'title' => $enhanced['title'],
    'content' => $enhanced['content'],
    'excerpt' => $enhanced['excerpt'],
    'categories' => $enhanced['categories'],
    'tags' => $enhanced['tags']
]);
```

### CMS Integration

```php
// Update existing CMS article
$cms->updateArticle($articleId, [
    'seo_title' => $enhanced['seo_title'],
    'meta_description' => $enhanced['seo_description'],
    'keywords' => $enhanced['seo_keywords']
]);
```

The AI Content Enhancement system transforms raw scraped data into publication-ready, optimized content that performs better in search engines and engages readers more effectively.
