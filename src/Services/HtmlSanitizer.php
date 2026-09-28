<?php

declare(strict_types=1);

namespace Sarva\Services;

/**
 * Lightweight HTML sanitizer for article rich content.
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h2', 'h3', 'h4',
        'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'a', 'blockquote',
        'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'span', 'div',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        // Strip scripts/styles entirely
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*/?>#is', '', $html) ?? $html;

        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $html = strip_tags($html, $allowed);

        // Clean attributes via DOM if available
        if (class_exists(\DOMDocument::class)) {
            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $wrapped = '<?xml encoding="UTF-8"><div id="sarva-root">' . $html . '</div>';
            $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $dom->getElementById('sarva-root');
            if ($root) {
                self::sanitizeNode($root);
                $out = '';
                foreach ($root->childNodes as $child) {
                    $out .= $dom->saveHTML($child);
                }
                libxml_clear_errors();
                libxml_use_internal_errors($prev);
                return $out;
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }

        return $html;
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $allowedAttrs = ['href', 'src', 'alt', 'title', 'class', 'target', 'rel', 'width', 'height'];
            $remove = [];
            foreach ($node->attributes ?? [] as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowedAttrs, true)) {
                    $remove[] = $attr->name;
                    continue;
                }
                $value = $attr->value;
                if (in_array($name, ['href', 'src'], true) && preg_match('#^\s*javascript:#i', $value)) {
                    $remove[] = $attr->name;
                }
            }
            foreach ($remove as $name) {
                $node->removeAttribute($name);
            }
            if (strtolower($node->tagName) === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            self::sanitizeNode($child);
        }
    }
}
