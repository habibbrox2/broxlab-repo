# SEO Best Practices Guide for BroxLab

## Table of Contents
1. [Image Optimization](#image-optimization)
2. [Meta Descriptions](#meta-descriptions)
3. [Title Tags](#title-tags)
4. [Content Structure](#content-structure)
5. [Schema Markup](#schema-markup)
6. [Link Building](#link-building)
7. [Technical SEO](#technical-seo)

---

## Image Optimization

### Alt Text Guidelines
Alt text (alternative text) helps search engines understand images and improves accessibility. Follow these guidelines:

#### For Product/Device Images
- **Format**: `[Brand] [Model] - [Key Feature(s)]`
- **Example**: `iPhone 15 Pro Max - Premium smartphone with titanium design`
- **Length**: 100-125 characters max
- **Avoid**: Keyword stuffing, generic "image" or "photo"

#### For Feature/Review Images
- **Format**: `[Device Name] [Feature] - [What's shown]`
- **Example**: `Samsung Galaxy S24 camera system - showing 50MP main sensor`
- **Length**: Keep under 100 characters
- **Include**: What users see and why it matters

#### For Comparison/Chart Images
- **Format**: `[Chart Type] comparing [Products] - [Key Data]`
- **Example**: `Performance benchmark chart comparing iPhone 15 vs Pixel 8 processors`

#### For UI/Feature Screenshots
- **Format**: `[App/Device] [Feature Name] - [What's visible]`
- **Example**: `Gmail interface on Android showing conversation threads`

### Implementation in Twig Templates

```twig
{# Good: Descriptive alt text #}
<img src="/assets/images/iphone-15-pro.jpg" 
     alt="iPhone 15 Pro - Premium smartphone with A17 Pro chip and titanium design"
     loading="lazy" 
     decoding="async">

{# Bad: Generic or keyword-stuffed #}
<img src="/assets/images/iphone-15-pro.jpg" 
     alt="Phone"
     loading="lazy">

{# For featured images in articles #}
<img src="{{ post.featured_image }}" 
     alt="{{ post.title }} - Featured image"
     loading="lazy"
     decoding="async">
```

### Image File Names
- Use descriptive, hyphenated names
- **Good**: `iphone-15-pro-camera-comparison.jpg`
- **Bad**: `image123.jpg` or `photo.jpg`

---

## Meta Descriptions

### What Makes a Good Meta Description
1. **Length**: 150-160 characters (optimal for desktop)
2. **Unique**: Different for every page
3. **Action-Oriented**: Include verbs like "Learn," "Discover," "Explore"
4. **Include Primary Keyword**: Once naturally
5. **Compelling**: Enticing reason to click

### Templates by Page Type

#### Article/Blog Post
```
Format: "[Keyword phrase] - [unique angle or main takeaway]. [Quick fact or benefit]"

Example: "iPhone 15 Pro review - flagship Apple phone with A17 Pro chip. 
Compare camera, performance, battery life with detailed specs. Updated 2024."
```

#### Product/Device Page
```
Format: "[Product name] - [Price/Release]. [Top 2-3 features]. [USP]. Compare [X] vs [Y]."

Example: "Samsung Galaxy S24 Ultra smartphone from $1,299. 200MP camera, 
AI features, 3000Hz refresh. Compare specs and price with iPhone 15 Pro."
```

#### Category/Archive Page
```
Format: "Browse [category name] articles. [Number if applicable] [content type] about [topic]. 
Find reviews, guides, and latest news about [topic]."

Example: "Browse all smartphone reviews. 150+ detailed reviews, comparisons, 
and guides. Find the best mobile devices with expert recommendations."
```

#### FAQ Page
```
Format: "Answers to frequently asked questions about [topic]. 
Learn [specific question] and [another key question]. Free resource."

Example: "FAQ about smartphones and mobile devices. Learn how to choose the right phone, 
improve battery life, and understand 5G technology."
```

### Twig Implementation Example
```twig
{% block meta_description %}
  {% if post.excerpt %}
    {{ post.excerpt|slice(0, 150) }}
  {% else %}
    {{ post.title }} - {{ post.author }}. 
    {{ post.content|striptags|slice(0, 130) }}. 
    Detailed review and analysis on {{ app_settings.site_name }}.
  {% endif %}
{% endblock %}
```

---

## Title Tags

### Best Practices
1. **Length**: 50-60 characters (optimal)
2. **Format**: `[Primary Keyword] - [Secondary Keyword/Benefit] | [Brand Name]`
3. **Unique**: Each page must have unique title
4. **Front-Load Keywords**: Put most important keywords first

### Templates by Page Type

#### Article Title
```
Format: "[Article Title] - [Keyword] | [Site Name]"

Example: "iPhone 15 Pro Review - Camera Performance Comparison | BroxLab"
```

#### Product/Device Page
```
Format: "[Brand] [Model] - [Price] | [Site Name]"

Example: "Samsung Galaxy S24 Ultra - $1,299 | BroxLab"
```

#### Category Page
```
Format: "[Category] - Browse [Type] | [Site Name]"

Example: "Smartphones - Browse Latest Phones | BroxLab"
```

---

## Content Structure

### Heading Hierarchy (H1-H6)

#### Rules
- **One H1 per page**: Should match or closely relate to page title
- **Logical flow**: H1 → H2 → H3 (don't skip levels)
- **Keywords**: Include naturally in headings
- **Readability**: Make headings compelling, not just keyword-focused

#### Example Structure
```html
<h1>iPhone 15 Pro Review: Complete Camera and Performance Analysis</h1>

  <h2>Design and Build Quality</h2>
  <h3>Titanium vs Previous Aluminum</h3>
  <h3>Weight and Durability</h3>

  <h2>Camera System</h2>
  <h3>Main Camera Improvements</h3>
  <h3>Ultra-Wide and Telephoto Lenses</h3>

  <h2>Performance and Processor</h2>
  <h3>A17 Pro Benchmarks</h3>
  <h3>Real-World Performance</h3>

  <h2>Battery Life and Charging</h2>
  <h2>Verdict and Rating</h2>
```

### Content Length Guidelines
- **Blog Posts**: 2,000+ words for competitive topics
- **Product Reviews**: 1,500-2,500 words with specs
- **Comparison Articles**: 2,500+ words
- **How-To Guides**: 1,500+ words
- **Landing Pages**: 1,000+ words

---

## Schema Markup

### Currently Implemented

1. **BreadcrumbList** - Navigation hierarchy
2. **BlogPosting** - Article/blog content
3. **Organization** - Site-wide organization info
4. **LocalBusiness** - Business details
5. **WebSite** - General website info
6. **FAQPage** - FAQ rich snippets

### How to Test
1. Go to [Google Rich Results Test](https://search.google.com/test/rich-results)
2. Enter your URL
3. Check for any warnings or errors
4. Fix any issues found

### Next Schema Types to Implement

#### Product Schema
Used for device/mobile phone pages
```json
{
  "@type": "Product",
  "name": "iPhone 15 Pro",
  "brand": "Apple",
  "price": "999",
  "priceCurrency": "USD",
  "rating": "4.8",
  "ratingCount": "1200",
  "image": "url"
}
```

#### Review Schema
Used for review pages
```json
{
  "@type": "Review",
  "reviewRating": "4.8",
  "author": "John Smith",
  "reviewBody": "Excellent phone with great camera...",
  "datePublished": "2024-04-28"
}
```

---

## Link Building & Internal Linking

### Internal Linking Best Practices
1. **Contextual Links**: Links within content, not just navigation
2. **Anchor Text**: Descriptive, keyword-relevant (avoid "click here")
3. **Linking Structure**: Link from high-authority pages to important pages
4. **Reasonable Density**: 3-5 internal links per 1000 words

#### Example Implementation
```twig
{# Good: Descriptive anchor text with context #}
<p>For a complete comparison, check our 
  <a href="/comparison/iphone-15-vs-pixel-8">iPhone 15 vs Pixel 8 comparison guide</a>.
</p>

{# Bad: Generic anchor text #}
<p>For more info, <a href="/page">click here</a>.</p>
```

### Building Topical Clusters
Create content clusters around main topics:
- **Pillar**: "Best Smartphones 2024"
- **Cluster**: "iPhone 15 Pro Review," "Galaxy S24 Review," "Pixel 8 Pro Review"
- **Link**: Link cluster articles to pillar and each other

---

## Technical SEO

### Robots.txt
✓ Already configured - Located at `/robots.txt`
- Allows all crawlers except from private areas
- Specifies sitemap location

### XML Sitemap
✓ Already implemented - Located at `/sitemap.xml`
- Automatically generated
- Submitted to Google Search Console

### Mobile Optimization
✓ Responsive design implemented
- Mobile-first approach
- Touch-friendly buttons (48px minimum)
- Readable font sizes (16px minimum)

### Page Speed
Monitor and improve:
1. **Check Core Web Vitals**: [PageSpeed Insights](https://pagespeed.web.dev/)
2. **Optimize Images**: Use modern formats (WebP), responsive images
3. **Lazy Loading**: Already implemented with `loading="lazy"`
4. **Caching**: Browser caching configured
5. **Compression**: Enable gzip compression

### SSL/HTTPS
✓ Implemented - All traffic over HTTPS

### Canonical URLs
✓ Implemented - Set on every page to prevent duplicate content

---

## Monitoring & Analytics

### Tools to Setup
1. **Google Search Console** - Monitor search performance
2. **Google Analytics 4** - Track user behavior and traffic
3. **Bing Webmaster Tools** - Bing search performance
4. **Search.Google.com** - Run Rich Results Test monthly

### KPIs to Track
- Organic traffic growth
- Click-through rate (CTR) from SERPs
- Average position for target keywords
- Core Web Vitals scores
- Internal link click behavior
- Content performance by page

### Monthly Review Checklist
- [ ] Check Search Console for indexing issues
- [ ] Run Rich Results Test on updated pages
- [ ] Review top-performing keywords
- [ ] Check Core Web Vitals
- [ ] Analyze user behavior via Analytics
- [ ] Identify underperforming content
- [ ] Update old or outdated content

---

## Content Updates (E-E-A-T)

### Google's E-E-A-T Requirements
- **Expertise**: Show subject matter knowledge
- **Experience**: Include personal experience where relevant
- **Authority**: Include author bio, credentials
- **Trustworthiness**: Cite sources, provide accurate information

### How to Implement
1. **Author Information**: Include on article pages
2. **Publication Date**: Always display (not just creation, update also)
3. **Citations**: Link to authoritative sources
4. **Regular Updates**: Update publish dates when content is revised
5. **Expertise Signals**: Highlight author credentials, expertise

---

## Common SEO Mistakes to Avoid

1. ❌ **Duplicate Content**: Use canonical tags (already done)
2. ❌ **Thin Content**: Maintain 1500+ words for important pages
3. ❌ **Slow Load Times**: Test with PageSpeed Insights
4. ❌ **Mobile Issues**: Always test on mobile devices
5. ❌ **Poor Meta Descriptions**: Make them unique and compelling
6. ❌ **Keyword Stuffing**: Write naturally for humans first
7. ❌ **Broken Links**: Test regularly and fix
8. ❌ **Missing Alt Text**: Every image needs descriptive alt text
9. ❌ **Wrong Schema Type**: Use appropriate schema.org type
10. ❌ **Ignoring Analytics**: Monitor performance and adjust

---

## Quick Reference Checklist

- [ ] Meta description is 150-160 characters
- [ ] Title tag is 50-60 characters with primary keyword first
- [ ] One H1 per page matching main topic
- [ ] All images have descriptive alt text
- [ ] Internal links use descriptive anchor text
- [ ] Content is 1500+ words for important pages
- [ ] Schema markup implemented correctly
- [ ] No broken links (test monthly)
- [ ] Mobile-friendly design tested
- [ ] Page loads in under 3 seconds
- [ ] Canonical URL set correctly
- [ ] No duplicate content across pages

---

**Last Updated**: April 28, 2026
**Version**: 1.0
**Maintained By**: SEO Team
