<?php

declare(strict_types=1);

namespace MintCart\Design;

/**
 * Decides whether an uploaded SVG is safe to store and serve from the
 * store's own domain (e.g. as the logo).
 *
 * Inside <img> an SVG never runs script, but the same file opened by its
 * URL does - so a logo with <script> or an onload= would be stored XSS on
 * the shop's origin. Rather than rewriting the file, anything risky is
 * refused and the owner gets an error; a normal exported logo (Figma,
 * Illustrator, Inkscape) has none of it.
 *
 * Refused: DOCTYPE/entities (XXE, billion laughs), <script>,
 * <foreignObject> (embeds HTML), <iframe>/<embed>/<object>, any on*
 * attribute, and href/xlink:href/src pointing anywhere but an in-file
 * #fragment or an embedded raster data:image (javascript:, external URLs).
 */
final class SvgValidator
{
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener'];

    private const SAFE_DATA_URI = '#^data:image/(png|jpe?g|gif|webp);base64,#i';

    public static function isSafe(string $svg): bool
    {
        if ($svg === '' || stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        // LIBXML_NONET: never fetch anything over the network while parsing.
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (in_array(strtolower($element->localName), self::FORBIDDEN_ELEMENTS, true)) {
                return false;
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->localName);
                $value = trim($attribute->value);

                if (str_starts_with($name, 'on')) {
                    return false;
                }

                if (in_array($name, ['href', 'src'], true)
                    && $value !== ''
                    && $value[0] !== '#'
                    && !preg_match(self::SAFE_DATA_URI, $value)) {
                    return false;
                }

                // url(javascript:...) / expression() in inline styles.
                if ($name === 'style' && preg_match('/javascript:|expression\s*\(|@import/i', $value)) {
                    return false;
                }
            }
        }

        // <style> blocks: same checks as inline styles.
        foreach ($document->getElementsByTagName('style') as $style) {
            if (preg_match('/javascript:|expression\s*\(|@import|url\s*\(\s*[\'"]?\s*(?!#|data:image)/i', $style->textContent)) {
                return false;
            }
        }

        return true;
    }
}
