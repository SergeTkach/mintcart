<?php
namespace MintCart\Compatibility\OpenCart3;

/**
 * Wraps OpenCart 3's `setting/extension` model + per-extension config flag
 * behind a normalized MintCart contract (MINTCART_TECHNICAL_PLAN.md §5:
 * "Mint code -> Adapter -> OpenCart model/service/storage").
 *
 * OpenCart splits "installed" (a row in the `extension` table) from
 * "enabled" (a `<type>_<code>_status` config flag each extension's own
 * admin controller sets when saved, e.g. `extension/shipping/flat.php`
 * writes `shipping_flat_status`) - Mint code should not need to know that
 * two-step storage shape just to answer "is any shipping method usable
 * right now?". `MintCart\Onboarding\StoreReadiness`'s shipping/payment
 * checks (via `ModelMintcartReadiness`) are this adapter's first real
 * consumer (CLAUDE.md §85); a future Business -> Delivery/Payments facade
 * (MINTCART_TECHNICAL_PLAN.md §23) is expected to need the same check.
 *
 * Takes OpenCart's own `Config` object and the already-loaded
 * `setting/extension` model proxy (duck-typed - OpenCart's model proxies
 * have no common interface) rather than a full \Registry, since that is
 * all the underlying signal needs; callers that already have both
 * (any Model/Controller, which load `setting/extension` via
 * `$this->load->model()`) do not gain anything from a heavier dependency.
 */
final class ExtensionAdapter
{
    private $config;
    private $modelSettingExtension;

    /**
     * @param object $config OpenCart's `Config` object (or any object
     *        exposing `get(string $key)`).
     * @param object $modelSettingExtension The loaded `setting/extension`
     *        model proxy (or any object exposing
     *        `getInstalled(string $type): array`).
     */
    public function __construct($config, $modelSettingExtension)
    {
        $this->config = $config;
        $this->modelSettingExtension = $modelSettingExtension;
    }

    /**
     * Whether at least one extension of the given type (e.g. `shipping`,
     * `payment`) is both installed and enabled.
     */
    public function hasEnabled(string $type): bool
    {
        return count($this->getEnabledCodes($type)) > 0;
    }

    /**
     * @return list<string> Installed extension codes of the given type
     *         whose own `<type>_<code>_status` config flag is truthy.
     */
    private function getEnabledCodes(string $type): array
    {
        $enabled = [];

        foreach ($this->modelSettingExtension->getInstalled($type) as $code) {
            if ($this->config->get($type . '_' . $code . '_status')) {
                $enabled[] = $code;
            }
        }

        return $enabled;
    }
}
