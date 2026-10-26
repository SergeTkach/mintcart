<?php
// Heading
$_['heading_title']				= 'eWAY platba';

// Text
$_['text_extension']			= 'Rozšíření';
$_['text_success']				= 'Úspěch: Upravili jste své eWAY údaje!';
$_['text_edit']					= 'Upravit eWAY';
$_['text_eway']					= '<a target="_BLANK" href="http://www.eway.com.au/"><img src="view/image/payment/eway.png" alt="eWAY" title="eWAY" style="border: 1px solid #EEEEEE;" /></a>';
$_['text_authorisation']		= 'Autorizace';
$_['text_sale']					= 'Prodej';
$_['text_transparent']			= 'Transparentní přesměrování (platební formulář na webu)';
$_['text_iframe']				= 'IFrame (platební formulář v okně)';
$_['text_connect_eway']	        = 'eWAY pomáhá podnikům bezpečně zpracovávat všechny hlavní kreditní karty, s vestavěnou prevencí podvodů, technickou podporou 24/7 a mnoha dalšími výhodami. <a target="_blank" href="https://myeway.force.com/success/accelerator-signup?pid=4382&pa=0012000000ivcWf">Klikněte zde</a>';
$_['text_eway_image']	        = '<a target="_blank" href="https://myeway.force.com/success/accelerator-signup?pid=4382&pa=0012000000ivcWf"><img src="view/image/payment/eway_connect.png" alt="eWAY" title="eWAY" class="img-fluid" /></a>';

// Entry
$_['entry_paymode']				= 'Režim platby';
$_['entry_test']				= 'Testovací režim';
$_['entry_order_status']		= 'Stav objednávky';
$_['entry_order_status_refund'] = 'Stav vrácené objednávky';
$_['entry_order_status_auth']	= 'Stav autorizované objednávky';
$_['entry_order_status_fraud']	= 'Stav podezření na podvod';
$_['entry_status']				= 'Stav';
$_['entry_username']			= 'eWAY API klíč';
$_['entry_password']			= 'eWAY heslo';
$_['entry_payment_type']		= 'Typ platby';
$_['entry_geo_zone']			= 'Geo zóna';
$_['entry_sort_order']			= 'Pořadí';
$_['entry_transaction_method']	= 'Způsob transakce';

// Error
$_['error_permission']			= 'Varování: Nemáte oprávnění upravovat platební modul eWAY';
$_['error_username']			= 'eWAY API klíč je povinný!';
$_['error_password']			= 'eWAY heslo je povinné!';
$_['error_payment_type']		= 'Je vyžadován alespoň jeden typ platby!';

// Help hints
$_['help_testmode']				= 'Nastavte na Ano pro použití eWAY Sandbox.';
$_['help_username']				= 'Váš eWAY API klíč z vašeho MYeWAY účtu.';
$_['help_password']				= 'Vaše eWAY API heslo z vašeho MYeWAY účtu.';
$_['help_transaction_method']	= 'Autorizace je dostupná pouze pro australské banky';

// Order page - payment tab
$_['text_payment_info']			= 'Informace o platbě';
$_['text_order_total']			= 'Celkem autorizováno';
$_['text_transactions']			= 'Transakce';
$_['text_column_transactionid'] = 'ID transakce eWAY';
$_['text_column_amount']		= 'Částka';
$_['text_column_type']			= 'Typ';
$_['text_column_created']		= 'Vytvořeno';
$_['text_total_captured']		= 'Celkem zachyceno';
$_['text_capture_status']		= 'Platba zachycena';
$_['text_void_status']			= 'Platba stornována';
$_['text_refund_status']		= 'Platba vrácena';
$_['text_total_refunded']		= 'Celkem vráceno';
$_['text_refund_success']		= 'Vrácení bylo úspěšné!';
$_['text_capture_success']		= 'Zachycení bylo úspěšné!';
$_['text_refund_failed']		= 'Vrácení selhalo: ';
$_['text_capture_failed']		= 'Zachycení selhalo: ';
$_['text_unknown_failure']		= 'Neplatná objednávka nebo částka';

$_['text_confirm_capture']		= 'Opravdu chcete zachytit platbu?';
$_['text_confirm_release']		= 'Opravdu chcete uvolnit platbu?';
$_['text_confirm_refund']		= 'Opravdu chcete vrátit platbu?';

