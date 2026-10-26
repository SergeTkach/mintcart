<?php
// Heading
$_['heading_title']                 = 'CardConnect';

// Tab
$_['tab_settings']                  = 'Nastavení';
$_['tab_order_status']              = 'Stav objednávky';

// Text
$_['text_extension']                = 'Rozšíření';
$_['text_success']                  = 'Úspěch: Upravili jste platební modul CardConnect!';
$_['text_edit']                     = 'Upravit CardConnect';
$_['text_cardconnect']              = '<a href="http://www.cardconnect.com" target="_blank"><img src="view/image/payment/cardconnect.png" alt="CardConnect" title="CardConnect"></a>';
$_['text_payment']                  = 'Platba';
$_['text_authorize']                = 'Autorizovat';
$_['text_live']                     = 'Živý';
$_['text_test']                     = 'Test';
$_['text_no_cron_time']             = 'Cron dosud nebyl spuštěn';
$_['text_payment_info']             = 'Informace o platbě';
$_['text_payment_method']           = 'Platební metoda';
$_['text_card']                     = 'Karta';
$_['text_echeck']                   = 'eCheck';
$_['text_reference']                = 'Odkaz';
$_['text_update']                   = 'Aktualizovat';
$_['text_order_total']              = 'Celkem objednávky';
$_['text_total_captured']           = 'Celkem zachyceno';
$_['text_capture_payment']          = 'Zachytit platbu';
$_['text_refund_payment']           = 'Vrátit platbu';
$_['text_void']                     = 'Stornovat';
$_['text_transactions']             = 'Transakce';
$_['text_column_type']              = 'Typ';
$_['text_column_reference']         = 'Odkaz';
$_['text_column_amount']            = 'Částka';
$_['text_column_status']            = 'Stav';
$_['text_column_date_modified']     = 'Datum úpravy';
$_['text_column_date_added']        = 'Datum přidání';
$_['text_column_update']            = 'Aktualizovat';
$_['text_column_void']              = 'Stornovat';
$_['text_confirm_capture']          = 'Opravdu chcete zachytit platbu?';
$_['text_confirm_refund']           = 'Opravdu chcete vrátit platbu?';
$_['text_inquire_success']          = 'Dotaz byl úspěšný';
$_['text_capture_success']          = 'Požadavek na zachycení byl úspěšný';
$_['text_refund_success']           = 'Požadavek na vrácení byl úspěšný';
$_['text_void_success']             = 'Požadavek na stornování byl úspěšný';

// Entry
$_['entry_merchant_id']             = 'ID obchodníka';
$_['entry_api_username']            = 'Uživatelské jméno API';
$_['entry_api_password']            = 'Heslo API';
$_['entry_token']                   = 'Tajný token';
$_['entry_transaction']             = 'Transakce';
$_['entry_environment']             = 'Prostředí';
$_['entry_site']                    = 'Web';
$_['entry_store_cards']             = 'Ukládat karty';
$_['entry_echeck']                  = 'eCheck';
$_['entry_total']                   = 'Celkem';
$_['entry_geo_zone']                = 'Geo zóna';
$_['entry_status']                  = 'Stav';
$_['entry_logging']                 = 'Ladící protokolování';
$_['entry_sort_order']              = 'Pořadí';
$_['entry_cron_url']                = 'URL Cron úlohy';
$_['entry_cron_time']               = 'Poslední spuštění Cron úlohy';
$_['entry_order_status_pending']    = 'Čekající';
$_['entry_order_status_processing'] = 'Zpracování';

// Help
$_['help_merchant_id']              = 'Vaše osobní ID obchodníka účtu CardConnect.';
$_['help_api_username']             = 'Vaše uživatelské jméno pro přístup k API CardConnect.';
$_['help_api_password']             = 'Vaše heslo pro přístup k API CardConnect.';
$_['help_token']                    = 'Zadejte náhodný token pro zabezpečení nebo použijte vygenerovaný.';
$_['help_transaction']              = 'Vyberte \'Platba\' pro okamžité zachycení platby nebo \'Autorizace\' pro schválení.';
$_['help_site']                     = 'Toto určuje první část URL API. Měňte pouze na základě pokynů.';
$_['help_store_cards']              = 'Zda chcete ukládat karty pomocí tokenizace.';
$_['help_echeck']                   = 'Zda chcete nabídnout možnost platby pomocí eCheck.';
$_['help_total']                    = 'Celková částka objednávky, které musí objednávka dosáhnout, než se tato platební metoda stane aktivní. Musí být hodnotou bez symbolu měny.';
$_['help_logging']                  = 'Povolení ladění zapíše citlivá data do protokolu. Vždy byste měli zakázat, pokud vám nebylo řečeno jinak.';
$_['help_cron_url']                 = 'Nastavte cron úlohu pro volání této URL, aby byly objednávky automaticky aktualizovány. Je navrženo tak, aby běželo několik hodin po posledním zachycení pracovního dne.';
$_['help_cron_time']                = 'Toto je poslední čas, kdy byla spuštěna URL cron úlohy.';
$_['help_order_status_pending']     = 'Stav objednávky, když musí být objednávka autorizována obchodníkem.';
$_['help_order_status_processing']  = 'Stav objednávky, když je objednávka automaticky zachycena.';

// Button
$_['button_inquire_all']            = 'Dotaz na vše';
$_['button_capture']                = 'Zachytit';
$_['button_refund']                 = 'Vrátit';
$_['button_void_all']               = 'Stornovat vše';
$_['button_inquire']                = 'Dotaz';
$_['button_void']                   = 'Stornovat';

// Error
$_['error_permission']              = 'Varování: Nemáte oprávnění upravovat platbu CardConnect!';
$_['error_merchant_id']             = 'ID obchodníka je povinné!';
$_['error_api_username']            = 'Uživatelské jméno API je povinné!';
$_['error_api_password']            = 'Heslo API je povinné!';
$_['error_token']                   = 'Tajný token je povinný!';
$_['error_site']                    = 'Web je povinný!';
$_['error_amount_zero']             = 'Částka musí být vyšší než nula!';
$_['error_no_order']                = 'Nebyly nalezeny žádné odpovídající informace o objednávce!';
$_['error_invalid_response']        = 'Obdržena neplatná odpověď!';
$_['error_data_missing']            = 'Chybí data!';
$_['error_not_enabled']             = 'Modul není povolen!';
