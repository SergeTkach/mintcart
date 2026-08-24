<?php
namespace MintCart\Design;

/**
 * Normalizes footer presentation data into the stable Footer component
 * contract described in MINTCART_IMPLEMENTATION_GUIDE.md §48
 * ("Footer, Footer Columns, Contacts, Social links, Payments, Newsletter")
 * and MINTCART_TECHNICAL_PLAN.md §14.
 *
 * Mirrors MintCart\Commerce\ProductViewProvider's role for product-card:
 * the single place the `footer` shape every Mint theme's `footer.twig`
 * override renders against gets assembled, so contact/business data stays
 * centralized instead of duplicated per theme
 * (MINTCART_IMPLEMENTATION_GUIDE.md §6, CLAUDE.md §39: "no per-theme
 * contact duplication").
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \Config), same
 * rationale as ProductViewProvider: given already-resolved values (e.g.
 * OpenCart's own `config_telephone`/`config_email`/`config_address`
 * settings - standard Setting storage, never duplicated into a Mint-owned
 * table per MINTCART_IMPLEMENTATION_GUIDE.md §39), this class only shapes
 * what the caller already has. Reading those settings and wiring a real
 * `catalog/controller/common/footer.php` call site is left to a future run
 * (no live theme consumes this yet), same phase-split ProductViewProvider
 * (Phase 6) went through before ControllerProductCategory wired it (Phase 8).
 */
final class FooterViewProvider
{
    /**
     * @param array<int, array{title?: string, links?: array<int, array{label?: string, href?: string}>}> $columns
     *        Footer link columns (e.g. Information / Service / Account) -
     *        the normalized shape of what ControllerCommonFooter's
     *        `informations` array plus its individual link fields
     *        (`contact`, `return`, `sitemap`, `account`, ...) already
     *        resolve today, just grouped into columns instead of loose
     *        top-level keys.
     * @param array{phone?: ?string, email?: ?string, address?: ?string} $contacts
     *        Store contact details. Sourced from OpenCart's own
     *        `config_telephone`/`config_email`/`config_address` settings -
     *        never re-entered into a Mint-owned table.
     * @param array<int, array{network?: string, href?: string, label?: ?string}> $social
     *        Social profile links. OpenCart has no built-in storage for
     *        these today; empty unless the caller supplies real data (no
     *        config source is invented here - CLAUDE.md §85 - this stays
     *        an empty array until Business Settings/Phase 14 adds one).
     * @param array<int, array{name?: string, icon?: ?string}> $payments
     *        Accepted payment method badges shown in the footer. Same
     *        "no storage yet" status as `$social` - reserved for
     *        Business -> Payments (Phase 14), empty by default.
     * @param array{enabled?: bool, action?: ?string}|null $newsletter
     *        Newsletter subscribe form target. `enabled` only ever comes
     *        back true when the caller also supplies a real `action` URL -
     *        there is no subscribe endpoint to submit to yet (Commerce API,
     *        Phase 15), so this defaults to disabled rather than rendering
     *        a form with nowhere to go.
     * @param string $powered Pre-formatted "Powered by X" text (OpenCart's
     *        existing `text_powered` string, already resolved by the
     *        caller - this class does not touch language strings).
     *
     * @return array{
     *     columns: list<array{title: string, links: list<array{label: string, href: string}>}>,
     *     contacts: array{phone: ?string, email: ?string, address: ?string},
     *     social: list<array{network: string, href: string, label: string}>,
     *     payments: list<array{name: string, icon: ?string}>,
     *     newsletter: array{enabled: bool, action: ?string},
     *     powered: string
     * }
     */
    public function build(
        array $columns = [],
        array $contacts = [],
        array $social = [],
        array $payments = [],
        ?array $newsletter = null,
        string $powered = ''
    ): array {
        return [
            'columns' => $this->buildColumns($columns),
            'contacts' => [
                'phone' => $contacts['phone'] ?? null,
                'email' => $contacts['email'] ?? null,
                'address' => $contacts['address'] ?? null,
            ],
            'social' => $this->buildSocial($social),
            'payments' => $this->buildPayments($payments),
            'newsletter' => $this->buildNewsletter($newsletter),
            'powered' => $powered,
        ];
    }

    /**
     * @param array<int, array{title?: string, links?: array<int, array{label?: string, href?: string}>}> $columns
     * @return list<array{title: string, links: list<array{label: string, href: string}>}>
     */
    private function buildColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            $links = [];

            foreach ($column['links'] ?? [] as $link) {
                if (empty($link['label']) || empty($link['href'])) {
                    continue;
                }

                $links[] = [
                    'label' => $link['label'],
                    'href' => $link['href'],
                ];
            }

            // A column with a title but no (surviving) links renders nothing
            // useful - skip it rather than let a theme render an empty <ul>.
            if (empty($column['title']) || $links === []) {
                continue;
            }

            $normalized[] = [
                'title' => $column['title'],
                'links' => $links,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{network?: string, href?: string, label?: ?string}> $social
     * @return list<array{network: string, href: string, label: string}>
     */
    private function buildSocial(array $social): array
    {
        $normalized = [];

        foreach ($social as $link) {
            if (empty($link['network']) || empty($link['href'])) {
                continue;
            }

            $normalized[] = [
                'network' => $link['network'],
                'href' => $link['href'],
                'label' => $link['label'] ?? ucfirst($link['network']),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{name?: string, icon?: ?string}> $payments
     * @return list<array{name: string, icon: ?string}>
     */
    private function buildPayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $payment) {
            if (empty($payment['name'])) {
                continue;
            }

            $normalized[] = [
                'name' => $payment['name'],
                'icon' => $payment['icon'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{enabled?: bool, action?: ?string}|null $newsletter
     * @return array{enabled: bool, action: ?string}
     */
    private function buildNewsletter(?array $newsletter): array
    {
        $action = $newsletter['action'] ?? null;

        return [
            'enabled' => !empty($newsletter['enabled']) && !empty($action),
            'action' => $action,
        ];
    }
}
