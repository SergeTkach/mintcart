<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='spacer') into the setting
 * `blocks/spacer.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16 ("Block
 * Provider" normalizes settings) and MINTCART_IMPLEMENTATION_GUIDE.md §33
 * (Spacer is part of the Homepage Builder's first block catalog).
 *
 * The simplest possible block: purely structural (no localized content,
 * no OpenCart query at all) - it exists only to let a homepage builder
 * insert vertical spacing between two other blocks without an empty
 * "Text" block being repurposed for that. Same "no OpenCart dependency"
 * shape as ProductsBlockProvider/TextImageBlockProvider.
 */
final class SpacerBlockProvider
{
    private const DEFAULT_SIZE = 'md';

    private const ALLOWED_SIZES = ['sm', 'md', 'lg', 'xl'];

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (`size`: one of sm/md/lg/xl).
     *
     * @return array{size: string}
     */
    public function build(array $settings = []): array
    {
        $size = is_string($settings['size'] ?? null) ? $settings['size'] : '';

        return [
            'size' => in_array($size, self::ALLOWED_SIZES, true) ? $size : self::DEFAULT_SIZE,
        ];
    }
}
