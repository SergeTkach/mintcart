<?php
namespace MintCart\Catalog;

/**
 * Splits a category's rich-text description into a short intro (shown above
 * the product list) and the rest (shown below it), so a long SEO text does
 * not push the products off the first screen.
 *
 * Works on top-level blocks so the markup stays valid; a single long
 * text-only block is cut at a word boundary. No OpenCart dependency.
 */
class DescriptionSplitter
{
    /**
     * @return array{intro: string, rest: string}
     */
    public function split(string $html, int $limit = 360): array
    {
        $html = trim($html);

        if ($html === '' || $this->length($html) <= $limit) {
            return ['intro' => $html, 'rest' => ''];
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);

        if (!$body) {
            return ['intro' => $this->excerpt($html, $limit), 'rest' => $html];
        }

        $intro = '';
        $rest = '';
        $used = 0;
        $full = false;

        foreach (iterator_to_array($body->childNodes) as $node) {
            $outer = $dom->saveHTML($node);
            $size = $this->length($outer);

            if ($full) {
                $rest .= $outer;
                continue;
            }

            if ($used + $size <= $limit) {
                $intro .= $outer;
                $used += $size;
                continue;
            }

            $full = true;

            if ($intro === '' && $node instanceof \DOMElement && !$this->hasElementChildren($node)) {
                $text = trim($node->textContent);
                $cut = $this->cutPoint($text, $limit);
                $tag = $node->nodeName;

                $intro = '<' . $tag . '>' . htmlspecialchars(rtrim(substr($text, 0, $cut)) . '…', ENT_QUOTES, 'UTF-8') . '</' . $tag . '>';
                $tail = ltrim(substr($text, $cut));

                if ($tail !== '') {
                    $rest .= '<' . $tag . '>' . htmlspecialchars($tail, ENT_QUOTES, 'UTF-8') . '</' . $tag . '>';
                }
            } elseif ($intro === '') {
                $intro = '<p>' . htmlspecialchars($this->excerpt($outer, $limit), ENT_QUOTES, 'UTF-8') . '</p>';
                $rest .= $outer;
            } else {
                $rest .= $outer;
            }
        }

        return ['intro' => $intro, 'rest' => $rest];
    }

    private function length(string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
    }

    private function hasElementChildren(\DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    /** Byte offset of the last space within the first $limit characters. */
    private function cutPoint(string $text, int $limit): int
    {
        $head = mb_substr($text, 0, $limit);
        $space = mb_strrpos($head, ' ');

        if ($space !== false && $space > $limit / 2) {
            $head = mb_substr($head, 0, $space);
        }

        return strlen($head);
    }

    private function excerpt(string $html, int $limit): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));

        return rtrim(substr($text, 0, $this->cutPoint($text, $limit))) . '…';
    }
}
