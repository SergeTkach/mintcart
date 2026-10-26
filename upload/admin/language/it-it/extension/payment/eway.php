<?php
// Heading
$_['heading_title']				= 'Pagamento eWAY';

// Text
$_['text_extension']			= 'Estensioni';
$_['text_success']				= 'Successo: Hai modificato i tuoi dettagli eWAY!';
$_['text_edit']					= 'Modifica eWAY';
$_['text_eway']					= '<a target="_BLANK" href="http://www.eway.com.au/"><img src="view/image/payment/eway.png" alt="eWAY" title="eWAY" style="border: 1px solid #EEEEEE;" /></a>';
$_['text_authorisation']		= 'Autorizzazione';
$_['text_sale']					= 'Vendita';
$_['text_transparent']			= 'Redirect Trasparente (modulo di pagamento sul sito)';
$_['text_iframe']				= 'IFrame (modulo di pagamento nella finestra)';
$_['text_connect_eway']	        = 'eWAY aiuta le aziende a elaborare in modo sicuro tutte le principali carte di credito, con prevenzione frodi integrata, supporto tecnico 24/7 e molto altro. <a target="_blank" href="https://myeway.force.com/success/accelerator-signup?pid=4382&pa=0012000000ivcWf">Clicca Qui</a>';
$_['text_eway_image']	        = '<a target="_blank" href="https://myeway.force.com/success/accelerator-signup?pid=4382&pa=0012000000ivcWf"><img src="view/image/payment/eway_connect.png" alt="eWAY" title="eWAY" class="img-fluid" /></a>';

// Entry
$_['entry_paymode']				= 'Modalità di Pagamento';
$_['entry_test']				= 'Modalità Test';
$_['entry_order_status']		= 'Stato ordine';
$_['entry_order_status_refund'] = 'Stato ordine rimborsato';
$_['entry_order_status_auth']	= 'Stato ordine autorizzato';
$_['entry_order_status_fraud']	= 'Stato ordine sospetta frode';
$_['entry_status']				= 'Stato';
$_['entry_username']			= 'Chiave API eWAY';
$_['entry_password']			= 'Password eWAY';
$_['entry_payment_type']		= 'Tipo di Pagamento';
$_['entry_geo_zone']			= 'Zona Geografica';
$_['entry_sort_order']			= 'Ordinamento';
$_['entry_transaction_method']	= 'Metodo Transazione';

// Error
$_['error_permission']			= 'Attenzione: Non hai il permesso di modificare il modulo di pagamento eWAY';
$_['error_username']			= 'Chiave API eWAY obbligatoria!';
$_['error_password']			= 'Password eWAY obbligatoria!';
$_['error_payment_type']		= 'È richiesto almeno un tipo di pagamento!';

// Help hints
$_['help_testmode']				= 'Imposta su Sì per usare eWAY Sandbox.';
$_['help_username']				= 'La tua Chiave API eWAY dal tuo account MYeWAY.';
$_['help_password']				= 'La tua Password API eWAY dal tuo account MYeWAY.';
$_['help_transaction_method']	= 'L\'autorizzazione è disponibile solo per le banche australiane';

// Order page - payment tab
$_['text_payment_info']			= 'Informazioni sul pagamento';
$_['text_order_total']			= 'Totale autorizzato';
$_['text_transactions']			= 'Transazioni';
$_['text_column_transactionid'] = 'ID Transazione eWAY';
$_['text_column_amount']		= 'Importo';
$_['text_column_type']			= 'Tipo';
$_['text_column_created']		= 'Creato';
$_['text_total_captured']		= 'Totale catturato';
$_['text_capture_status']		= 'Pagamento catturato';
$_['text_void_status']			= 'Pagamento annullato';
$_['text_refund_status']		= 'Pagamento rimborsato';
$_['text_total_refunded']		= 'Totale rimborsato';
$_['text_refund_success']		= 'Rimborso riuscito!';
$_['text_capture_success']		= 'Cattura riuscita!';
$_['text_refund_failed']		= 'Rimborso fallito: ';
$_['text_capture_failed']		= 'Cattura fallita: ';
$_['text_unknown_failure']		= 'Ordine o importo non valido';
$_['text_refund']               = 'Rimborsa';

$_['text_confirm_capture']		= 'Sei sicuro di voler catturare il pagamento?';
$_['text_confirm_release']		= 'Sei sicuro di voler rilasciare il pagamento?';
$_['text_confirm_refund']		= 'Sei sicuro di voler rimborsare il pagamento?';

