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

    /**
     * Event handler (admin/controller/common/dashboard/before, registered by
     * migration 202610081201): until the wizard is finished or skipped, the
     * dashboard - where login lands - opens the wizard instead
     * (Functional Scope §2: after install the owner should not be dropped
     * into the plain OpenCart admin). Only for users who may run it.
     */
    public function autostart(&$route = '', &$data = array())
    {
        if (!$this->user->isLogged() || !$this->user->hasPermission('modify', 'mintcart/wizard')) {
            return null;
        }

        if ($this->config->get('mintcart_wizard_skip_dismissed')) {
            return null;
        }

        $this->load->model('setting/setting');

        if ($this->loadProgress()->isReady()) {
            return null;
        }

        $this->response->redirect($this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true));
    }

    // "Skip for now": stop the autostart; the wizard stays reachable from
    // the dashboard's Store Readiness widget, with progress kept.
    public function skip()
    {
        if ($this->user->hasPermission('modify', 'mintcart/wizard')) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('mintcart_wizard_skip', ['mintcart_wizard_skip_dismissed' => 1]);
        }

        $this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
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

        // The live resume step, or a step already completed (the owner went
        // Back to change it - saved again, then on to the resume step).
        if ($step === '' || !$progress->isImplemented($step) || ($step !== $current && !$progress->isCompleted($step))) {
            // Not a step the owner may submit (stale form, tampered request,
            // or a step further ahead) - redirect back to whatever the real
            // current state is rather than trusting the client.
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

        // Payment mirrors Delivery exactly, for the same reason: nothing of
        // its own to validate/save on Continue - a payment method is
        // enabled through togglePayment(), not this submission, and a store
        // with zero enabled payment methods can still continue (the Ready
        // step's own StoreReadiness checklist already surfaces "payment
        // configured" as outstanding).

        if ($step === 'design') {
            $this->validateDesignStep();
        }

        $skippingFirstProduct = $step === 'first_product' && ($this->request->post['first_product_action'] ?? '') === 'skip';

        if ($step === 'first_product' && !$skippingFirstProduct) {
            $this->validateFirstProductStep();
        }

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

        if ($step === 'design') {
            $this->saveDesignStep();
        }

        if ($step === 'first_product' && !$skippingFirstProduct) {
            $this->saveFirstProductStep();
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

        $logo = $this->uploadedLogo();

        if ($logo !== null && !$this->isAcceptableLogo($logo)) {
            $this->error['logo'] = $this->language->get('error_logo');
        }
    }

    // Logo is optional: null when no file was chosen.
    private function uploadedLogo(): ?array
    {
        $file = $this->request->files['logo'] ?? null;

        // OpenCart's Request::clean() runs $_FILES through htmlspecialchars(),
        // so error/size arrive as strings - compare them as ints.
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    // SVG, PNG, JPG, GIF or WebP up to 2 MB. Rasters must really decode as
    // an image; an SVG must pass MintCart\Design\SvgValidator (no script,
    // event handlers, external references, DOCTYPE) - served from the
    // store's own domain, a scripted SVG would be stored XSS.
    private function isAcceptableLogo(array $file): bool
    {
        if ((int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || (int)$file['size'] > 2 * 1024 * 1024) {
            return false;
        }

        return $this->logoExtension($file['tmp_name']) !== null;
    }

    private function logoExtension(string $tmpName): ?string
    {
        $info = @getimagesize($tmpName);
        $rasters = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

        if (is_array($info) && isset($rasters[$info[2]])) {
            return $rasters[$info[2]];
        }

        if (\MintCart\Design\SvgValidator::isSafe((string)file_get_contents($tmpName))) {
            return 'svg';
        }

        return null;
    }

    // Stores the logo under image/catalog/ and returns its config_logo path.
    private function storeLogo(array $file): string
    {
        $path = 'catalog/logo-' . date('YmdHis') . '.' . $this->logoExtension($file['tmp_name']);

        move_uploaded_file($file['tmp_name'], DIR_IMAGE . $path);

        return $path;
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
    /**
     * Design: pick one of the active theme's presets - the same
     * `mintcart_design_preset` setting Design -> Styles writes. A theme
     * without presets has nothing to choose; the step just continues.
     */
    private function validateDesignStep(): void
    {
        $preset = (string)($this->request->post['mintcart_design_preset'] ?? '');

        if ($preset !== '' && !in_array($preset, $this->availablePresets(), true)) {
            $this->error['preset'] = $this->language->get('error_preset');
        }
    }

    private function saveDesignStep(): void
    {
        $preset = (string)($this->request->post['mintcart_design_preset'] ?? '');

        if ($this->availablePresets()) {
            $this->model_setting_setting->editSetting('mintcart_design', ['mintcart_design_preset' => $preset]);
        }
    }

    private function availablePresets(): array
    {
        $directory = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));

        return \MintCart\Design\ActiveTheme::presets(DIR_CATALOG . 'view/theme', $directory);
    }

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

        $logo = $this->uploadedLogo();

        if ($logo !== null) {
            $merged['config_logo'] = $this->storeLogo($logo);
        }

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

    /**
     * Only name + price are required - everything else the product table
     * needs (model/sku/upc/.../weight/length) gets a safe empty/zero default
     * in saveFirstProductStep(), the same "ask only what the user actually
     * needs to decide" principle CLAUDE.md §87 asks for (Product Vision §5:
     * the user decides which products, not every field a full product form
     * exposes - the rest is editable later from Catalog -> Products).
     * Skipping the step (first_product_action=skip) bypasses this entirely,
     * per Functional Scope §2's "optional steps can be skipped".
     */
    private function validateFirstProductStep(): void
    {
        $name = trim((string)($this->request->post['product_name'] ?? ''));
        $price = trim((string)($this->request->post['price'] ?? ''));

        if ($name === '' || utf8_strlen($name) > 255) {
            $this->error['product_name'] = $this->language->get('error_product_name');
        }

        if ($price === '' || !is_numeric($price) || (float)$price < 0) {
            $this->error['price'] = $this->language->get('error_price');
        }
    }

    /**
     * Calls the real, unmodified ModelCatalogProduct::addProduct() (checked
     * against upload/admin/model/catalog/product.php before writing this,
     * CLAUDE.md §84/§10) - the exact same table Catalog -> Products writes
     * to, so the product this step creates is a completely ordinary
     * product, editable/deletable from that screen like any other
     * (CLAUDE.md §39: no Mint-owned duplicate of OpenCart's own catalog).
     *
     * Every field addProduct() requires but this step doesn't ask about
     * gets the same safe default a brand-new product would reasonably
     * start with: no model/SKU/barcodes, available today, shipped
     * (physical good - the common case), sold at this store (store_id 0),
     * zero weight/dimensions (unknown yet, not load-bearing for a test
     * order), and the store's own configured weight/length class
     * (config_weight_class_id/config_length_class_id - existing OpenCart
     * settings, not invented here, same reuse this controller's own
     * saveStoreStep()/saveLanguageCurrencyStep() already apply to 'config').
     * Status is enabled (1) so the product counts toward
     * MintCart\Onboarding\StoreReadiness's own 'first_product' signal
     * (ModelMintcartReadiness::getSignals(), filter_status => 1) and is
     * immediately visible on the storefront - the whole point of this step
     * is a store that can take a real test order right after finishing it.
     */
    private function saveFirstProductStep(): void
    {
        $this->load->model('catalog/product');
        $this->load->model('localisation/language');

        $name = trim((string)$this->request->post['product_name']);
        $description = trim((string)($this->request->post['description'] ?? ''));
        $price = (float)$this->request->post['price'];

        $quantity = trim((string)($this->request->post['quantity'] ?? ''));
        $quantity = ($quantity !== '' && is_numeric($quantity) && (int)$quantity >= 0) ? (int)$quantity : 100;

        $productDescription = [];

        foreach ($this->model_localisation_language->getLanguages() as $language) {
            $productDescription[$language['language_id']] = [
                'name'             => $name,
                'description'      => $description,
                'tag'              => '',
                'meta_title'       => $name,
                'meta_description' => '',
                'meta_keyword'     => '',
            ];
        }

        $this->model_catalog_product->addProduct([
            'model'               => '',
            'sku'                 => '',
            'upc'                 => '',
            'ean'                 => '',
            'jan'                 => '',
            'isbn'                => '',
            'mpn'                 => '',
            'location'            => '',
            'quantity'            => $quantity,
            'minimum'             => 1,
            'subtract'            => 1,
            'stock_status_id'     => $this->loadDefaultStockStatusId(),
            'date_available'      => date('Y-m-d'),
            'manufacturer_id'     => 0,
            'shipping'            => 1,
            'price'               => $price,
            'points'              => 0,
            'tax_class_id'        => 0,
            'weight'              => 0,
            'weight_class_id'     => (int)$this->config->get('config_weight_class_id'),
            'length'              => 0,
            'width'               => 0,
            'height'              => 0,
            'length_class_id'     => (int)$this->config->get('config_length_class_id'),
            'sort_order'          => 0,
            'status'              => 1,
            'product_description' => $productDescription,
            'product_store'       => [0],
        ]);
    }

    /**
     * The product table's stock_status_id has no NULL/zero "unset" option
     * that reads sensibly on a storefront, and there is no standard
     * OpenCart 'config_stock_status_id' setting to reuse (unlike
     * weight/length class) - Settings -> Option has no such default either.
     * Rather than inventing a new Mint-owned setting for a one-field
     * default, this picks whichever installed stock status is actually
     * named "In Stock" (the stock default data's own id 7, but never
     * hardcoded - a store that edited/reordered Localisation -> Stock
     * Statuses is trusted over the stock seed), falling back to the first
     * available row, then to 0 only if the table is somehow empty.
     */
    private function loadDefaultStockStatusId(): int
    {
        $this->load->model('localisation/stock_status');

        $statuses = $this->model_localisation_stock_status->getStockStatuses();

        foreach ($statuses as $status) {
            if (strcasecmp((string)$status['name'], 'In Stock') === 0) {
                return (int)$status['stock_status_id'];
            }
        }

        return isset($statuses[0]) ? (int)$statuses[0]['stock_status_id'] : 0;
    }

    private function render(?string $step, \MintCart\Onboarding\WizardProgress $progress): void
    {
        $data['heading_title'] = $this->language->get('heading_title');
        $data['error'] = $this->error;

        $data['asset_base'] = HTTP_CATALOG;
        $data['action'] = $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true);
        $data['dashboard'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
        $data['skip_wizard'] = $this->url->link('mintcart/wizard/skip', 'user_token=' . $this->session->data['user_token'], true);

        $notice = $this->session->data['mintcart_wizard_notice'] ?? ['type' => '', 'text' => ''];
        $data['notice'] = $notice['text'];
        $data['notice_type'] = $notice['type'];
        unset($this->session->data['mintcart_wizard_notice']);

        $data['step'] = $step;
        $data['is_ready'] = $step === null;

        $data['brand_logo'] = HTTP_SERVER . 'view/image/mintcart/logo.svg';

        // Step N of M, progress bar and the Back link (the step before this
        // one; always reachable, since steps are completed in order).
        $steps = $progress->steps();
        $index = $step === null ? count($steps) - 1 : (int)array_search($step, $steps, true);
        $completedCount = count(array_filter($steps, [$progress, 'isCompleted']));

        $data['step_number'] = $index + 1;
        $data['step_total'] = count($steps);
        $data['progress_percent'] = (int)round($completedCount / count($steps) * 100);
        $data['prev_step_url'] = ($step !== null && $index > 0)
            ? $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'] . '&step=' . $steps[$index - 1], true)
            : '';

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
            $data['current_logo'] = $this->config->get('config_logo') && is_file(DIR_IMAGE . $this->config->get('config_logo'))
                ? HTTP_CATALOG . 'image/' . $this->config->get('config_logo')
                : '';
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

            $data['config_language'] = $this->request->post['config_language'] ?? $this->request->get['added_language'] ?? $this->config->get('config_language');
            $data['config_currency'] = $this->request->post['config_currency'] ?? $this->request->get['added'] ?? $this->config->get('config_currency');
            $data['languages'] = $this->model_localisation_language->getLanguages();
            $data['currencies'] = $this->model_localisation_currency->getCurrencies();
            $data['add_language_action'] = $this->url->link('mintcart/wizard/addLanguage', 'user_token=' . $this->session->data['user_token'], true);
            $data['available_languages'] = array_map(function ($language) {
                return $language['name'] . ' (' . $language['code'] . ')';
            }, $this->availableLanguages());
            $data['add_currency_action'] = $this->url->link('mintcart/wizard/addCurrency', 'user_token=' . $this->session->data['user_token'], true);
            $data['available_currencies'] = $this->currencyCatalog()->available(array_column($data['currencies'], 'code'));
        }

        if ($step === 'delivery') {
            $data['shipping_extensions'] = $this->loadShippingExtensions();
            $data['toggle_delivery'] = $this->url->link('mintcart/wizard/toggleDelivery', 'user_token=' . $this->session->data['user_token'] . '&step=delivery', true);
            $data['manage_delivery'] = $this->url->link('mintcart/business/delivery', 'user_token=' . $this->session->data['user_token'], true);
        }

        if ($step === 'payment') {
            $data['payment_extensions'] = $this->loadPaymentExtensions();
            $data['toggle_payment'] = $this->url->link('mintcart/wizard/togglePayment', 'user_token=' . $this->session->data['user_token'] . '&step=payment', true);
            $data['manage_payment'] = $this->url->link('mintcart/business/payments', 'user_token=' . $this->session->data['user_token'], true);
        }

        if ($step === 'design') {
            $data['presets'] = [];

            foreach ($this->availablePresets() as $code) {
                $data['presets'][] = ['code' => $code, 'label' => $this->language->get('preset_' . $code) !== 'preset_' . $code ? $this->language->get('preset_' . $code) : ucfirst($code)];
            }

            $data['selected_preset'] = $this->request->post['mintcart_design_preset'] ?? ((string)$this->config->get('mintcart_design_preset') ?: ($data['presets'][0]['code'] ?? ''));
            $data['store_preview'] = HTTP_CATALOG;
        }

        if ($step === 'first_product') {
            $data['product_name'] = $this->request->post['product_name'] ?? '';
            $data['description'] = $this->request->post['description'] ?? '';
            $data['price'] = $this->request->post['price'] ?? '';
            $data['quantity'] = $this->request->post['quantity'] ?? '';
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
    public function addCurrency(): void
    {
        $this->load->language('mintcart/wizard');
        $this->load->model('localisation/currency');

        $back = $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'] . '&step=language_currency', true);
        $code = trim((string)($this->request->post['add_currency_code'] ?? ''));

        // The field is a searchable list: the value is "Title (CODE)" when picked, or a bare code when typed.
        if (preg_match('/\(([A-Za-z]{3})\)\s*$/', $code, $match)) {
            $code = $match[1];
        }

        $currency = $this->currencyCatalog()->find($code);

        if (!$currency) {
            $this->session->data['mintcart_wizard_notice'] = ['type' => 'warning', 'text' => $this->language->get('text_currency_unknown')];
        }

        if ($currency && $this->user->hasPermission('modify', 'mintcart/wizard') && !$this->model_localisation_currency->getCurrencyByCode($currency['code'])) {
            $base = (string)$this->config->get('config_currency');
            $rate = null;

            foreach (['fetchEcbRates', 'fetchOpenRates'] as $source) {
                $rates = \MintCart\Onboarding\CurrencyCatalog::$source();
                $rate = $rates ? \MintCart\Onboarding\CurrencyCatalog::rate($rates, $currency['code'], $base) : null;

                if ($rate !== null) {
                    break;
                }
            }

            $currency['value'] = $rate !== null ? round($rate, 8) : 1;
            $currency['status'] = 1;

            $this->model_localisation_currency->addCurrency($currency);

            $this->session->data['mintcart_wizard_notice'] = $rate === null
                ? ['type' => 'warning', 'text' => sprintf($this->language->get('text_currency_rate_manual'), $currency['code'])]
                : ['type' => 'success', 'text' => sprintf($this->language->get('text_currency_added'), $currency['title'], $currency['code'])];

            $back .= '&added=' . $currency['code'];
        }

        $this->response->redirect($back);
    }

    /**
     * Bundled language packs (`system/mintcart/data/languages.json`) that are
     * on disk for both the storefront and the admin but not installed yet.
     *
     * @return array<string, array{code: string, name: string, locale: string}>
     */
    private function availableLanguages(): array
    {
        $this->load->model('localisation/language');

        $installed = array_column($this->model_localisation_language->getLanguages(), 'code');
        $catalog = json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/languages.json'), true) ?: [];
        $available = [];

        foreach ($catalog as $code => $language) {
            if (!in_array($code, $installed, true) && is_dir(DIR_LANGUAGE . $code) && is_dir(DIR_CATALOG . 'language/' . $code)) {
                $available[$code] = ['code' => $code, 'name' => $language['name'], 'locale' => $language['locale']];
            }
        }

        return $available;
    }

    public function addLanguage(): void
    {
        $this->load->language('mintcart/wizard');
        $this->load->model('localisation/language');

        $back = $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'] . '&step=language_currency', true);
        $code = trim((string)($this->request->post['add_language_code'] ?? ''));

        if (preg_match('/\(([a-z]{2}-[a-z]{2})\)\s*$/i', $code, $match)) {
            $code = strtolower($match[1]);
        }

        $available = $this->availableLanguages();

        if (isset($available[$code]) && $this->user->hasPermission('modify', 'mintcart/wizard')) {
            $language = $available[$code];

            $this->model_localisation_language->addLanguage([
                'name'       => $language['name'],
                'code'       => $language['code'],
                'locale'     => $language['locale'],
                'sort_order' => count($this->model_localisation_language->getLanguages()) + 1,
                'status'     => 1,
            ]);

            $this->session->data['mintcart_wizard_notice'] = ['type' => 'success', 'text' => sprintf($this->language->get('text_language_added'), $language['name'])];
            $back .= '&added_language=' . $language['code'];
        } else {
            $this->session->data['mintcart_wizard_notice'] = ['type' => 'warning', 'text' => $this->language->get('text_language_unknown')];
        }

        $this->response->redirect($back);
    }

    private function currencyCatalog(): \MintCart\Onboarding\CurrencyCatalog
    {
        return \MintCart\Onboarding\CurrencyCatalog::fromFile(DIR_SYSTEM . 'mintcart/data/currencies.json');
    }

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
     * route=mintcart/wizard/togglePayment - POST code. Same shape as
     * toggleDelivery() above for the 'payment' extension type instead of
     * 'shipping' - see that method's docblock for the full rationale
     * (duplicated rather than shared, matching
     * docs/developer/onboarding/business-delivery-payments.md's own call
     * for Business -> Delivery vs. Business -> Payments).
     */
    public function togglePayment(): void
    {
        $this->load->language('mintcart/wizard');
        $this->load->model('setting/extension');
        $this->load->model('setting/setting');

        $code = isset($this->request->post['code']) ? (string)$this->request->post['code'] : '';

        if ($code !== '' && in_array($code, $this->model_setting_extension->getInstalled('payment'), true)) {
            // Merge-then-save - see toggleDelivery()'s identical comment for
            // why a bare editSetting('payment_<code>', [...]) here would
            // silently wipe the extension's other settings.
            $existing = $this->model_setting_setting->getSetting('payment_' . $code);

            $existing['payment_' . $code . '_status'] = empty($existing['payment_' . $code . '_status']) ? '1' : '0';

            $this->model_setting_setting->editSetting('payment_' . $code, $existing);
        }

        $this->response->redirect($this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'] . '&step=payment', true));
    }

    /**
     * Read-only list of installed payment extensions for the Payment step -
     * same shape as loadShippingExtensions() below for the 'payment'
     * extension type instead of 'shipping'.
     *
     * @return array<int, array{code: string, name: string, enabled: bool}>
     */
    private function loadPaymentExtensions(): array
    {
        $this->load->model('setting/extension');

        $rows = [];

        foreach ($this->model_setting_extension->getInstalled('payment') as $code) {
            if (!is_file(DIR_APPLICATION . 'controller/extension/payment/' . $code . '.php')) {
                continue;
            }

            $this->load->language('extension/payment/' . $code, 'extension');

            $rows[] = [
                'code' => $code,
                'name' => $this->language->get('extension')->get('heading_title'),
                'enabled' => (bool)$this->config->get('payment_' . $code . '_status'),
            ];
        }

        return $rows;
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
