<?php
// Heading
$_['heading_title']					 = 'First Data EMEA Web Service API';

// Text
$_['text_firstdata_remote']			 = '<img src="view/image/payment/firstdata.png" alt="First Data" title="First Data" style="border: 1px solid #EEEEEE;" />';
$_['text_extension']					 = 'Estensioni';
$_['text_success']					 = 'Successo: Hai modificato i dettagli dell\'account First Data!';
$_['text_edit']                      = 'Modifica First Data EMEA Web Service API';
$_['text_card_type']				 = 'Tipo carta';
$_['text_enabled']					 = 'Abilitato';
$_['text_merchant_id']				 = 'ID Negozio';
$_['text_subaccount']				 = 'Sottoconto';
$_['text_user_id']					 = 'ID Utente';
$_['text_capture_ok']				 = 'La cattura è andata a buon fine';
$_['text_capture_ok_order']			 = 'La cattura è andata a buon fine, lo stato dell\'ordine è stato aggiornato a successo - regolato';
$_['text_refund_ok']				 = 'Il rimborso è andato a buon fine';
$_['text_refund_ok_order']			 = 'Il rimborso è andato a buon fine, lo stato dell\'ordine è stato aggiornato a rimborsato';
$_['text_void_ok']					 = 'L\'annullamento è andato a buon fine, lo stato dell\'ordine è stato aggiornato ad annullato';
$_['text_settle_auto']				 = 'Vendita';
$_['text_settle_delayed']			 = 'Pre auth';
$_['text_mastercard']				 = 'Mastercard';
$_['text_visa']						 = 'Visa';
$_['text_diners']					 = 'Diners';
$_['text_amex']						 = 'American Express';
$_['text_maestro']					 = 'Maestro';
$_['text_payment_info']				 = 'Informazioni sul pagamento';
$_['text_capture_status']			 = 'Pagamento catturato';
$_['text_void_status']				 = 'Pagamento annullato';
$_['text_refund_status']			 = 'Pagamento rimborsato';
$_['text_order_ref']				 = 'Rif. ordine';
$_['text_order_total']				 = 'Totale autorizzato';
$_['text_total_captured']			 = 'Totale catturato';
$_['text_transactions']				 = 'Transazioni';
$_['text_column_amount']			 = 'Importo';
$_['text_column_type']				 = 'Tipo';
$_['text_column_date_added']		 = 'Creato';
$_['text_confirm_void']				 = 'Sei sicuro di voler annullare il pagamento?';
$_['text_confirm_capture']			 = 'Sei sicuro di voler catturare il pagamento?';
$_['text_confirm_refund']			 = 'Sei sicuro di voler rimborsare il pagamento?';
$_['text_void']                      = 'Annulla';
$_['text_payment']                   = 'Pagamento';
$_['text_refund']                    = "Refund";

// Entry
$_['entry_certificate_path']		 = 'Percorso certificato';
$_['entry_certificate_key_path']	 = 'Percorso chiave privata';
$_['entry_certificate_key_pw']		 = 'Password chiave privata';
$_['entry_certificate_ca_path']		 = 'Percorso CA';
$_['entry_merchant_id']				 = 'ID Negozio';
$_['entry_user_id']					 = 'ID Utente';
$_['entry_password']				 = 'Password';
$_['entry_total']					 = 'Totale';
$_['entry_sort_order']				 = 'Ordinamento';
$_['entry_geo_zone']				 = 'Zona geografica';
$_['entry_status']					 = 'Stato';
$_['entry_debug']					 = 'Log di Debug';
$_['entry_auto_settle']				 = 'Tipo di regolamento';
$_['entry_status_success_settled']	 = 'Successo - regolato';
$_['entry_status_success_unsettled'] = 'Successo - non regolato';
$_['entry_status_decline']			 = 'Rifiuto';
$_['entry_status_void']				 = 'Annullato';
$_['entry_status_refund']			 = 'Rimborsato';
$_['entry_enable_card_store']		 = 'Abilita token di archiviazione carta';
$_['entry_cards_accepted']			 = 'Tipi di carta accettati';

// Help
$_['help_total']					 = 'Il totale del checkout che l\'ordine deve raggiungere prima che questo metodo di pagamento diventi attivo';
$_['help_certificate']				 = 'I certificati e le chiavi private dovrebbero essere archiviati al di fuori delle cartelle web pubbliche';
$_['help_card_select']				 = 'Chiedi all\'utente di scegliere il tipo di carta prima di reindirizzarlo';
$_['help_notification']				 = 'Devi fornire questo URL a First Data per ricevere le notifiche di pagamento';
$_['help_debug']					 = 'L\'abilitazione del debug scriverà dati sensibili in un file di log. Dovresti sempre disabilitarlo se non diversamente indicato .';
$_['help_settle']					 = 'Se usi la pre-autorizzazione devi completare un\'azione di post-autorizzazione entro 3-5 giorni altrimenti la tua transazione verrà scartata';

// Tab
$_['tab_account']					 = 'Info API';
$_['tab_order_status']				 = 'Stato ordine';
$_['tab_payment']					 = 'Impostazioni pagamento';

// Button
$_['button_capture']				 = 'Cattura';
$_['button_refund']					 = 'Rimborsa';
$_['button_void']					 = 'Annulla';

// Error
$_['error_merchant_id']				 = 'ID Negozio obbligatorio';
$_['error_user_id']					 = 'ID Utente obbligatorio';
$_['error_password']				 = 'Password obbligatoria';
$_['error_certificate']				 = 'Percorso certificato obbligatorio';
$_['error_key']						 = 'Chiave certificato obbligatoria';
$_['error_key_pw']					 = 'Password chiave certificato obbligatoria';
$_['error_ca']						 = 'Certificate Authority (CA) obbligatoria';
