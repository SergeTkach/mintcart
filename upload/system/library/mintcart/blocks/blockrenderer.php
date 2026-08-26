<?php
namespace MintCart\Blocks;

/**
 * Resolves and renders a MintCart Homepage Builder block template
 * (e.g. `products`) against the active theme's `template/mint/blocks/`
 * subtree, per MINTCART_TECHNICAL_PLAN.md §16/§17 ("Block Provider / Block
 * Renderer / Block Template" as distinct responsibilities) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §32.
 *
 * Delegates entirely to MintCart\Design\ComponentRenderer with
 * kind='blocks' — the active theme -> theme.json parent chain -> Mint
 * Default fallback walk a block template needs is identical to what a
 * component needs, so this class exists only to give Blocks its own
 * documented entry point (matching the Technical Plan's naming) without
 * duplicating that walk a second time.
 */
final class BlockRenderer
{
    private \MintCart\Design\ComponentRenderer $componentRenderer;

    public function __construct(string $themesDirectory, string $activeTheme)
    {
        $this->componentRenderer = new \MintCart\Design\ComponentRenderer($themesDirectory, $activeTheme, 'blocks');
    }

    /**
     * @throws \RuntimeException if the block type exists nowhere in the
     *         chain, including Mint Default.
     */
    public function resolve(string $type): string
    {
        return $this->componentRenderer->resolve($type);
    }

    /**
     * @param array<string, mixed> $data Template variables — typically a
     *        block provider's build() result plus the block's documented
     *        `text_*` language strings.
     */
    public function render(string $type, array $data = []): string
    {
        return $this->componentRenderer->render($type, $data);
    }
}
