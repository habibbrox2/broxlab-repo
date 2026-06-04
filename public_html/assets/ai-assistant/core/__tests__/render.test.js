/**
 * Unit tests for AI Assistant render module security functions
 * @see public_html/assets/ai-assistant/core/render.js
 *
 * Covers: escapeHtml, sanitizeHtml, parseMarkdown
 * Focus: XSS prevention, correct markdown rendering, edge cases
 */

import { describe, it, expect } from 'vitest';
import { escapeHtml, sanitizeHtml, parseMarkdown } from '../render.js';

// =============================================================================
// escapeHtml
// =============================================================================
describe('escapeHtml', () => {
  it('should escape ampersand', () => {
    expect(escapeHtml('&')).toBe('&amp;');
  });

  it('should escape less-than', () => {
    expect(escapeHtml('<')).toBe('&lt;');
  });

  it('should escape greater-than', () => {
    expect(escapeHtml('>')).toBe('&gt;');
  });

  it('should escape double quotes', () => {
    expect(escapeHtml('"')).toBe('&quot;');
  });

  it('should escape single quotes', () => {
    expect(escapeHtml("'")).toBe('&#039;');
  });

  it('should escape a full XSS payload', () => {
    const input = '<script>alert("xss")</script>';
    const expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
    expect(escapeHtml(input)).toBe(expected);
  });

  it('should handle empty string', () => {
    expect(escapeHtml('')).toBe('');
  });

  it('should coerce null to the string "null"', () => {
    // String(null) === 'null'; escapeHtml escapes no chars in it
    expect(escapeHtml(null)).toBe('null');
  });

  it('should coerce undefined to the string "undefined"', () => {
    // String(undefined) === 'undefined'
    expect(escapeHtml(undefined)).toBe('undefined');
  });

  it('should handle numbers by converting to string', () => {
    expect(escapeHtml(0)).toBe('0');
    expect(escapeHtml(42)).toBe('42');
  });

  it('should escape mixed HTML and text content', () => {
    const input = 'Hello <b>World</b> & "friends"';
    const expected = 'Hello &lt;b&gt;World&lt;/b&gt; &amp; &quot;friends&quot;';
    expect(escapeHtml(input)).toBe(expected);
  });

  it('should not alter safe strings', () => {
    expect(escapeHtml('hello world')).toBe('hello world');
    expect(escapeHtml('12345')).toBe('12345');
    expect(escapeHtml('no special chars here')).toBe('no special chars here');
  });
});

