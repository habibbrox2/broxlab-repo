<?php

/**
 * Custom rich text editor helper functions.
 * Moved from controller-level example files into shared helpers.
 */

if (!function_exists('sanitizeEditorHtml')) {
    /**
     * Sanitize HTML content to prevent XSS attacks while preserving safe formatting.
     */
    function sanitizeEditorHtml($html): string
    {
        if (empty($html)) {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return trim(strip_tags((string) $html));
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);

        $allowedTags = [
            'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'blockquote', 'hr', 'a', 'img', 'span', 'div',
            'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot',
            'video', 'iframe', 'source'
        ];

        $allowedAttributes = [
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'width', 'height', 'title'],
            'iframe' => ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen'],
            'video' => ['width', 'height', 'controls', 'poster'],
            'source' => ['src', 'type'],
            'div' => ['style'],
            'span' => ['style'],
            'p' => ['style'],
            'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
            'h5' => ['style'], 'h6' => ['style'],
            'blockquote' => ['style'],
            'table' => ['style', 'border'],
            'tr' => ['style'],
            'td' => ['style', 'colspan', 'rowspan'],
            'th' => ['style', 'colspan', 'rowspan'],
        ];

        $safeCSSProperties = [
            'color', 'background-color', 'font-size', 'font-weight', 'font-style',
            'text-decoration', 'text-align', 'line-height', 'margin', 'padding',
            'border', 'width', 'height', 'max-width', 'max-height'
        ];

        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }

        $styles = $dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }

        $nodesToRemove = [];
        walkDom($dom, $allowedTags, $allowedAttributes, $safeCSSProperties, $nodesToRemove);

        foreach ($nodesToRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        $cleanedHTML = '';
        foreach ($body->childNodes as $node) {
            $cleanedHTML .= $dom->saveHTML($node);
        }

        return trim($cleanedHTML);
    }
}

if (!function_exists('walkDom')) {
    /**
     * Recursively walk through DOM tree and sanitize nodes.
     */
    function walkDom(&$dom, $allowedTags, $allowedAttributes, $safeCSSProperties, &$nodesToRemove, $node = null): void
    {
        if ($node === null) {
            $node = $dom->documentElement;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($child->nodeName);

            if (!in_array($tagName, $allowedTags, true)) {
                while ($child->firstChild) {
                    $child->parentNode->insertBefore($child->firstChild, $child);
                }
                $nodesToRemove[] = $child;
                continue;
            }

            $attrs = $child->attributes;
            for ($i = $attrs->length - 1; $i >= 0; $i--) {
                $attrName = $attrs->item($i)->name;
                $isAllowed = false;

                if (isset($allowedAttributes[$tagName]) && in_array($attrName, $allowedAttributes[$tagName], true)) {
                    $isAllowed = true;
                }

                if (in_array($attrName, ['id', 'class'], true)) {
                    if (preg_match('/^[a-zA-Z0-9_-]+$/', $attrs->item($i)->value)) {
                        $isAllowed = true;
                    }
                }

                if (!$isAllowed) {
                    $child->removeAttribute($attrName);
                }
            }

            if ($child->hasAttribute('style')) {
                $style = $child->getAttribute('style');
                $sanitizedStyle = sanitizeCss($style, $safeCSSProperties);
                if ($sanitizedStyle) {
                    $child->setAttribute('style', $sanitizedStyle);
                } else {
                    $child->removeAttribute('style');
                }
            }

            if ($child->hasAttribute('href')) {
                $href = $child->getAttribute('href');
                if (!isValidUrl($href)) {
                    $child->removeAttribute('href');
                }
            }

            if ($child->hasAttribute('src')) {
                $src = $child->getAttribute('src');
                if (!isValidImageSource($src)) {
                    $child->removeAttribute('src');
                }
            }

            foreach ($child->attributes as $attr) {
                if (strpos($attr->name, 'on') === 0) {
                    $child->removeAttribute($attr->name);
                }
            }

            walkDom($dom, $allowedTags, $allowedAttributes, $safeCSSProperties, $nodesToRemove, $child);
        }
    }
}

if (!function_exists('sanitizeCss')) {
    /**
     * Sanitize CSS to prevent CSS-based attacks.
     */
    function sanitizeCss($style, $safeCSSProperties): string
    {
        $declarations = [];
        $pairs = explode(';', (string) $style);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $colonPos = strpos($pair, ':');
            if ($colonPos === false) {
                continue;
            }

            $property = trim(substr($pair, 0, $colonPos));
            $value = trim(substr($pair, $colonPos + 1));

            if (!in_array(strtolower($property), $safeCSSProperties, true)) {
                continue;
            }

            if (preg_match('/javascript|expression|behavior|binding|import/i', $value)) {
                continue;
            }

            if (strpos(strtolower($property), 'color') !== false && !isValidColor($value)) {
                continue;
            }

            $declarations[] = $property . ': ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return implode('; ', $declarations);
    }
}

if (!function_exists('isValidUrl')) {
    /**
     * Validate URL for safe link usage.
     */
    function isValidUrl($url): bool
    {
        $url = trim((string) $url);

        if (strpos($url, '#') === 0) {
            return true;
        }

        if (strpos($url, '/') === 0 || strpos($url, './') === 0 || strpos($url, '../') === 0) {
            return true;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($url);
            $allowedProtocols = ['http', 'https', 'mailto'];
            if (isset($parsed['scheme']) && in_array($parsed['scheme'], $allowedProtocols, true)) {
                return true;
            }
        }

        if (preg_match('/^(javascript|vbscript|data):/i', $url)) {
            return false;
        }

        return false;
    }
}

if (!function_exists('isValidImageSource')) {
    /**
     * Validate image source URL/path.
     */
    function isValidImageSource($src): bool
    {
        $src = trim((string) $src);

        if (strpos($src, 'data:image/') === 0) {
            return true;
        }

        if (filter_var($src, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (strpos($src, '/') === 0 || strpos($src, './') === 0 || strpos($src, '../') === 0) {
            return true;
        }

        if (preg_match('/^(javascript|vbscript):/i', $src)) {
            return false;
        }

        return false;
    }
}

if (!function_exists('isValidColor')) {
    /**
     * Validate color values used in inline styles.
     */
    function isValidColor($value): bool
    {
        $value = trim((string) $value);

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return true;
        }

        if (preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/', $value)) {
            return true;
        }

        if (preg_match('/^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)$/', $value)) {
            return true;
        }

        $namedColors = ['black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta', 'gray', 'grey'];
        return in_array(strtolower($value), $namedColors, true);
    }
}
