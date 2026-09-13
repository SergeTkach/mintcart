<?php
namespace MintCart\Onboarding;

/**
 * Pure helpers for the social-profile-link `{network, href}` list shape
 * `MintCart\Design\FooterViewProvider::build()`'s `$social` parameter
 * expects (`MINTCART_FUNCTIONAL_SCOPE.md` §3, `MINTCART_TECHNICAL_PLAN.md`
 * §23 "Business Settings").
 *
 * No OpenCart dependency, same shape as WizardProgress/StoreReadiness. This
 * is deliberately a small standalone class rather than a refactor of
 * `ControllerMintcartWizard`'s existing inline Contacts-step validation
 * (`upload/admin/controller/mintcart/wizard.php` on the `wizard-contacts`
 * branch, not yet on `master` at the time this was written) - retrofitting
 * that controller here would touch the same file an already-open pull
 * request is also changing, risking a collision for no functional benefit
 * this run. `ControllerMintcartBusinessContacts` (this run's other new
 * file, `upload/admin/controller/mintcart/business/contacts.php`) is this
 * class's first real consumer; a future run may fold the Wizard's own copy
 * into this class once that PR has landed.
 */
final class SocialProfileLinks
{
    public const NETWORKS = ['facebook', 'instagram', 'twitter', 'youtube'];

    /**
     * Blank is always valid (every network is optional). A non-blank value
     * must be a well-formed URL **and** start with `http://`/`https://` -
     * `FILTER_VALIDATE_URL` alone accepts `javascript:...`/`data:...` as
     * "valid" (it only checks syntax, not scheme), which would let a saved
     * link execute script in a storefront visitor's browser when rendered
     * as a plain `href` on the footer.
     */
    public static function isValidUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }

    /**
     * @param array<string, string> $urlsByNetwork Raw posted values, keyed
     *                                              by network code.
     * @return list<array{network: string, href: string}> Blank entries
     *         dropped, in NETWORKS order - the exact shape FooterViewProvider
     *         and the `mintcart_contacts` setting already store.
     */
    public static function normalize(array $urlsByNetwork): array
    {
        $result = [];

        foreach (self::NETWORKS as $network) {
            $url = trim((string)($urlsByNetwork[$network] ?? ''));

            if ($url !== '') {
                $result[] = ['network' => $network, 'href' => $url];
            }
        }

        return $result;
    }

    /**
     * The inverse of normalize() - re-keys a stored `{network, href}[]`
     * list by network so a form can re-populate each field individually.
     * Entries missing either key, or naming a network outside NETWORKS,
     * are ignored rather than trusted as-is (storage may have been hand-
     * edited or written by a future, wider NETWORKS list).
     *
     * @param list<array{network?: string, href?: string}> $social
     * @return array<string, string>
     */
    public static function byNetwork(array $social): array
    {
        $byNetwork = [];

        foreach ($social as $link) {
            $network = $link['network'] ?? '';
            $href = $link['href'] ?? '';

            if ($network !== '' && $href !== '' && in_array($network, self::NETWORKS, true)) {
                $byNetwork[$network] = $href;
            }
        }

        return $byNetwork;
    }
}