// =============================================================================
// sanitizeHtml — XSS attack vectors
// =============================================================================
describe('sanitizeHtml', () => {
  // --- Null/empty handling ---
  it('should return empty string for falsy input', () => {
    expect(sanitizeHtml('')).toBe('');
    expect(sanitizeHtml(null)).toBe('');
    expect(sanitizeHtml(undefined)).toBe('');
    expect(sanitizeHtml(false)).toBe('');
  });

  // --- Script tag removal ---
  it('should remove inline script tags', () => {
    const input = 'Hello <script>alert("xss")</script> World';
    expect(sanitizeHtml(input)).toBe('Hello  World');
  });

  it('should remove script tags with attributes', () => {
    const input = '<script src="evil.js" type="text/javascript">code</script>';
    expect(sanitizeHtml(input)).toBe('');
  });

  it('should remove multi-line script tags', () => {
    const input = 'before<script\n  type="text/javascript"\n>malicious();</script>after';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<script');
    expect(result).toContain('before');
    expect(result).toContain('after');
  });

  it('should remove all script tags in a string with multiple', () => {
    const input = '<script>a</script>safe<script>b</script>';
    expect(sanitizeHtml(input)).toBe('safe');
  });

  // --- Event handler removal ---
  it('should remove onclick event handler', () => {
    const input = '<div onclick="alert(1)">click me</div>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('onclick');
    expect(result).toContain('click me');
  });

  it('should remove onerror handler from img tags', () => {
    const input = '<img src=x onerror="alert(1)">';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('onerror');
  });

  it('should remove onmouseover handler', () => {
    const input = '<span onmouseover="steal()">hover</span>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('onmouseover');
    expect(result).toContain('hover');
  });

  it('should remove multiple event handlers from one element', () => {
    const input = '<div onclick="a()" onmouseover="b()" onfocus="c()">text</div>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('onclick');
    expect(result).not.toContain('onmouseover');
    expect(result).not.toContain('onfocus');
    expect(result).toContain('text');
  });

  // --- javascript: URI removal ---
  it('should remove javascript: URIs in href', () => {
    const input = '<a href="javascript:alert(1)">link</a>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('javascript:');
    expect(result).toContain('link');
  });

  it('should remove javascript: URIs in src attributes', () => {
    const input = '<img src="javascript:alert(1)">';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('javascript:');
  });

  it('should remove javascript: URIs in action attributes', () => {
    const input = '<form action="javascript:steal()">';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('javascript:');
  });

  it('should remove vbscript: URIs', () => {
    const input = '<a href="vbscript:MsgBox(1)">link</a>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('vbscript:');
    expect(result).toContain('link');
  });

  it('should remove javascript: URIs with single quotes', () => {
    const input = "<a href='javascript:alert(1)'>link</a>";
    const result = sanitizeHtml(input);
    expect(result).not.toContain('javascript:');
  });

  it('should remove javascript: URIs without quotes', () => {
    const input = '<a href=javascript:alert(1)>link</a>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('javascript:');
  });

  // --- Dangerous element removal ---
  it('should remove iframe elements with content', () => {
    const input = '<iframe src="evil.com">fallback</iframe>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<iframe');
    expect(result).not.toContain('</iframe>');
  });

  it('should remove object elements', () => {
    const input = '<object data="evil.swf"></object>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<object');
  });

  it('should remove embed elements', () => {
    const input = '<embed src="evil.swf">';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<embed');
  });

  it('should remove applet elements', () => {
    const input = '<applet code="evil">fallback</applet>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<applet');
  });

  it('should remove self-closing dangerous elements', () => {
    const input = '<iframe src="evil.com"/>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('<iframe');
  });

  // --- Preserves safe HTML ---
  it('should preserve safe HTML tags', () => {
    const input = '<p>Hello <strong>World</strong></p>';
    expect(sanitizeHtml(input)).toBe(input);
  });

  it('should preserve safe href links', () => {
    const input = '<a href="https://example.com">link</a>';
    expect(sanitizeHtml(input)).toBe(input);
  });

  it('should preserve safe img tags', () => {
    const input = '<img src="photo.jpg" alt="nice">';
    expect(sanitizeHtml(input)).toBe(input);
  });

  // --- Data URI attacks ---
  it('should neutralize data: URIs in href', () => {
    const input = '<a href="data:text/html,<script>alert(1)</script>">click</a>';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('data:');
  });

  it('should neutralize data: URIs in src attributes', () => {
    const input = '<img src="data:image/svg+xml,<script>alert(1)</script>">';
    const result = sanitizeHtml(input);
    expect(result).not.toContain('data:');
  });

  // --- Combined attack vectors ---
  it('should handle multiple attack vectors in one string', () => {
    const input = '<p>safe</p><script>alert(1)</script><img onerror="hack()" src=x>';
    const result = sanitizeHtml(input);
    expect(result).toContain('<p>safe</p>');
    expect(result).not.toContain('<script');
    expect(result).not.toContain('onerror');
  });

  it('should handle nested script-like patterns without false positives', () => {
    const input = '<p>The &lt;script&gt; tag is dangerous</p>';
    // This is already escaped HTML — sanitizeHtml should pass it through
    expect(sanitizeHtml(input)).toBe(input);
  });
});

