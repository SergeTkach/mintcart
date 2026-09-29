<?php
/**
 * Setup Wizard shell (Functional Scope §2, Implementation Guide §54).
 *
 * Route: mintcart/wizard. Fully additive - no existing OpenCart controller,
 * model or template is modified, same "0 controller patches" shape as
 * ControllerMintcartCart (Phase 15). Standalone page: it does not include
 * common/header, common/column_left or common/footer, so it never shares a
 * page with the legacy Bootstrap 3 admin chrome (the exact conflict that
 * forced the storefront's own asset-enqueue attempt to be reverted - see
 * docs/IMPLEMENTATION_STATUS.md's Phase 15 "human session" update). This is
 * the first live page anywhere in MintCart to actually load
 * assets/dist/css/index.min.css.
 *
 * Step order/resume logic lives in MintCart\Onboarding\WizardProgress (pure,
 * no OpenCart dependency, unit-tested standalone). This controller only:
 * loads/saves progress via the standard oc_setting table (code
 * 'mintcart_wizard' - Mint-specific state, not duplicated OpenCart data,
 * CLAUDE.md §6/§39), validates + saves each step's own fields, and renders.
 *
 * No explicit hasPermission() gate: this is meant to be the first screen a
 * freshly-installed admin sees, the same reasoning ControllerCommonDashboard
 * uses for not gating on a permission that a brand-new install's default
 * user group may not even have been granted yet. A valid admin session
 * (user_token) is still required, same as every other admin route. The
 * Delivery step's own toggleDelivery() action keeps this same no-gate
 * shape, unlike ControllerMintcartBusinessDelivery::toggle()'s explicit
 * hasPermission('modify', ...) check - deliberately consistent with the
 * rest of this controller rather than with that one.
 */
class ControllerMintcartWizard extends Controller
{
    private $error = [];

    public function index()
    {
        $this->load->language('mintcart/wizard');
        $this->load->model('setting/setting');

        $progress = $this->loadProgress();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->handleSubmit($progress);

            return;
        }

        $requestedStep = isset($this->request->get['step']) ? (string)$this->request->get['step'] : null;

