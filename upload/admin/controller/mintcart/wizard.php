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
 * (user_token) is still required, same as every other admin route.
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
     * well-formed URL, though - this is what gets rendered as an `href` on
     * the live storefront footer.
     */
    private function validateContactsStep(): void
    {
        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = trim((string)($this->request->post['social_' . $network] ?? ''));

            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
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

        $this->response->setOutput($this->load->view('mintcart/wizard', $data));
    }
}
