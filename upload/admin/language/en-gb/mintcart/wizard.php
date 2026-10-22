<?php
// Heading

$_['heading_title'] = 'MintCart Setup';

// Step nav (docs/developer/onboarding/setup-wizard.md - step keys match
// MintCart\Onboarding\WizardProgress::STEPS)

$_['step_welcome']            = 'Welcome';
$_['step_store']               = 'Store';
$_['step_contacts']            = 'Contacts';
$_['step_language_currency']   = 'Language & currency';
$_['step_delivery']            = 'Delivery';
$_['step_payment']             = 'Payment';
$_['step_design']              = 'Design';
$_['step_first_product']       = 'First product';
$_['step_ready']                = 'Ready';

// Welcome step

$_['welcome_title']    = 'Welcome to MintCart';
$_['welcome_text']     = 'Let\'s get your store ready to take its first order. This will only take a few minutes, and you can always come back and finish later.';
$_['button_get_started'] = 'Get started';
$_['button_skip_wizard'] = 'Skip for now';
$_['button_back']        = 'Back';
$_['text_steps']         = 'Setup steps';
$_['text_step_of']       = 'Step %s of %s';
$_['text_progress']      = '%s%% done';

// Store step

$_['store_title']       = 'Tell us about your store';
$_['store_text']        = 'This information appears on your storefront, invoices and order emails. You can change it any time in Settings.';
$_['entry_name']        = 'Store name';
$_['entry_email']       = 'Store email';
$_['entry_telephone']   = 'Telephone';
$_['entry_address']     = 'Address';
$_['button_continue']   = 'Continue';

// Contacts step

$_['contacts_title']          = 'Where can shoppers find you online?';
$_['contacts_text']           = 'Add links to your store\'s social profiles. They\'ll appear in your storefront footer. This step is optional — leave any field blank and add it later.';
$_['entry_social_facebook']   = 'Facebook';
$_['entry_social_instagram']  = 'Instagram';
$_['entry_social_twitter']    = 'Twitter / X';
$_['entry_social_youtube']    = 'YouTube';

// Language & currency step

$_['language_currency_title'] = 'Language & currency';
$_['language_currency_text']  = 'Choose the default language and currency shoppers see on your storefront. You can add more of either later in Settings.';
$_['entry_language']          = 'Default language';
$_['entry_currency']          = 'Default currency';

// Delivery step

$_['delivery_title']         = 'How will you ship orders?';
$_['delivery_text']          = 'Enable the shipping methods you plan to offer. You can configure each one\'s cost and settings any time from Business > Delivery. This step is optional — skip it for now and come back later.';
$_['text_delivery_empty']    = 'No shipping methods are installed yet.';
$_['warning_delivery_none']  = 'No delivery method is enabled, so customers cannot check out products that need shipping. Enable at least one.';
$_['text_enabled']           = 'Enabled';
$_['text_disabled']          = 'Disabled';
$_['button_enable']          = 'Enable';
$_['button_disable']         = 'Disable';
$_['button_manage_delivery'] = 'Manage delivery methods';

// Payment step

$_['payment_title']         = 'How will you get paid?';
$_['payment_text']          = 'Enable the payment methods you plan to offer. You can configure each one\'s settings any time from Business > Payments. This step is optional — skip it for now and come back later.';
$_['text_payment_empty']    = 'No payment methods are installed yet.';
$_['warning_payment_none']  = 'No payment method is enabled, so your store cannot take orders. Enable at least one - Cash On Delivery is the simplest to start with.';
$_['button_manage_payment'] = 'Manage payment methods';

// First product step

$_['first_product_title']  = 'Add your first product';
$_['first_product_text']   = 'A store needs at least one product to take an order. Fill in just enough to get started — you can add images, options and everything else from Catalog > Products any time. This step is optional — skip it for now and come back later.';
$_['entry_product_name']   = 'Product name';
$_['entry_price']          = 'Price';
$_['entry_quantity']       = 'Quantity';
$_['entry_description']    = 'Description';
$_['button_add_product']   = 'Add product';
$_['button_skip']          = 'Skip for now';

// Ready step (Store Readiness checklist)

$_['readiness_title']       = 'How ready is your store?';
$_['readiness_text']        = 'Here\'s where things stand today, based on your real store settings. You can finish setup and come back to this checklist any time.';
$_['text_check_done']       = 'Done';
$_['text_check_pending']    = 'Not yet';
$_['button_finish_setup']   = 'Finish setup';

// Ready / more-steps-coming state (shown once every implemented step, ready included, is completed)

$_['ready_title'] = 'You\'re off to a great start';
$_['ready_text']  = 'Your store is set up. Anything still open is listed above - you can change every setting later in the admin.';

// Store: logo
$_['entry_logo'] = 'Logo (optional)';
$_['help_logo']  = 'SVG, PNG, JPG, GIF or WebP, up to 2 MB. Leave empty to keep the current one.';
$_['error_logo'] = 'The logo must be an SVG, PNG, JPG, GIF or WebP image up to 2 MB. SVG files with scripts, event handlers or external links are not accepted.';

// Design
$_['design_title']      = 'Choose a look';
$_['design_text']       = 'Pick a style for your storefront. It changes colours, fonts and shapes - you can switch it any time under Design -> Styles.';
$_['design_no_presets'] = 'Your current theme has no styles to choose from. Continue to the next step.';
$_['design_preview']    = 'Open the store in a new tab';
$_['preset_clean']      = 'Clean - cool, minimal';
$_['preset_warm']       = 'Warm - soft, friendly';
$_['preset_gallery']    = 'Gallery - white, image-first, square';
$_['error_preset']      = 'Please choose one of the listed styles.';
$_['button_go_to_dashboard'] = 'Go to dashboard';

// Errors

$_['error_warning']   = 'Please correct the highlighted fields and try again.';
$_['error_name']      = 'Store name must be between 1 and 64 characters!';
$_['error_email']     = 'E-Mail Address does not appear to be valid!';
$_['error_telephone'] = 'Telephone must be between 3 and 32 characters!';
$_['error_address']   = 'Address must be between 3 and 256 characters!';
$_['error_social']    = '%s must be a valid URL!';
$_['error_language']  = 'Please choose a valid language!';
$_['error_currency']  = 'Please choose a valid currency!';
$_['error_product_name'] = 'Product name must be between 1 and 255 characters!';
$_['error_price']     = 'Price must be a valid, non-negative number!';
