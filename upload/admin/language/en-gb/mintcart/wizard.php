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

// Ready step (Store Readiness checklist)

$_['readiness_title']       = 'How ready is your store?';
$_['readiness_text']        = 'Here\'s where things stand today, based on your real store settings. You can finish setup and come back to this checklist any time.';
$_['text_check_done']       = 'Done';
$_['text_check_pending']    = 'Not yet';
$_['button_finish_setup']   = 'Finish setup';

// Ready / more-steps-coming state (shown once every implemented step, ready included, is completed)

$_['ready_title'] = 'You\'re off to a great start';
$_['ready_text']  = 'The rest of the Setup Wizard (delivery, payment, design and your first product) is coming in a future update. For now, head to your dashboard to keep setting up your store.';
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