        $this->render($this->resolveViewableStep($progress, $requestedStep), $progress);
    }

    private function loadProgress(): \MintCart\Onboarding\WizardProgress
    {
        $setting = $this->model_setting_setting->getSetting('mintcart_wizard');

        $completed = isset($setting['mintcart_wizard_completed_steps']) && is_array($setting['mintcart_wizard_completed_steps'])
            ? $setting['mintcart_wizard_completed_steps']
            : [];

        return new \MintCart\Onboarding\WizardProgress($completed);
    }

    /**
     * Only ever returns a step the visitor is actually allowed to see: an
     * implemented step that is either already completed (so they can review
     * it) or is the live resume point. Anything else - an unimplemented
     * step, or an implemented step further along than the resume point -
     * falls back to the resume point, so the URL's ?step= can never be used
     * to skip ahead of what's actually been completed.
     */
    private function resolveViewableStep(\MintCart\Onboarding\WizardProgress $progress, ?string $requestedStep): ?string
    {
        $current = $progress->currentStep();

        if ($requestedStep !== null
            && $progress->isImplemented($requestedStep)
            && ($progress->isCompleted($requestedStep) || $requestedStep === $current)
        ) {
            return $requestedStep;
        }

        return $current;
    }

    private function handleSubmit(\MintCart\Onboarding\WizardProgress $progress): void
    {
        $step = isset($this->request->post['step']) ? (string)$this->request->post['step'] : '';
        $current = $progress->currentStep();

        if ($step === '' || $step !== $current) {
            // Not the live resume step (stale form, tampered request, or
            // everything is already done) - redirect back to whatever the
            // real current state is rather than trusting the client.
            $this->response->redirect($this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true));

            return;
        }

        if ($step === 'store') {
            $this->validateStoreStep();
        }

        if ($step === 'contacts') {
            $this->validateContactsStep();
        }

        if ($step === 'language_currency') {
            $this->validateLanguageCurrencyStep();
        }

        // Delivery has nothing to validate: unlike Store/Language & currency
        // it collects no fields of its own on this step's Continue submit -
        // shipping methods are toggled through their own dedicated action
        // (toggleDelivery(), see below), and Functional Scope §2 explicitly
        // allows "optional steps" to be skipped, so a store with zero
        // enabled shipping methods yet is still a valid state to continue
        // from (the Ready step's own StoreReadiness checklist is what
        // actually surfaces "shipping configured" as outstanding, not this
        // step's ability to proceed).

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        if ($this->error) {
            $this->render($step, $progress);

            return;
        }

        if ($step === 'store') {
            $this->saveStoreStep();
        }

        if ($step === 'contacts') {
            $this->saveContactsStep();
        }

        if ($step === 'language_currency') {
            $this->saveLanguageCurrencyStep();
        }

        $this->model_setting_setting->editSetting('mintcart_wizard', [
            'mintcart_wizard_completed_steps' => $progress->withCompleted($step),
        ]);

        $this->response->redirect($this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function validateStoreStep(): void
    {
        $name = trim((string)($this->request->post['config_name'] ?? ''));
        $email = trim((string)($this->request->post['config_email'] ?? ''));
        $telephone = trim((string)($this->request->post['config_telephone'] ?? ''));
        $address = trim((string)($this->request->post['config_address'] ?? ''));

        if ($name === '' || utf8_strlen($name) > 64) {
            $this->error['name'] = $this->language->get('error_name');
        }

        if ($email === '' || utf8_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error['email'] = $this->language->get('error_email');
        }

        if (utf8_strlen($telephone) < 3 || utf8_strlen($telephone) > 32) {
            $this->error['telephone'] = $this->language->get('error_telephone');
        }

        if (utf8_strlen($address) < 3 || utf8_strlen($address) > 256) {
            $this->error['address'] = $this->language->get('error_address');
        }
    }

    /**
     * The social networks the Contacts step collects a profile URL for.
     * Matches MintCart\Design\FooterViewProvider::buildSocial()'s `network`
     * values exactly - `saveContactsStep()` writes this same set of codes,
     * so a link this step saves needs no translation before it can be
     * handed straight to that provider (see ControllerCommonFooter, wired
     * this run).
     */
    private const SOCIAL_NETWORKS = ['facebook', 'instagram', 'twitter', 'youtube'];

    /**
     * All four fields are optional - a store with no social presence yet is
     * a completely valid, finishable state (Functional Scope §2 lists
     * social profiles under Contacts, but nothing in the product vision
     * makes them mandatory to reach "ready"). A filled-in field must be a
     * well-formed http(s) URL - this is what gets rendered as an `href` on
     * the live storefront footer, so a scheme is checked explicitly rather
     * than trusting FILTER_VALIDATE_URL alone: it happily accepts
     * `javascript:...`/`data:...` as "valid" URLs (it only checks syntax,
     * not scheme), which would otherwise let a stored link execute script
     * in a storefront visitor's browser when they click it.
     */
    private function validateContactsStep(): void
    {
        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = trim((string)($this->request->post['social_' . $network] ?? ''));

            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url))) {
                $this->error['social_' . $network] = sprintf($this->language->get('error_social'), $this->language->get('entry_social_' . $network));
            }
        }
    }

    /**
     * Stored under its own 'mintcart_contacts' setting code, never merged
     * into 'config' - social profile links have no standard OpenCart
     * storage to reuse (same "genuinely new capability" reasoning
     * FooterViewProvider's own doc-comment already gives for why `$social`
     * defaults to empty, CLAUDE.md §6/§39), and unlike 'config' this code
     * has exactly one key MintCart itself owns end to end, so there is no
     * editSetting() partial-write trap to guard against here (nothing else
     * writes to 'mintcart_contacts').
     *
     * Written in the exact {network, href} shape
     * MintCart\Design\FooterViewProvider::build()'s $social parameter
     * expects, so ControllerCommonFooter can pass this setting's value
     * straight through with no reshaping.
     */
    private function saveContactsStep(): void
    {
        $social = [];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = trim((string)($this->request->post['social_' . $network] ?? ''));

            if ($url !== '') {
                $social[] = [
                    'network' => $network,
                    'href' => $url,
                ];
            }
        }

        $this->model_setting_setting->editSetting('mintcart_contacts', [
            'mintcart_contacts_social' => $social,
        ]);
    }

    /**
     * Merge-then-save, never a bare editSetting('config', $partialData) -
     * ModelSettingSetting::editSetting() DELETEs every existing row for the
     * given code before re-inserting only the keys it's handed, so saving a
     * 4-field partial without merging the store's full existing 'config'
     * settings first would silently wipe out every other config_* setting
     * (currency, tax, checkout, ...) on the very first Wizard save. Same
     * "inspect the existing implementation before changing a flow" rule
     * CLAUDE.md §84/§10 requires - this was checked against the real model
     * (upload/admin/model/setting/setting.php) before writing this method,
     * not assumed from memory.
     */
    private function saveStoreStep(): void
    {
        $existing = $this->model_setting_setting->getSetting('config');

        $merged = array_merge($existing, [
            'config_name'      => trim((string)$this->request->post['config_name']),
            'config_email'     => trim((string)$this->request->post['config_email']),
            'config_telephone' => trim((string)$this->request->post['config_telephone']),
            'config_address'   => trim((string)$this->request->post['config_address']),
        ]);

        $this->model_setting_setting->editSetting('config', $merged);
    }

    /**
     * Re-reads the network => href map saved by saveContactsStep(), keyed by
     * network code, so revisiting the step (allowed per resolveViewableStep())
     * re-populates each field instead of showing it blank.
     *
     * @return array<string, string>
     */
    private function loadContactsSocial(): array
    {
        $setting = $this->model_setting_setting->getSetting('mintcart_contacts');
        $social = isset($setting['mintcart_contacts_social']) && is_array($setting['mintcart_contacts_social'])
            ? $setting['mintcart_contacts_social']
            : [];

        $byNetwork = [];

        foreach ($social as $link) {
            if (!empty($link['network']) && !empty($link['href'])) {
                $byNetwork[$link['network']] = $link['href'];
            }
        }

        return $byNetwork;
    }

    /**
     * config_language/config_currency are existing OpenCart 'config' settings
     * (the same store-default fields Settings -> Store's own General tab
     * manages, upload/admin/controller/setting/store.php) - a code must
     * actually exist in oc_language/oc_currency, mirroring Settings -> Store's
     * own permissiveness rather than inventing a stricter "must be status
     * enabled" rule that screen itself doesn't enforce (CLAUDE.md §84: checked
     * against the real controller before writing this, not assumed).
     */
    private function validateLanguageCurrencyStep(): void
    {
        $this->load->model('localisation/language');
        $this->load->model('localisation/currency');

        $language = trim((string)($this->request->post['config_language'] ?? ''));
        $currency = trim((string)($this->request->post['config_currency'] ?? ''));

        if ($language === '' || !$this->model_localisation_language->getLanguageByCode($language)) {
            $this->error['language'] = $this->language->get('error_language');
        }

        if ($currency === '' || !$this->model_localisation_currency->getCurrencyByCode($currency)) {
            $this->error['currency'] = $this->language->get('error_currency');
        }
    }

    /**
     * Same merge-then-save shape saveStoreStep() uses, into the same 'config'
     * setting group - config_language/config_currency are two more keys in
     * that one existing row set, not a new Mint-owned duplicate (CLAUDE.md
     * §6/§39).
     */
    private function saveLanguageCurrencyStep(): void
    {
        $existing = $this->model_setting_setting->getSetting('config');

        $merged = array_merge($existing, [
            'config_language' => trim((string)$this->request->post['config_language']),
            'config_currency' => trim((string)$this->request->post['config_currency']),
        ]);

        $this->model_setting_setting->editSetting('config', $merged);
    }

    private function render(?string $step, \MintCart\Onboarding\WizardProgress $progress): void
    {
        $data['heading_title'] = $this->language->get('heading_title');
        $data['error'] = $this->error;

        $data['asset_base'] = HTTP_CATALOG;
        $data['action'] = $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true);
        $data['dashboard'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

        $data['step'] = $step;
        $data['is_ready'] = $step === null;

        $data['nav_steps'] = [];

        foreach ($progress->steps() as $navStep) {
            $data['nav_steps'][] = [
                'code'      => $navStep,
                'label'     => $this->language->get('step_' . $navStep),
                'completed' => $progress->isCompleted($navStep),
                'current'   => $navStep === $step,
                'reachable' => $progress->isImplemented($navStep)
                    && ($progress->isCompleted($navStep) || $navStep === $progress->currentStep()),
            ];
        }

        if ($step === 'store') {
            $data['config_name'] = $this->request->post['config_name'] ?? $this->config->get('config_name');
            $data['config_email'] = $this->request->post['config_email'] ?? $this->config->get('config_email');
            $data['config_telephone'] = $this->request->post['config_telephone'] ?? $this->config->get('config_telephone');
            $data['config_address'] = $this->request->post['config_address'] ?? $this->config->get('config_address');
        }

        if ($step === 'contacts') {
            $saved = $this->loadContactsSocial();

            foreach (self::SOCIAL_NETWORKS as $network) {
                $data['social_' . $network] = $this->request->post['social_' . $network] ?? ($saved[$network] ?? '');
            }
        }

        if ($step === 'language_currency') {
            $this->load->model('localisation/language');
            $this->load->model('localisation/currency');

            $data['config_language'] = $this->request->post['config_language'] ?? $this->config->get('config_language');
            $data['config_currency'] = $this->request->post['config_currency'] ?? $this->config->get('config_currency');
            $data['languages'] = $this->model_localisation_language->getLanguages();
            $data['currencies'] = $this->model_localisation_currency->getCurrencies();
        }

        if ($step === 'delivery') {
            $data['shipping_extensions'] = $this->loadShippingExtensions();
            $data['toggle_delivery'] = $this->url->link('mintcart/wizard/toggleDelivery', 'user_token=' . $this->session->data['user_token'] . '&step=delivery', true);
            $data['manage_delivery'] = $this->url->link('mintcart/business/delivery', 'user_token=' . $this->session->data['user_token'], true);
        }

        if ($step === 'ready') {
            $data['readiness'] = $this->loadReadiness();
        }

        $this->response->setOutput($this->load->view('mintcart/wizard', $data));
    }

    /**
     * route=mintcart/wizard/toggleDelivery - POST code. Toggles one shipping
     * extension's enabled state without advancing/validating the wizard step
     * itself, then redirects back to this same step - same shape
     * ControllerMintcartBusinessDelivery::toggle() already established
     * (`Business -> Delivery`, Phase 14), duplicated here rather than shared
     * because the two controllers' surrounding contexts differ (this one has
     * no hasPermission() gate anywhere else either, see the class docblock)
     * - the same "two near-duplicate controllers, not a shared base class"
     * call `docs/developer/onboarding/business-delivery-payments.md` already
     * made for Business -> Delivery vs. Business -> Payments.
     */
    public function toggleDelivery(): void
    {
        $this->load->language('mintcart/wizard');
        $this->load->model('setting/extension');
        $this->load->model('setting/setting');

        $code = isset($this->request->post['code']) ? (string)$this->request->post['code'] : '';

        if ($code !== '' && in_array($code, $this->model_setting_extension->getInstalled('shipping'), true)) {
            // Merge-then-save, never a bare editSetting('shipping_<code>', [...]) -
            // ModelSettingSetting::editSetting() DELETEs every existing row
            // for the setting code first, so toggling just the *_status key
            // without re-supplying the extension's other settings (cost, tax
            // class, geo zone, ...) would silently wipe them. Same trap
            // saveStoreStep()/saveLanguageCurrencyStep() already document for
            // the 'config' code, and ControllerMintcartBusinessDelivery::toggle()
            // documents for this exact 'shipping_<code>' code.
            $existing = $this->model_setting_setting->getSetting('shipping_' . $code);

            $existing['shipping_' . $code . '_status'] = empty($existing['shipping_' . $code . '_status']) ? '1' : '0';

            $this->model_setting_setting->editSetting('shipping_' . $code, $existing);
        }

        $this->response->redirect($this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'] . '&step=delivery', true));
    }

    /**
     * Read-only list of installed shipping extensions for the Delivery step
     * - same data source and "only list an extension that still has a real
     * controller file" defensive check
     * `ControllerMintcartBusinessDelivery::getExtensionRows()` already
     * established (Phase 14): `ModelSettingExtension::getInstalled('shipping')`
     * for the list, each extension's own language file for its display name,
     * and the `shipping_<code>_status` config key every stock shipping
     * extension controller already saves under for its enabled state.
     *
     * @return array<int, array{code: string, name: string, enabled: bool}>
     */
    private function loadShippingExtensions(): array
    {
        $this->load->model('setting/extension');

        $rows = [];

        foreach ($this->model_setting_extension->getInstalled('shipping') as $code) {
            if (!is_file(DIR_APPLICATION . 'controller/extension/shipping/' . $code . '.php')) {
                continue;
            }

            $this->load->language('extension/shipping/' . $code, 'extension');

            $rows[] = [
                'code' => $code,
                'name' => $this->language->get('extension')->get('heading_title'),
                'enabled' => (bool)$this->config->get('shipping_' . $code . '_status'),
            ];
        }

        return $rows;
    }

    /**
     * Reuses MintCart\Onboarding\StoreReadiness (Phase 14) rather than a
     * second copy of the computation - same evaluate() call and same
     * mintcart/readiness signal source ControllerMintcartReadiness's JSON
     * endpoint already uses, just rendered into this page instead of JSON.
     * Loading the model directly (not the mintcart/readiness route) means
     * this deliberately does not go through that controller's own
     * hasPermission('access', 'mintcart/readiness') gate - consistent with
     * the rest of this controller having no hasPermission() gate of its own
     * (see the class docblock).
     */
    private function loadReadiness(): array
    {
        $this->load->language('mintcart/readiness');
        $this->load->model('mintcart/readiness');

        $readiness = new \MintCart\Onboarding\StoreReadiness();

        return $readiness->evaluate($this->model_mintcart_readiness->getSignals(), [
            'logo'          => $this->language->get('text_check_logo'),
            'contacts'      => $this->language->get('text_check_contacts'),
            'language'      => $this->language->get('text_check_language'),
            'currency'      => $this->language->get('text_check_currency'),
            'shipping'      => $this->language->get('text_check_shipping'),
            'payment'       => $this->language->get('text_check_payment'),
            'first_product' => $this->language->get('text_check_first_product'),
        ]);
    }
}
