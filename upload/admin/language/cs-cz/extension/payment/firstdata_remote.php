<?php
// Heading
$_['heading_title']					 = 'First Data EMEA Web Service API';

// Text
$_['text_firstdata_remote']			 = '<img src="view/image/payment/firstdata.png" alt="First Data" title="First Data" style="border: 1px solid #EEEEEE;" />';
$_['text_extension']					 = 'Rozšíření';
$_['text_success']					 = 'Úspěch: Upravili jste údaje účtu First Data!';
$_['text_edit']                      = 'Upravit First Data EMEA Web Service API';
$_['text_card_type']				 = 'Typ karty';
$_['text_enabled']					 = 'Povoleno';
$_['text_merchant_id']				 = 'ID obchodu';
$_['text_subaccount']				 = 'Podúčet';
$_['text_user_id']					 = 'ID uživatele';
$_['text_capture_ok']				 = 'Zachycení bylo úspěšné';
$_['text_capture_ok_order']			 = 'Zachycení bylo úspěšné, stav objednávky aktualizován na úspěch - vyrovnáno';
$_['text_refund_ok']				 = 'Vrácení bylo úspěšné';
$_['text_refund_ok_order']			 = 'Vrácení bylo úspěšné, stav objednávky aktualizován na vráceno';
$_['text_void_ok']					 = 'Stornování bylo úspěšné, stav objednávky aktualizován na stornováno';
$_['text_settle_auto']				 = 'Prodej';
$_['text_settle_delayed']			 = 'Předautorizace';
$_['text_mastercard']				 = 'Mastercard';
$_['text_visa']						 = 'Visa';
$_['text_diners']					 = 'Diners';
$_['text_amex']						 = 'American Express';
$_['text_maestro']					 = 'Maestro';
$_['text_payment_info']				 = 'Informace o platbě';
$_['text_capture_status']			 = 'Platba zachycena';
$_['text_void_status']				 = 'Platba stornována';
$_['text_refund_status']			 = 'Platba vrácena';
$_['text_order_ref']				 = 'Odkaz objednávky';
$_['text_order_total']				 = 'Celkem autorizováno';
$_['text_total_captured']			 = 'Celkem zachyceno';
$_['text_transactions']				 = 'Transakce';
$_['text_column_amount']			 = 'Částka';
$_['text_column_type']				 = 'Typ';
$_['text_column_date_added']		 = 'Vytvořeno';
$_['text_confirm_void']				 = 'Opravdu chcete stornovat platbu?';
$_['text_confirm_capture']			 = 'Opravdu chcete zachytit platbu?';
$_['text_confirm_refund']			 = 'Opravdu chcete vrátit platbu?';

// Entry
$_['entry_certificate_path']		 = 'Cesta k certifikátu';
$_['entry_certificate_key_path']	 = 'Cesta k soukromému klíči';
$_['entry_certificate_key_pw']		 = 'Heslo soukromého klíče';
$_['entry_certificate_ca_path']		 = 'Cesta CA';
$_['entry_merchant_id']				 = 'ID obchodu';
$_['entry_user_id']					 = 'ID uživatele';
$_['entry_password']				 = 'Heslo';
$_['entry_total']					 = 'Celkem';
$_['entry_sort_order']				 = 'Pořadí';
$_['entry_geo_zone']				 = 'Geo zóna';
$_['entry_status']					 = 'Stav';
$_['entry_debug']					 = 'Ladící protokolování';
$_['entry_auto_settle']				 = 'Typ vyrovnání';
$_['entry_status_success_settled']	 = 'Úspěch - vyrovnáno';
$_['entry_status_success_unsettled'] = 'Úspěch - nevyrovnáno';
$_['entry_status_decline']			 = 'Zamítnuto';
$_['entry_status_void']				 = 'Stornováno';
$_['entry_status_refund']			 = 'Vráceno';
$_['entry_enable_card_store']		 = 'Povolit ukládání tokenů karet';
$_['entry_cards_accepted']			 = 'Přijímané typy karet';

// Help
$_['help_total']					 = 'Celková částka objednávky, které musí objednávka dosáhnout, než se tato platební metoda stane aktivní';
$_['help_certificate']				 = 'Certifikáty a soukromé klíče by měly být uloženy mimo vaše veřejné webové složky';
$_['help_card_select']				 = 'Požádejte uživatele, aby si vybral typ karty před přesměrováním';
$_['help_notification']				 = 'Tuto URL musíte poskytnout First Data pro příjem platebních oznámení';
$_['help_debug']					 = 'Povolení ladění zapíše citlivá data do protokolu. Vždy byste měli zakázat, pokud vám nebylo řečeno jinak .';
$_['help_settle']					 = 'Pokud používáte předautorizaci, musíte dokončit akci po autorizaci do 3-5 dnů, jinak bude vaše transakce zrušena';

// Tab
$_['tab_account']					 = 'API info';
$_['tab_order_status']				 = 'Stav objednávky';
$_['tab_payment']					 = 'Nastavení platby';

// Button
$_['button_capture']				 = 'Zachytit';
$_['button_refund']					 = 'Vrátit';
$_['button_void']					 = 'Stornovat';

// Error
$_['error_merchant_id']				 = 'ID obchodu je povinné';
$_['error_user_id']					 = 'ID uživatele je povinné';
$_['error_password']				 = 'Heslo je povinné';
$_['error_certificate']				 = 'Cesta k certifikátu je povinná';
$_['error_key']						 = 'Klíč certifikátu je povinný';
$_['error_key_pw']					 = 'Heslo klíče certifikátu je povinné';
$_['error_ca']						 = 'Cesta k certifikační autoritě (CA) je povinná';
