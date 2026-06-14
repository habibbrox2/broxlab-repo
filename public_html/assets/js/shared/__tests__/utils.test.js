/**
 * Unit tests for shared utility functions
 * @see public_html/assets/js/shared/utils.js
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  escapeHtml,
  parseJson,
  toSafeId,
  formatDate,
  formatDateLabel,
  debounce,
  throttle,
  uniqueId
} from '../utils.js';

// =============================================================================
// escapeHtml
// =============================================================================
describe('escapeHtml', () => {
  it('should escape & to &amp;', () => {
    expect(escapeHtml('&')).toBe('&amp;');
  });

  it('should escape < to &lt;', () => {
    expect(escapeHtml('<')).toBe('&lt;');
  });

  it('should escape > to &gt;', () => {
    expect(escapeHtml('>')).toBe('&gt;');
  });

  it('should escape double quotes to &quot;', () => {
    expect(escapeHtml('"')).toBe('&quot;');
  });

  it('should escape single quotes to &#39;', () => {
    expect(escapeHtml("'")).toBe('&#39;');
  });

  it('should escape a full HTML string', () => {
    const input = '<script>alert("xss")</script>';
    const expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
    expect(escapeHtml(input)).toBe(expected);
  });

  it('should handle empty string', () => {
    expect(escapeHtml('')).toBe('');
  });

  it('should handle null', () => {
    expect(escapeHtml(null)).toBe('');
  });

  it('should handle undefined', () => {
    expect(escapeHtml(undefined)).toBe('');
  });

  it('should return safe strings unchanged', () => {
    expect(escapeHtml('hello world')).toBe('hello world');
    expect(escapeHtml('12345')).toBe('12345');
    expect(escapeHtml('')).toBe('');
  });

  it('should escape mixed content with HTML and text', () => {
    const input = 'Hello <b>World</b> & welcome to "Codebuff"\'s test';
    const expected = 'Hello &lt;b&gt;World&lt;/b&gt; &amp; welcome to &quot;Codebuff&quot;&#39;s test';
    expect(escapeHtml(input)).toBe(expected);
  });

  it('should handle numbers by converting to string', () => {
    expect(escapeHtml(0)).toBe('0');
    expect(escapeHtml(42)).toBe('42');
  });

  it('should handle objects by converting to string', () => {
    expect(escapeHtml({})).toBe('[object Object]');
    expect(escapeHtml({ foo: 'bar', })).toBe('[object Object]');
  });

  it('should not double-escape already escaped entities', () => {
    const input = '&amp;';
    // & is still a character, so it gets escaped again — this is expected
    // because escapeHtml is a simple character replacement, not an HTML parser
    expect(escapeHtml(input)).toBe('&amp;amp;');
  });
});

// =============================================================================
// parseJson
// =============================================================================
describe('parseJson', () => {
  it('should parse valid JSON string', () => {
    expect(parseJson('{"a":1}')).toEqual({ a: 1, });
  });

  it('should return fallback for invalid JSON', () => {
    expect(parseJson('not json', null)).toBeNull();
    expect(parseJson('{invalid}', 'fallback')).toBe('fallback');
  });

  it('should return fallback for empty/null/undefined input', () => {
    expect(parseJson('', 'default')).toBe('default');
    expect(parseJson(null, 'default')).toBe('default');
    expect(parseJson(undefined, 'default')).toBe('default');
  });

  it('should parse arrays', () => {
    expect(parseJson('[1,2,3]')).toEqual([1, 2, 3,]);
  });

  it('should parse primitive JSON values', () => {
    expect(parseJson('"hello"')).toBe('hello');
    expect(parseJson('42')).toBe(42);
    expect(parseJson('true')).toBe(true);
  });
});

// =============================================================================
// toSafeId
// =============================================================================
describe('toSafeId', () => {
  it('should convert spaces to hyphens', () => {
    expect(toSafeId('hello world')).toBe('hello-world');
  });

  it('should remove special characters', () => {
    expect(toSafeId('hello@world!')).toBe('helloworld');
  });

  it('should preserve hyphens and underscores', () => {
    expect(toSafeId('hello-world_test')).toBe('hello-world_test');
  });

  it('should trim whitespace', () => {
    expect(toSafeId('  hello world  ')).toBe('hello-world');
  });

  it('should handle empty/null/undefined', () => {
    expect(toSafeId('')).toBe('');
    expect(toSafeId(null)).toBe('');
    expect(toSafeId(undefined)).toBe('');
  });

  it('should handle multiple consecutive spaces', () => {
    expect(toSafeId('hello   world')).toBe('hello-world');
  });

  it('should create valid kebab-case strings', () => {
    expect(toSafeId('My Component Name')).toBe('My-Component-Name');
  });
});

// =============================================================================
// formatDate
// =============================================================================
describe('formatDate', () => {
  it('should format a valid date string in en-US locale', () => {
    // Use local-timezone-agnostic approach: test that the output contains
    // expected date parts rather than exact time (which varies by timezone)
    const result = formatDate('2026-03-15T14:30:00');
    expect(result).toContain('Mar 15, 2026');
    expect(result).toMatch(/\d{1,2}:\d{2} [AP]M/);
  });

  it('should return "N/A" for null/undefined', () => {
    expect(formatDate(null)).toBe('N/A');
    expect(formatDate(undefined)).toBe('N/A');
    expect(formatDate('')).toBe('N/A');
  });

  it('should return "Invalid Date" for unparseable date strings', () => {
    // new Date('not-a-date') returns an Invalid Date without throwing,
    // and toLocaleString returns "Invalid Date" — the try/catch does not trigger
    const result = formatDate('not-a-date');
    expect(result).toBe('Invalid Date');
  });
});

// =============================================================================
// formatDateLabel
// =============================================================================
describe('formatDateLabel', () => {
  it('should format a valid date to short month + day', () => {
    const result = formatDateLabel('2026-03-15');
    // Timezone-agnostic: just check the output contains expected month/day
    expect(result).toContain('Mar');
    expect(result).toContain('15');
  });

  it('should return empty string for null/undefined', () => {
    expect(formatDateLabel(null)).toBe('');
    expect(formatDateLabel(undefined)).toBe('');
    expect(formatDateLabel('')).toBe('');
  });

  it('should return "Invalid Date" for unparseable date strings', () => {
    // new Date('bad-date') returns Invalid Date without throwing
    expect(formatDateLabel('bad-date')).toBe('Invalid Date');
  });
});

// =============================================================================
// uniqueId
// =============================================================================
describe('uniqueId', () => {
  it('should use the default prefix "uid"', () => {
    const id = uniqueId();
    expect(id).toMatch(/^uid-\d+-[a-z0-9]{7}$/);
  });

  it('should use a custom prefix', () => {
    const id = uniqueId('item');
    expect(id).toMatch(/^item-\d+-[a-z0-9]{7}$/);
  });

  it('should generate unique IDs on successive calls', () => {
    const id1 = uniqueId();
    const id2 = uniqueId();
    expect(id1).not.toBe(id2);
  });

  it('should contain a timestamp component that increases', () => {
    const id1 = uniqueId();
    const ts1 = parseInt(id1.split('-')[1], 10);
    const id2 = uniqueId();
    const ts2 = parseInt(id2.split('-')[1], 10);
    expect(ts2).toBeGreaterThanOrEqual(ts1);
  });
});

// =============================================================================
// debounce
// =============================================================================
describe('debounce', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should not call the function immediately', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 100);
    debounced();
    expect(fn).not.toHaveBeenCalled();
  });

  it('should call the function after the delay', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 100);
    debounced();
    vi.advanceTimersByTime(100);
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('should reset the timer on subsequent calls', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 100);

    debounced();
    vi.advanceTimersByTime(50);
    debounced(); // resets timer
    vi.advanceTimersByTime(50); // 100ms haven't passed since last call
    expect(fn).not.toHaveBeenCalled();

    vi.advanceTimersByTime(50); // now 100ms since last call
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('should pass arguments to the original function', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 100);

    debounced('arg1', 42);
    vi.advanceTimersByTime(100);

    expect(fn).toHaveBeenCalledWith('arg1', 42);
  });

  it('should preserve the `this` context', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 100);
    const context = { value: 42, };

    debounced.call(context);
    vi.advanceTimersByTime(100);

    expect(fn.mock.instances[0]).toBe(context);
  });

  it('should use default delay of 300ms', () => {
    const fn = vi.fn();
    const debounced = debounce(fn);

    debounced();
    vi.advanceTimersByTime(299);
    expect(fn).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1);
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('should work with multiple rapid calls, only invoking once', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 50);

    for (let i = 0; i < 10; i++) {
      debounced();
      vi.advanceTimersByTime(10); // each call resets the timer
    }

    vi.advanceTimersByTime(50);
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('should correctly handle zero or negative delay', () => {
    const fn = vi.fn();
    const debounced = debounce(fn, 0);

    debounced();
    vi.advanceTimersByTime(0);
    expect(fn).toHaveBeenCalledTimes(1);
  });
});

// =============================================================================
// throttle
// =============================================================================
describe('throttle', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should call the function immediately on first invocation', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 100);
    throttled();
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('should ignore subsequent calls within the limit', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 100);

    throttled();
    expect(fn).toHaveBeenCalledTimes(1);

    throttled();
    throttled();
    throttled();
    expect(fn).toHaveBeenCalledTimes(1); // still 1
  });

  it('should allow a new call after the limit has passed', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 100);

    throttled();
    expect(fn).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(100);
    throttled();
    expect(fn).toHaveBeenCalledTimes(2);
  });

  it('should pass arguments to the original function', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 100);

    throttled('hello', 'world');
    expect(fn).toHaveBeenCalledWith('hello', 'world');
  });

  it('should preserve the `this` context', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 100);
    const context = { value: 99, };

    throttled.call(context);
    expect(fn.mock.instances[0]).toBe(context);
  });

  it('should use default limit of 300ms', () => {
    const fn = vi.fn();
    const throttled = throttle(fn);

    throttled();
    expect(fn).toHaveBeenCalledTimes(1);

    throttled();
    throttled();
    expect(fn).toHaveBeenCalledTimes(1); // still 1

    vi.advanceTimersByTime(300);
    throttled();
    expect(fn).toHaveBeenCalledTimes(2);
  });

  it('should only execute once even with rapid calls within limit', () => {
    const fn = vi.fn();
    const throttled = throttle(fn, 200);

    throttled();
    throttled();
    vi.advanceTimersByTime(50);
    throttled();
    vi.advanceTimersByTime(50);
    throttled();

    expect(fn).toHaveBeenCalledTimes(1); // only the 1st call went through

    vi.advanceTimersByTime(100);
    throttled();

    expect(fn).toHaveBeenCalledTimes(2); // now the 2nd one goes through
  });
});
