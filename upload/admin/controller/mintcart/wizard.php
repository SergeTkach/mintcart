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

        if ($step === 'ready') {
            $data['readiness'] = $this->loadReadiness();
        }

        $this->response->setOutput($this->load->view('mintcart/wizard', $data));
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