$_['text_empty_refund']			= 'Inserisci un importo da rimborsare';
$_['text_empty_capture']		= 'Inserisci un importo da catturare';

$_['btn_refund']				= 'Rimborsa';
$_['btn_capture']				= 'Cattura';

// Validation Error codes
$_['text_card_message_V6000']	= 'Errore di Validazione Non Definito';
$_['text_card_message_V6001'] 	= 'IP Cliente Non Valido';
$_['text_card_message_V6002'] 	= 'DeviceID Non Valido';
$_['text_card_message_V6011'] 	= 'Importo Non Valido';
$_['text_card_message_V6012'] 	= 'Descrizione Fattura Non Valida';
$_['text_card_message_V6013'] 	= 'Numero Fattura Non Valido';
$_['text_card_message_V6014'] 	= 'Riferimento Fattura Non Valido';
$_['text_card_message_V6015'] 	= 'Codice Valuta Non Valido';
$_['text_card_message_V6016'] 	= 'Pagamento Richiesto';
$_['text_card_message_V6017'] 	= 'Codice Valuta Pagamento Richiesto';
$_['text_card_message_V6018'] 	= 'Codice Valuta Pagamento Sconosciuto';
$_['text_card_message_V6021'] 	= 'Nome Titolare Carta Richiesto';
$_['text_card_message_V6022'] 	= 'Numero Carta Richiesto';
$_['text_card_message_V6023'] 	= 'CVN Richiesto';
$_['text_card_message_V6031'] 	= 'Numero Carta Non Valido';
$_['text_card_message_V6032'] 	= 'CVN Non Valido';
$_['text_card_message_V6033'] 	= 'Data Scadenza Non Valida';
$_['text_card_message_V6034'] 	= 'Numero Emissione Non Valido';
$_['text_card_message_V6035'] 	= 'Data Inizio Non Valida';
$_['text_card_message_V6036'] 	= 'Mese Non Valido';
$_['text_card_message_V6037'] 	= 'Anno Non Valido';
$_['text_card_message_V6040'] 	= 'Token Customer Id Non Valido';
$_['text_card_message_V6041'] 	= 'Cliente Richiesto';
$_['text_card_message_V6042'] 	= 'Nome Cliente Richiesto';
$_['text_card_message_V6043'] 	= 'Cognome Cliente Richiesto';
$_['text_card_message_V6044'] 	= 'Codice Paese Cliente Richiesto';
$_['text_card_message_V6045'] 	= 'Titolo Cliente Richiesto';
$_['text_card_message_V6046'] 	= 'Token Customer ID Richiesto';
$_['text_card_message_V6047'] 	= 'RedirectURL Richiesto';
$_['text_card_message_V6051'] 	= 'Nome Non Valido';
$_['text_card_message_V6052'] 	= 'Cognome Non Valido';
$_['text_card_message_V6053'] 	= 'Codice Paese Non Valido';
$_['text_card_message_V6054'] 	= 'Email Non Valida';
$_['text_card_message_V6055'] 	= 'Telefono Non Valido';
$_['text_card_message_V6056'] 	= 'Cellulare Non Valido';
$_['text_card_message_V6057'] 	= 'Fax Non Valido';
$_['text_card_message_V6058'] 	= 'Titolo Non Valido';
$_['text_card_message_V6059'] 	= 'URL Redirect Non Valido';
$_['text_card_message_V6060'] 	= 'URL Redirect Non Valido';
$_['text_card_message_V6061'] 	= 'Riferimento Non Valido';
$_['text_card_message_V6062'] 	= 'Nome Azienda Non Valido';
$_['text_card_message_V6063'] 	= 'Descrizione Lavoro Non Valida';
$_['text_card_message_V6064'] 	= 'Indirizzo1 Non Valido';
$_['text_card_message_V6065'] 	= 'Indirizzo2 Non Valido';
$_['text_card_message_V6066'] 	= 'Città Non Valida';
$_['text_card_message_V6067'] 	= 'Stato Non Valido';
$_['text_card_message_V6068'] 	= 'Codice Postale Non Valido';
$_['text_card_message_V6069'] 	= 'Email Non Valida';
$_['text_card_message_V6070'] 	= 'Telefono Non Valido';
$_['text_card_message_V6071'] 	= 'Cellulare Non Valido';
$_['text_card_message_V6072'] 	= 'Commenti Non Validi';
$_['text_card_message_V6073'] 	= 'Fax Non Valido';
$_['text_card_message_V6074'] 	= 'URL Non Valido';
$_['text_card_message_V6075'] 	= 'Nome Indirizzo Spedizione Non Valido';
$_['text_card_message_V6076'] 	= 'Cognome Indirizzo Spedizione Non Valido';
$_['text_card_message_V6077'] 	= 'Indirizzo1 Spedizione Non Valido';
$_['text_card_message_V6078'] 	= 'Indirizzo2 Spedizione Non Valido';
$_['text_card_message_V6079'] 	= 'Città Spedizione Non Valida';
$_['text_card_message_V6080'] 	= 'Stato Spedizione Non Valido';
$_['text_card_message_V6081'] 	= 'Codice Postale Spedizione Non Valido';
$_['text_card_message_V6082'] 	= 'Email Spedizione Non Valida';
$_['text_card_message_V6083'] 	= 'Telefono Spedizione Non Valido';
$_['text_card_message_V6084'] 	= 'Paese Spedizione Non Valido';
$_['text_card_message_V6091'] 	= 'Codice Paese Sconosciuto';
$_['text_card_message_V6100'] 	= 'Nome Carta Non Valido';
$_['text_card_message_V6101'] 	= 'Mese Scadenza Carta Non Valido';
$_['text_card_message_V6102'] 	= 'Anno Scadenza Carta Non Valido';
$_['text_card_message_V6103'] 	= 'Mese Inizio Carta Non Valido';
$_['text_card_message_V6104'] 	= 'Anno Inizio Carta Non Valido';
$_['text_card_message_V6105'] 	= 'Numero Emissione Carta Non Valido';
$_['text_card_message_V6106'] 	= 'CVN Carta Non Valido';
$_['text_card_message_V6107'] 	= 'Codice Accesso Non Valido';
$_['text_card_message_V6108'] 	= 'CustomerHostAddress Non Valido';
$_['text_card_message_V6109'] 	= 'UserAgent Non Valido';
$_['text_card_message_V6110'] 	= 'Numero Carta Non Valido';
$_['text_card_message_V6111'] 	= 'Accesso API Non Autorizzato, Account Non Certificato PCI';
$_['text_card_message_V6112'] 	= 'Dettagli carta ridondanti diversi da anno e mese di scadenza';
$_['text_card_message_V6113'] 	= 'Transazione non valida per il rimborso';
$_['text_card_message_V6114'] 	= 'Errore di validazione del gateway';
$_['text_card_message_V6115'] 	= 'DirectRefundRequest non valido, ID Transazione';
$_['text_card_message_V6116'] 	= 'Dati carta non validi sul TransactionID originale';
$_['text_card_message_V6124'] 	= 'Voci Non Valide. Le voci sono state fornite ma i totali non corrispondono al campo TotalAmount';
$_['text_card_message_V6125'] 	= 'Tipo di Pagamento Selezionato non abilitato';
$_['text_card_message_V6126'] 	= 'Numero carta crittografato non valido, decrittazione fallita';
$_['text_card_message_V6127'] 	= 'CVN crittografato non valido, decrittazione fallita';
$_['text_card_message_V6128'] 	= 'Metodo non valido per il tipo di pagamento';
$_['text_card_message_V6129'] 	= 'La transazione non è stata autorizzata per Cattura/Annullamento';
$_['text_card_message_V6130'] 	= 'Errore generico informazioni cliente';
$_['text_card_message_V6131'] 	= 'Errore generico informazioni spedizione';
$_['text_card_message_V6132'] 	= 'La transazione è già stata completata o annullata, operazione non consentita';
$_['text_card_message_V6133'] 	= 'Checkout non disponibile per il tipo di pagamento';
$_['text_card_message_V6134'] 	= 'ID Transazione Auth non valido per Cattura/Annullamento';
$_['text_card_message_V6135'] 	= 'Errore PayPal nell\'elaborazione del rimborso';
$_['text_card_message_V6140'] 	= 'L\'account commerciante è sospeso';
$_['text_card_message_V6141'] 	= 'Dettagli account PayPal o firma API non validi';
$_['text_card_message_V6142'] 	= 'Autorizzazione non disponibile per Banca/Filiale';
$_['text_card_message_V6150'] 	= 'Importo Rimborso Non Valido';
$_['text_card_message_V6151'] 	= 'Importo rimborso superiore alla transazione originale';
$_['text_card_message_D4406'] 	= 'Errore sconosciuto';
$_['text_card_message_S5010'] 	= 'Errore sconosciuto';
