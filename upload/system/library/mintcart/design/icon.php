<?php
namespace MintCart\Design;

/**
 * Inline SVG for a semantic icon key ("truck", "shield-check", ...), read
 * from Bootstrap Icons files copied to `system/mintcart/icons/`
 * (MINTCART_IMPLEMENTATION_GUIDE.md §21: Bootstrap Icons as SVG source, no
 * icon font). Returns '' for an unknown key, so a block without an icon
 * still renders. Add an icon by copying its .svg from
 * `node_modules/bootstrap-icons/icons/` into that directory.
 */
final class Icon
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
    }

    public function svg(string $name): string
    {
        // Key becomes part of a file path: allow only the icon-name alphabet.
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            return '';
        }

        $file = $this->directory . '/' . $name . '.svg';

        if (!is_file($file)) {
            return '';
        }

        $svg = (string) file_get_contents($file);

        // Size comes from CSS; the file's own width/height are 16px.
        $svg = preg_replace('/\s(width|height)="\d+"/', '', $svg) ?? $svg;

        return str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', trim($svg));
    }
}
