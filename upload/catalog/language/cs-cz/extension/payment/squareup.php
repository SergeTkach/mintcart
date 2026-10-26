<?php
// Text
$_['text_new_card']                     = '+ Přidat novou kartu';
$_['text_loading']                      = 'Načítání... Čekejte prosím...';
$_['text_card_details']                 = 'Detaily karty';
$_['text_saved_card']                   = 'Použít uloženou kartu:';
$_['text_card_ends_in']                 = 'Zaplatit existující %s kartou končící na XXXX XXXX XXXX %s';
$_['text_card_number']                  = 'Číslo karty:';
$_['text_card_expiry']                  = 'Platnost karty (MM/RR):';
$_['text_card_cvc']                     = 'Bezpečnostní kód karty (CVC):';
$_['text_card_zip']                     = 'PSČ karty:';
$_['text_card_save']                    = 'Uložit kartu pro budoucí použití?';
$_['text_trial']                        = '%s každých %s %s za %s plateb pak ';
$_['text_recurring']                    = '%s každých %s %s';
$_['text_length']                       = ' za %s plateb';
$_['text_cron_subject']                 = 'Souhrn CRON úlohy Square';
$_['text_cron_message']                 = 'Zde je seznam všech CRON úloh provedených vaším rozšířením Square:';
$_['text_squareup_profile_suspended']   = ' Vaše opakované platby byly pozastaveny. Pro více podrobností nás kontaktujte.';
$_['text_squareup_trial_expired']       = ' Vaše zkušební doba vypršela.';
$_['text_squareup_recurring_expired']   = ' Vaše opakované platby vypršely. Toto byla vaše poslední platba.';
$_['text_cron_summary_token_heading']   = 'Obnovení přístupového tokenu:';
$_['text_cron_summary_token_updated']   = 'Přístupový token byl úspěšně aktualizován!';
$_['text_cron_summary_error_heading']   = 'Chyby transakcí:';
$_['text_cron_summary_fail_heading']    = 'Selhané transakce (pozvastavené profily):';
$_['text_cron_summary_success_heading'] = 'Úspěšné transakce:';
$_['text_cron_fail_charge']             = 'Profil <strong>#%s</strong> nebylo možné zatížit částkou <strong>%s</strong>';
$_['text_cron_success_charge']          = 'Profil <strong>#%s</strong> byl zatížen částkou <strong>%s</strong>';
$_['text_card_placeholder']             = 'XXXX XXXX XXXX XXXX';
$_['text_cvv']                          = 'CVV';
$_['text_expiry']                       = 'MM/RR';
$_['text_default_squareup_name']        = 'Kreditní / Debetní karta';
$_['text_token_issue_customer_error']   = 'V našem platebním systému dochází k technickému výpadku. Zkuste to prosím později.';
$_['text_token_revoked_subject']        = 'Váš přístupový token Square byl odvolán!';
$_['text_token_revoked_message']        = "The Square payment extension's access to your Square account has been revoked through the Square Dashboard. You need to verify your application credentials in the extension settings and connect again.";
$_['text_token_expired_subject']        = 'Platnost vašeho přístupového tokenu Square vypršela!';
$_['text_token_expired_message']        = "The Square payment extension's access token connecting it to your Square account has expired. You need to verify your application credentials and CRON job in the extension settings and connect again.";

// Error
$_['error_browser_not_supported']       = 'Chyba: Platební systém již nepodporuje váš webový prohlížeč. Aktualizujte prosím nebo použijte jiný.';
$_['error_card_invalid']                = 'Chyba: Karta je neplatná!';
$_['error_squareup_cron_token']         = 'Chyba: Přístupový token nelze obnovit. Připojte prosím své rozšíření Square platby přes administrační panel OpenCart.';

// Warning
$_['warning_test_mode']                 = 'Varování: Sandbox režim je povolen! Transakce se budou tvářit jako provedené, ale žádné platby nebudou provedeny.';

// Statuses
$_['squareup_status_comment_authorized']    = 'Transakce kartou byla autorizována, ale dosud nebyla zachycena.';
$_['squareup_status_comment_captured']      = 'Transakce kartou byla autorizována a následně zachycena (tj. dokončena).';
$_['squareup_status_comment_voided']        = 'Transakce kartou byla autorizována a následně stornována (tj. zrušena).';
$_['squareup_status_comment_failed']        = 'Transakce kartou selhala.';

// Override errors
$_['squareup_override_error_billing_address.country']       = 'Země platební adresy není platná. Upravte ji a zkuste to znovu.';
$_['squareup_override_error_shipping_address.country']      = 'Země dodací adresy není platná. Upravte ji a zkuste to znovu.';
$_['squareup_override_error_email_address']                 = 'Vaše e-mailová adresa zákazníka není platná. Upravte ji a zkuste to znovu.';
$_['squareup_override_error_phone_number']                  = 'Vaše telefonní číslo zákazníka není platné. Upravte je a zkuste to znovu.';
$_['squareup_error_field']                                  = ' Pole: %s';