$_['text_empty_refund']			= 'Zadejte částku k vrácení';
$_['text_empty_capture']		= 'Zadejte částku k zachycení';

$_['btn_refund']				= 'Vrátit';
$_['btn_capture']				= 'Zachytit';

// Validation Error codes
$_['text_card_message_V6000']	= 'Nedefinovaná chyba validace';
$_['text_card_message_V6001'] 	= 'Neplatná IP zákazníka';
$_['text_card_message_V6002'] 	= 'Neplatné ID zařízení';
$_['text_card_message_V6011'] 	= 'Neplatná částka';
$_['text_card_message_V6012'] 	= 'Neplatný popis faktury';
$_['text_card_message_V6013'] 	= 'Neplatné číslo faktury';
$_['text_card_message_V6014'] 	= 'Neplatný odkaz na fakturu';
$_['text_card_message_V6015'] 	= 'Neplatný kód měny';
$_['text_card_message_V6016'] 	= 'Platba je povinná';
$_['text_card_message_V6017'] 	= 'Kód měny platby je povinný';
$_['text_card_message_V6018'] 	= 'Neznámý kód měny platby';
$_['text_card_message_V6021'] 	= 'Jméno držitele karty je povinné';
$_['text_card_message_V6022'] 	= 'Číslo karty je povinné';
$_['text_card_message_V6023'] 	= 'CVN je povinné';
$_['text_card_message_V6031'] 	= 'Neplatné číslo karty';
$_['text_card_message_V6032'] 	= 'Neplatné CVN';
$_['text_card_message_V6033'] 	= 'Neplatné datum vypršení platnosti';
$_['text_card_message_V6034'] 	= 'Neplatné číslo vydání';
$_['text_card_message_V6035'] 	= 'Neplatné počáteční datum';
$_['text_card_message_V6036'] 	= 'Neplatný měsíc';
$_['text_card_message_V6037'] 	= 'Neplatný rok';
$_['text_card_message_V6040'] 	= 'Neplatné ID tokenu zákazníka';
$_['text_card_message_V6041'] 	= 'Zákazník je povinný';
$_['text_card_message_V6042'] 	= 'Jméno zákazníka je povinné';
$_['text_card_message_V6043'] 	= 'Příjmení zákazníka je povinné';
$_['text_card_message_V6044'] 	= 'Kód země zákazníka je povinný';
$_['text_card_message_V6045'] 	= 'Titul zákazníka je povinný';
$_['text_card_message_V6046'] 	= 'ID tokenu zákazníka je povinné';
$_['text_card_message_V6047'] 	= 'RedirectURL je povinné';
$_['text_card_message_V6051'] 	= 'Neplatné jméno';
$_['text_card_message_V6052'] 	= 'Neplatné příjmení';
$_['text_card_message_V6053'] 	= 'Neplatný kód země';
$_['text_card_message_V6054'] 	= 'Neplatný e-mail';
$_['text_card_message_V6055'] 	= 'Neplatné telefonní číslo';
$_['text_card_message_V6056'] 	= 'Neplatné mobilní číslo';
$_['text_card_message_V6057'] 	= 'Neplatné faxové číslo';
$_['text_card_message_V6058'] 	= 'Neplatný titul';
$_['text_card_message_V6059'] 	= 'Neplatné URL pro přesměrování';
$_['text_card_message_V6060'] 	= 'Neplatné URL pro přesměrování';
$_['text_card_message_V6061'] 	= 'Neplatný odkaz';
$_['text_card_message_V6062'] 	= 'Neplatný název společnosti';
$_['text_card_message_V6063'] 	= 'Neplatný popis pracovní pozice';
$_['text_card_message_V6064'] 	= 'Neplatná ulice 1';
$_['text_card_message_V6065'] 	= 'Neplatná ulice 2';
$_['text_card_message_V6066'] 	= 'Neplatné město';
$_['text_card_message_V6067'] 	= 'Neplatný stát';
$_['text_card_message_V6068'] 	= 'Neplatné PSČ';
$_['text_card_message_V6069'] 	= 'Neplatný e-mail';
$_['text_card_message_V6070'] 	= 'Neplatné telefonní číslo';
$_['text_card_message_V6071'] 	= 'Neplatné mobilní číslo';
$_['text_card_message_V6072'] 	= 'Neplatné komentáře';
$_['text_card_message_V6073'] 	= 'Neplatné faxové číslo';
$_['text_card_message_V6074'] 	= 'Neplatné URL';
$_['text_card_message_V6075'] 	= 'Neplatné jméno dodací adresy';
$_['text_card_message_V6076'] 	= 'Neplatné příjmení dodací adresy';
$_['text_card_message_V6077'] 	= 'Neplatná ulice 1 dodací adresy';
$_['text_card_message_V6078'] 	= 'Neplatná ulice 2 dodací adresy';
$_['text_card_message_V6079'] 	= 'Neplatné město dodací adresy';
$_['text_card_message_V6080'] 	= 'Neplatný stát dodací adresy';
$_['text_card_message_V6081'] 	= 'Neplatné PSČ dodací adresy';
$_['text_card_message_V6082'] 	= 'Neplatný e-mail dodací adresy';
$_['text_card_message_V6083'] 	= 'Neplatné telefonní číslo dodací adresy';
$_['text_card_message_V6084'] 	= 'Neplatná země dodací adresy';
$_['text_card_message_V6091'] 	= 'Neznámý kód země';
$_['text_card_message_V6100'] 	= 'Neplatný název karty';
$_['text_card_message_V6101'] 	= 'Neplatný měsíc vypršení platnosti karty';
$_['text_card_message_V6102'] 	= 'Neplatný rok vypršení platnosti karty';
$_['text_card_message_V6103'] 	= 'Neplatný počáteční měsíc karty';
$_['text_card_message_V6104'] 	= 'Neplatný počáteční rok karty';
$_['text_card_message_V6105'] 	= 'Neplatné číslo vydání karty';
$_['text_card_message_V6106'] 	= 'Neplatné CVN karty';
$_['text_card_message_V6107'] 	= 'Neplatný AccessCode';
$_['text_card_message_V6108'] 	= 'Neplatný CustomerHostAddress';
$_['text_card_message_V6109'] 	= 'Neplatný UserAgent';
$_['text_card_message_V6110'] 	= 'Neplatné číslo karty';
$_['text_card_message_V6111'] 	= 'Neautorizovaný přístup API, účet není PCI certifikovaný';
$_['text_card_message_V6112'] 	= 'Nadbytečné údaje o kartě kromě roku a měsíce vypršení platnosti';
$_['text_card_message_V6113'] 	= 'Neplatná transakce pro vrácení';
$_['text_card_message_V6114'] 	= 'Chyba validace brány';
$_['text_card_message_V6115'] 	= 'Neplatný DirectRefundRequest, ID transakce';
$_['text_card_message_V6116'] 	= 'Neplatné údaje o kartě na původním TransactionID';
$_['text_card_message_V6124'] 	= 'Neplatné řádkové položky. Řádkové položky byly poskytnuty, ale součty se neshodují s polem TotalAmount';
$_['text_card_message_V6125'] 	= 'Vybraný typ platby není povolen';
$_['text_card_message_V6126'] 	= 'Neplatné zašifrované číslo karty, dešifrování selhalo';
$_['text_card_message_V6127'] 	= 'Neplatné zašifrované CVN, dešifrování selhalo';
$_['text_card_message_V6128'] 	= 'Neplatná metoda pro typ platby';
$_['text_card_message_V6129'] 	= 'Transakce nebyla autorizována pro zachycení/zrušení';
$_['text_card_message_V6130'] 	= 'Obecná chyba informací o zákazníkovi';
$_['text_card_message_V6131'] 	= 'Obecná chyba informací o dopravě';
$_['text_card_message_V6132'] 	= 'Transakce již byla dokončena nebo stornována, operace není povolena';
$_['text_card_message_V6133'] 	= 'Pokladna není dostupná pro typ platby';
$_['text_card_message_V6134'] 	= 'Neplatné ID autorizační transakce pro zachycení/storno';
$_['text_card_message_V6135'] 	= 'Chyba PayPal při zpracování vrácení';
$_['text_card_message_V6140'] 	= 'Účet obchodníka je pozastaven';
$_['text_card_message_V6141'] 	= 'Neplatné údaje o PayPal účtu nebo podpis API';
$_['text_card_message_V6142'] 	= 'Autorizace není dostupná pro Bank/Branch';
$_['text_card_message_V6150'] 	= 'Neplatná částka vrácení';
$_['text_card_message_V6151'] 	= 'Částka vrácení větší než původní transakce';
$_['text_card_message_D4406'] 	= 'Neznámá chyba';
$_['text_card_message_S5010'] 	= 'Neznámá chyba';
