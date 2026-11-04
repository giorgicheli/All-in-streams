<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A small document vocabulary: no images, uploads, HTML, styles or custom blocks. */
class LegalRichText implements ValidationRule
{
    private int $nodes = 0;
    private const BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'horizontalRule'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->nodes = 0;
        if (!is_array($value) || strlen(json_encode($value)) > 262144 || !$this->node($value, 0, ['doc'])) {
            $fail('Use the text editor with headings, paragraphs, lists and safe links. Images, embedded HTML and unsupported formatting are not allowed.');
        }
    }

    private function node(mixed $node, int $depth, array $allowed): bool
    {
        if (!is_array($node) || ++$this->nodes > 2000 || $depth > 16 || !in_array($node['type'] ?? null, $allowed, true)) return false;
        $type = $node['type'];
        if (array_diff(array_keys($node), ['type', 'content', 'attrs', 'marks', 'text'])) return false;
        if ($type === 'text') {
            if (!is_string($node['text'] ?? null) || $node['text'] === '' || array_key_exists('content', $node) || array_key_exists('attrs', $node)) return false;
            if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $node['text'])) return false;
        } elseif (array_key_exists('text', $node)) return false;
        $attrs = $node['attrs'] ?? [];
        if (!is_array($attrs)) return false;
        $attributeKeys = match ($type) { 'heading' => ['level', 'textAlign'], 'paragraph' => ['textAlign'], 'orderedList' => ['start', 'type'], default => [] };
        if (array_diff(array_keys($attrs), $attributeKeys)) return false;
        if ($type === 'heading' && (!is_int($attrs['level'] ?? null) || !in_array($attrs['level'], [1, 2, 3, 4, 5, 6], true))) return false;
        if (isset($attrs['textAlign']) && !in_array($attrs['textAlign'], ['start', 'end', 'left', 'right', 'center', 'justify'], true)) return false;
        if (isset($attrs['start']) && (!is_int($attrs['start']) || $attrs['start'] < 1 || $attrs['start'] > 100000)) return false;
        if (isset($attrs['type']) && !in_array($attrs['type'], ['1', 'a', 'A', 'i', 'I'], true)) return false;
        $marks = $node['marks'] ?? [];
        if (!is_array($marks) || !array_is_list($marks) || count($marks) > 8 || ($marks && !in_array($type, ['text', 'hardBreak'], true))) return false;
        foreach ($marks as $mark) {
            if (!is_array($mark) || array_diff(array_keys($mark), ['type', 'attrs']) || !in_array($mark['type'] ?? null, ['bold', 'italic', 'underline', 'strike', 'link'], true)) return false;
            $markAttrs = $mark['attrs'] ?? [];
            if (!is_array($markAttrs)) return false;
            if ($mark['type'] !== 'link') { if ($markAttrs) return false; continue; }
            if (array_diff(array_keys($markAttrs), ['href', 'target', 'rel', 'class']) || !SiteLink::allowed($markAttrs['href'] ?? null)) return false;
            if (isset($markAttrs['target']) && !in_array($markAttrs['target'], ['_blank', '_self'], true)) return false;
            if (isset($markAttrs['class']) && $markAttrs['class'] !== '') return false;
            if (isset($markAttrs['rel']) && (!is_string($markAttrs['rel']) || preg_match('/[^a-z\s-]/', $markAttrs['rel']))) return false;
        }
        if (in_array($type, ['text', 'hardBreak', 'horizontalRule'], true)) return !array_key_exists('content', $node);
        $children = $node['content'] ?? [];
        if (!is_array($children) || !array_is_list($children)) return false;
        $childTypes = match ($type) {
            'paragraph', 'heading' => ['text', 'hardBreak'],
            'bulletList', 'orderedList' => ['listItem'],
            default => self::BLOCKS,
        };
        foreach ($children as $child) if (!$this->node($child, $depth + 1, $childTypes)) return false;
        return true;
    }
}
