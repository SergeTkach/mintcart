<?php
// Heading
$_['heading_title']					 = 'Globalpay Redirect';

// Text
$_['text_extension']				  	 = 'Estensioni';
$_['text_success']					 = 'Successo: Hai modificato i dettagli dell\'account Globalpay!';
$_['text_edit']                      = 'Modifica Globalpay Redirect';
$_['text_live']						 = 'Live';
$_['text_demo']						 = 'Demo';
$_['text_card_type']				 = 'Tipo carta';
$_['text_enabled']					 = 'Abilitato';
$_['text_use_default']				 = 'Usa predefinito';
$_['text_merchant_id']				 = 'ID Commerciante';
$_['text_subaccount']				 = 'Sottoconto';
$_['text_secret']					 = 'Segreto condiviso';
$_['text_card_visa']				 = 'Visa';
$_['text_card_master']				 = 'Mastercard';
$_['text_card_amex']				 = 'American Express';
$_['text_card_switch']				 = 'Switch/Maestro';
$_['text_card_laser']				 = 'Laser';
$_['text_card_diners']				 = 'Diners';
$_['text_capture_ok']				 = 'La cattura è andata a buon fine';
$_['text_capture_ok_order']			 = 'La cattura è andata a buon fine, lo stato dell\'ordine è stato aggiornato a successo - regolato';
$_['text_rebate_ok']				 = 'Il rimborso è andato a buon fine';
$_['text_rebate_ok_order']			 = 'Il rimborso è andato a buon fine, lo stato dell\'ordine è stato aggiornato a rimborsato';
$_['text_void_ok']					 = 'L\'annullamento è andato a buon fine, lo stato dell\'ordine è stato aggiornato ad annullato';
$_['text_settle_auto']				 = 'Automatico';
$_['text_settle_delayed']			 = 'Ritardato';
$_['text_settle_multi']				 = 'Multiplo';
$_['text_url_message']				 = 'Devi fornire l\'URL del negozio al tuo account manager Globalpay prima di andare live';
$_['text_payment_info']				 = 'Informazioni sul pagamento';
$_['text_capture_status']			 = 'Pagamento catturato';
$_['text_void_status']				 = 'Pagamento annullato';
$_['text_rebate_status']			 = 'Pagamento rimborsato';
$_['text_order_ref']				 = 'Rif. ordine';
$_['text_order_total']				 = 'Totale autorizzato';
$_['text_total_captured']			 = 'Totale catturato';
$_['text_transactions']				 = 'Transazioni';
$_['text_column_amount']			 = 'Importo';
$_['text_column_type']				 = 'Tipo';
$_['text_column_date_added']		 = 'Creato';
$_['text_confirm_void']				 = 'Sei sicuro di voler annullare il pagamento?';
$_['text_confirm_capture']			 = 'Sei sicuro di voler catturare il pagamento?';
$_['text_confirm_rebate']			 = 'Sei sicuro di voler rimborsare il pagamento?';
$_['text_globalpay']                 = '<a target="_blank" href="https://resourcecentre.globaliris.com/getting-started.php?id=OpenCart"><img src="view/image/payment/globalpay.png" alt="Globalpay" title="Globalpay" style="border: 1px solid #EEEEEE;" /></a>';
$_['text_void']                      = 'Annulla'; 
$_['text_payment']                   = 'Pagamento';
$_['text_rebate']                    = 'Rimborso';

// Entry
$_['entry_merchant_id']				 = 'ID Commerciante';
$_['entry_secret']					 = 'Segreto condiviso';
$_['entry_rebate_password']			 = 'Password rimborso';
$_['entry_total']					 = 'Totale';
$_['entry_sort_order']				 = 'Ordinamento';
$_['entry_geo_zone']				 = 'Zona geografica';
$_['entry_status']					 = 'Stato';
$_['entry_debug']					 = 'Log di Debug';
$_['entry_live_demo']				 = 'Live / Demo';
$_['entry_auto_settle']				 = 'Tipo di regolamento';
$_['entry_card_select']				 = 'Seleziona carta';
$_['entry_tss_check']				 = 'Controlli TSS';
$_['entry_live_url']				 = 'URL connessione Live';
$_['entry_demo_url']				 = 'URL connessione Demo';
$_['entry_status_success_settled']	 = 'Successo - regolato';
$_['entry_status_success_unsettled'] = 'Successo - non regolato';
$_['entry_status_decline']			 = 'Rifiuto';
$_['entry_status_decline_pending']	 = 'Rifiuto - auth offline';
$_['entry_status_decline_stolen']	 = 'Rifiuto - carta persa o rubata';
$_['entry_status_decline_bank']		 = 'Rifiuto - errore banca';
$_['entry_status_void']				 = 'Annullato';
$_['entry_status_rebate']			 = 'Rimborsato';
$_['entry_notification_url']		 = 'URL Notifica';

// Help
$_['help_total']					 = 'Il totale del checkout che l\'ordine deve raggiungere prima che questo metodo di pagamento diventi attivo';
$_['help_card_select']				 = 'Chiedi all\'utente di scegliere il tipo di carta prima di reindirizzarlo';
$_['help_notification']				 = 'Devi fornire questo URL a Globalpay per ricevere le notifiche di pagamento';
$_['help_debug']					 = 'L\'abilitazione del debug scriverà dati sensibili in un file di log. Dovresti sempre disabilitarlo se non diversamente indicato';
$_['help_dcc_settle']				 = 'Se il tuo sottoconto è abilitato DCC devi usare Autosettle';

// Tab
$_['tab_api']					     = 'Dettagli API';
$_['tab_account']		     		 = 'Account';
$_['tab_order_status']				 = 'Stato ordine';
$_['tab_payment']					 = 'Impostazioni pagamento';
$_['tab_advanced']					 = 'Avanzate';

// Button
$_['button_capture']				 = 'Cattura';
$_['button_rebate']					 = 'Rimborso / rimborsa';
$_['button_void']					 = 'Annulla';

// Error
$_['error_merchant_id']				 = 'ID Commerciante obbligatorio';
$_['error_secret']					 = 'Segreto condiviso obbligatorio';
$_['error_live_url']				 = 'URL Live obbligatorio';
$_['error_demo_url']				 = 'URL Demo obbligatorio';
$_['error_data_missing']			 = 'Dati mancanti';
$_['error_use_select_card']			 = 'Devi avere "Seleziona Carta" abilitato per il routing del sottoconto per tipo di carta per funzionare';
