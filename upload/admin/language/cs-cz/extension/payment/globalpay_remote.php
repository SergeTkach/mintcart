<?php
// Heading
$_['heading_title']					 = 'Globalpay Remote';

// Text
$_['text_extension']				 = 'Rozšíření';
$_['text_success']					 = 'Úspěch: Upravili jste údaje účtu Globalpay!';
$_['text_edit']                      = 'Upravit Globalpay Remote';
$_['text_card_type']				 = 'Typ karty';
$_['text_enabled']					 = 'Povoleno';
$_['text_use_default']				 = 'Použít výchozí';
$_['text_merchant_id']				 = 'ID obchodníka';
$_['text_subaccount']				 = 'Podúčet';
$_['text_secret']					 = 'Sdílené tajemství';
$_['text_card_visa']				 = 'Visa';
$_['text_card_master']				 = 'Mastercard';
$_['text_card_amex']				 = 'American Express';
$_['text_card_switch']				 = 'Switch/Maestro';
$_['text_card_laser']				 = 'Laser';
$_['text_card_diners']				 = 'Diners';
$_['text_capture_ok']				 = 'Zachycení bylo úspěšné';
$_['text_capture_ok_order']			 = 'Zachycení bylo úspěšné, stav objednávky aktualizován na úspěch - vyrovnáno';
$_['text_rebate_ok']				 = 'Sleva byla úspěšná';
$_['text_rebate_ok_order']			 = 'Sleva byla úspěšná, stav objednávky aktualizován na vráceno';
$_['text_void_ok']					 = 'Stornování bylo úspěšné, stav objednávky aktualizován na stornováno';
$_['text_settle_auto']				 = 'Automaticky';
$_['text_settle_delayed']			 = 'Zpožděně';
$_['text_settle_multi']				 = 'Více';
$_['text_ip_message']				 = 'Před spuštěním do provozu musíte poskytnout IP adresu svého serveru správci účtu Globalpay';
$_['text_payment_info']				 = 'Informace o platbě';
$_['text_capture_status']			 = 'Platba zachycena';
$_['text_void_status']				 = 'Platba stornována';
$_['text_rebate_status']			 = 'Platba vrácena';
$_['text_order_ref']				 = 'Odkaz objednávky';
$_['text_order_total']				 = 'Celkem autorizováno';
$_['text_total_captured']			 = 'Celkem zachyceno';
$_['text_transactions']				 = 'Transakce';
$_['text_confirm_void']				 = 'Opravdu chcete stornovat platbu?';
$_['text_confirm_capture']			 = 'Opravdu chcete zachytit platbu?';
$_['text_confirm_rebate']			 = 'Opravdu chcete vrátit platbu?';
$_['text_globalpay_remote']			 = '<a target="_blank" href="https://resourcecentre.globaliris.com/getting-started.php?id=OpenCart"><img src="view/image/payment/globalpay.png" alt="Globalpay" title="Globalpay" style="border: 1px solid #EEEEEE;" /></a>';

// Column
$_['text_column_amount']			 = 'Částka';
$_['text_column_type']				 = 'Typ';
$_['text_column_date_added']		 = 'Vytvořeno';

// Entry
$_['entry_merchant_id']				 = 'ID obchodníka';
$_['entry_secret']					 = 'Sdílené tajemství';
$_['entry_rebate_password']			 = 'Heslo pro slevu';
$_['entry_total']					 = 'Celkem';
$_['entry_sort_order']				 = 'Pořadí';
$_['entry_geo_zone']				 = 'Geo zóna';
$_['entry_status']					 = 'Stav';
$_['entry_debug']					 = 'Ladící protokolování';
$_['entry_auto_settle']				 = 'Typ vyrovnání';
$_['entry_tss_check']				 = 'Kontroly TSS';
$_['entry_card_data_status']		 = 'Protokolování informací o kartě';
$_['entry_3d']						 = 'Povolit 3D secure';
$_['entry_liability_shift']			 = 'Přijmout scénáře bez přenesení odpovědnosti';
$_['entry_status_success_settled']	 = 'Úspěch - vyrovnáno';
$_['entry_status_success_unsettled'] = 'Úspěch - nevyrovnáno';
$_['entry_status_decline']			 = 'Zamítnuto';
$_['entry_status_decline_pending']	 = 'Zamítnuto - offline autorizace';
$_['entry_status_decline_stolen']	 = 'Zamítnuto - ztracená nebo odcizená karta';
$_['entry_status_decline_bank']		 = 'Zamítnuto - chyba banky';
$_['entry_status_void']				 = 'Stornováno';
$_['entry_status_rebate']			 = 'Vráceno';

// Help
$_['help_total']					 = 'Celková částka objednávky, které musí objednávka dosáhnout, než se tato platební metoda stane aktivní';
$_['help_card_select']				 = 'Požádejte uživatele, aby si vybral typ karty před přesměrováním';
$_['help_notification']				 = 'Tuto URL musíte poskytnout Globalpay pro příjem platebních oznámení';
$_['help_debug']					 = 'Povolení ladění zapíše citlivá data do protokolu. Vždy byste měli zakázat, pokud vám nebylo řečeno jinak.';
$_['help_liability']				 = 'Přijetí odpovědnosti znamená, že budete stále přijímat platby, když uživatel selže v 3D secure.';
$_['help_card_data_status']			 = 'Protokoluje poslední 4 číslice karty, datum vypršení platnosti, název, typ a informace o vydávající bance';

// Tab
$_['tab_api']					     = 'Detaily API';
$_['tab_account']				     = 'Účty';
$_['tab_order_status']				 = 'Stav objednávky';
$_['tab_payment']					 = 'Nastavení platby';

// Button
$_['button_capture']				 = 'Zachytit';
$_['button_rebate']					 = 'Sleva / vrácení';
$_['button_void']					 = 'Stornovat';

// Error
$_['error_merchant_id']				 = 'ID obchodníka je povinné';
$_['error_secret']					 = 'Sdílené tajemství je povinné';