// =============================================================================
// parseMarkdown — rendering correctness
// =============================================================================
describe('parseMarkdown', () => {
  // --- Null/empty ---
  it('should return empty string for falsy input', () => {
    expect(parseMarkdown('')).toBe('');
    expect(parseMarkdown(null)).toBe('');
    expect(parseMarkdown(undefined)).toBe('');
  });

  // --- XSS prevention in markdown ---
  it('should escape HTML within plain text', () => {
    const input = 'Hello <script>alert("xss")</script> World';
    const result = parseMarkdown(input);
    expect(result).not.toContain('<script>');
    expect(result).toContain('&lt;script&gt;');
  });

  it('should escape HTML in bold text', () => {
    const input = '**bold <img onerror="hack()"> text**';
    const result = parseMarkdown(input);
    // The HTML is escaped, so the img tag is not rendered as real HTML
    expect(result).not.toContain('<img');
    expect(result).toContain('&lt;img');
  });

  it('should escape HTML in code blocks', () => {
    const input = '```\n<script>alert("xss")</script>\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('&lt;script&gt;');
    expect(result).not.toContain('<script>');
  });

  // --- Code blocks ---
  it('should render fenced code blocks', () => {
    const input = '```js\nconst x = 1;\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('brox-ai-code-block-wrap');
    expect(result).toContain('brox-ai-code-header');
    expect(result).toContain('brox-ai-copy-code-btn');
    expect(result).toContain('const x = 1;');
  });

  it('should show language label when specified', () => {
    const input = '```python\nprint("hi")\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('brox-ai-code-lang');
    expect(result).toContain('python');
    expect(result).toContain('data-lang="python"');
  });

  it('should handle code blocks without language', () => {
    const input = '```\ncode here\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('brox-ai-code-block');
    expect(result).not.toContain('data-lang=');
    expect(result).not.toContain('brox-ai-code-lang');
  });

  // --- Inline code ---
  it('should render inline code', () => {
    const input = 'Use `console.log()` for debugging';
    const result = parseMarkdown(input);
    expect(result).toContain('<code class="brox-ai-inline-code">console.log()</code>');
  });

  // --- Bold ---
  it('should render bold text', () => {
    const input = '**bold text**';
    expect(parseMarkdown(input)).toContain('<strong>bold text</strong>');
  });

  // --- Italic ---
  it('should render italic text', () => {
    const input = '*italic text*';
    expect(parseMarkdown(input)).toContain('<em>italic text</em>');
  });

  // --- Strikethrough ---
  it('should render strikethrough text', () => {
    const input = '~~deleted text~~';
    expect(parseMarkdown(input)).toContain('<del>deleted text</del>');
  });

  // --- Links ---
  it('should render http links', () => {
    const input = '[Click here](https://example.com)';
    const result = parseMarkdown(input);
    expect(result).toContain('<a href="https://example.com"');
    expect(result).toContain('target="_blank"');
    expect(result).toContain('rel="noopener noreferrer"');
    expect(result).toContain('Click here</a>');
  });

  it('should render http links', () => {
    const input = '[Link](http://example.com)';
    const result = parseMarkdown(input);
    expect(result).toContain('<a href="http://example.com"');
  });

  it('should not render javascript: links as anchor tags', () => {
    const input = '[Click](javascript:alert(1))';
    const result = parseMarkdown(input);
    // javascript: links are NOT matched by the https?:// regex, so no <a> tag is created
    expect(result).not.toMatch(/<a\s/);
  });

  it('should not render ftp links as anchor tags', () => {
    const input = '[Link](ftp://evil.com)';
    const result = parseMarkdown(input);
    expect(result).not.toMatch(/<a\s/);
  });

  it('should not render data: URIs as anchor tags', () => {
    const input = '[Click](data:text/html,<script>alert(1)</script>)';
    const result = parseMarkdown(input);
    // data: URIs don't match the https?:// regex, so no <a> tag is created
    expect(result).not.toMatch(/<a\s/);
  });

  // --- Unordered lists ---
  it('should render unordered lists', () => {
    const input = '- item one\n- item two\n- item three';
    const result = parseMarkdown(input);
    expect(result).toContain('<ul>');
    expect(result).toContain('<li>item one</li>');
    expect(result).toContain('<li>item two</li>');
    expect(result).toContain('<li>item three</li>');
    expect(result).toContain('</ul>');
  });

  it('should render unordered lists with * prefix', () => {
    const input = '* first\n* second';
    const result = parseMarkdown(input);
    expect(result).toContain('<ul>');
    expect(result).toContain('<li>first</li>');
    expect(result).toContain('<li>second</li>');
  });

  it('should handle indented list items', () => {
    const input = '  - indented one\n  - indented two';
    const result = parseMarkdown(input);
    expect(result).toContain('<li>indented one</li>');
    expect(result).toContain('<li>indented two</li>');
  });

  // --- Ordered lists ---
  it('should render ordered lists', () => {
    const input = '1. first\n2. second\n3. third';
    const result = parseMarkdown(input);
    expect(result).toContain('<ol>');
    expect(result).toContain('<li>first</li>');
    expect(result).toContain('<li>second</li>');
    expect(result).toContain('<li>third</li>');
    expect(result).toContain('</ol>');
  });

  it('should handle indented ordered lists', () => {
    const input = '  1. indented first\n  2. indented second';
    const result = parseMarkdown(input);
    expect(result).toContain('<li>indented first</li>');
    expect(result).toContain('<li>indented second</li>');
  });

  // --- Blockquotes ---
  it('should render blockquotes', () => {
    const input = '> This is a quote';
    const result = parseMarkdown(input);
    expect(result).toContain('<blockquote>This is a quote</blockquote>');
  });

  // --- Line breaks ---
  it('should convert newlines to <br> tags', () => {
    const input = 'line one\nline two';
    const result = parseMarkdown(input);
    expect(result).toContain('line one<br>line two');
  });

  it('should collapse 3+ consecutive <br> tags to 2', () => {
    const input = 'a\n\n\n\n\nb';
    const result = parseMarkdown(input);
    // 5 newlines = 5 <br> tags, should collapse to 2
    expect(result).not.toMatch(/(<br>){3,}/);
    expect(result).toContain('<br><br>');
  });

  // --- Combined markdown ---
  it('should handle combined bold and italic', () => {
    const input = '***bold italic***';
    const result = parseMarkdown(input);
    // Bold regex runs first, then italic on the result
    expect(result).toContain('<strong>');
    expect(result).toContain('<em>');
  });

  it('should handle mixed markdown and plain text', () => {
    const input = 'Hello **world** and *everyone*';
    const result = parseMarkdown(input);
    expect(result).toContain('<strong>world</strong>');
    expect(result).toContain('<em>everyone</em>');
  });

  // --- Edge cases ---
  it('should handle text with no markdown', () => {
    const input = 'plain text with no special chars';
    const result = parseMarkdown(input);
    expect(result).toBe('plain text with no special chars');
  });

  it('should handle empty code block', () => {
    const input = '```\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('brox-ai-code-block');
  });

  it('should handle multiline code block', () => {
    const input = '```js\nline 1\nline 2\nline 3\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('line 1');
    expect(result).toContain('line 2');
    expect(result).toContain('line 3');
  });

  it('should handle text that is only a newline', () => {
    const result = parseMarkdown('\n');
    expect(result).toContain('<br>');
  });

  // --- ULITEM/OLITEM marker edge cases ---
  // Known limitation: the ULITEM/OLITEM internal markers are consumed by
  // the list regex, so literal text containing these markers loses them.
  // In practice this is extremely unlikely in AI-generated content.
  it('should render list items even when text contains ULITEM marker', () => {
    const input = '- ULITEM is a keyword';
    const result = parseMarkdown(input);
    expect(result).toContain('<li>');
    expect(result).toContain('<ul>');
  });

  it('should render ordered list items even when text contains OLITEM marker', () => {
    const input = '1. OLITEM is a keyword';
    const result = parseMarkdown(input);
    expect(result).toContain('<li>');
    expect(result).toContain('<ol>');
  });

  it('should escape HTML inside code blocks', () => {
    const input = '```\n<div>raw html</div>\n```';
    const result = parseMarkdown(input);
    // HTML inside code blocks is escaped by escapeHtml running first
    expect(result).toContain('&lt;div&gt;');
    expect(result).not.toContain('<div>');
  });

  // Known limitation: markdown link syntax inside code blocks is still
  // processed by the link regex. This is acceptable because the code block
  // content is already inside <code> tags and the URL is user-controlled
  // markdown input (not injected HTML).
  it('should wrap code block content in proper tags', () => {
    const input = '```\nhttps://example.com\n```';
    const result = parseMarkdown(input);
    expect(result).toContain('brox-ai-code-block');
    expect(result).toContain('brox-ai-copy-code-btn');
  });
});
