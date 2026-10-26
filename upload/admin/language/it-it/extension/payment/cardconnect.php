<?php
// Heading
$_['heading_title']                 = 'CardConnect';

// Tab
$_['tab_settings']                  = 'Impostazioni';
$_['tab_order_status']              = 'Stato Ordine';

// Text
$_['text_extension']                = 'Estensioni';
$_['text_success']                  = 'Successo: Hai modificato il modulo di pagamento CardConnect!';
$_['text_edit']                     = 'Modifica CardConnect';
$_['text_cardconnect']              = '<a href="http://www.cardconnect.com" target="_blank"><img src="view/image/payment/cardconnect.png" alt="CardConnect" title="CardConnect"></a>';
$_['text_payment']                  = 'Pagamento';
$_['text_refund']                   = 'Rimborso';
$_['text_authorize']                = 'Autorizza';
$_['text_live']                     = 'Live';
$_['text_test']                     = 'Test';
$_['text_no_cron_time']             = 'Il cron non è ancora stato eseguito';
$_['text_payment_info']             = 'Informazioni sul Pagamento';
$_['text_payment_method']           = 'Metodo di Pagamento';
$_['text_card']                     = 'Carta';
$_['text_echeck']                   = 'eCheck';
$_['text_reference']                = 'Riferimento';
$_['text_update']                   = 'Aggiorna';
$_['text_order_total']              = 'Totale Ordine';
$_['text_total_captured']           = 'Totale Catturato';
$_['text_capture_payment']          = 'Cattura Pagamento';
$_['text_refund_payment']           = 'Rimborsa Pagamento';
$_['text_void']                     = 'Annulla';
$_['text_transactions']             = 'Transazioni';
$_['text_column_type']              = 'Tipo';
$_['text_column_reference']         = 'Riferimento';
$_['text_column_amount']            = 'Importo';
$_['text_column_status']            = 'Stato';
$_['text_column_date_modified']     = 'Data Modifica';
$_['text_column_date_added']        = 'Data Aggiunta';
$_['text_column_update']            = 'Aggiorna';
$_['text_column_void']              = 'Annulla';
$_['text_confirm_capture']          = 'Sei sicuro di voler catturare il pagamento?';
$_['text_confirm_refund']           = 'Sei sicuro di voler rimborsare il pagamento?';
$_['text_inquire_success']          = 'La richiesta è andata a buon fine';
$_['text_capture_success']          = 'La richiesta di cattura è andata a buon fine';
$_['text_refund_success']           = 'La richiesta di rimborso è andata a buon fine';
$_['text_void_success']             = 'La richiesta di annullamento è andata a buon fine';

// Entry
$_['entry_merchant_id']             = 'ID Commerciante';
$_['entry_api_username']            = 'Nome Utente API';
$_['entry_api_password']            = 'Password API';
$_['entry_token']                   = 'Token Segreto';
$_['entry_transaction']             = 'Transazione';
$_['entry_environment']             = 'Ambiente';
$_['entry_site']                    = 'Sito';
$_['entry_store_cards']             = 'Memorizza Carte';
$_['entry_echeck']                  = 'eCheck';
$_['entry_total']                   = 'Totale';
$_['entry_geo_zone']                = 'Zona Geografica';
$_['entry_status']                  = 'Stato';
$_['entry_logging']                 = 'Log di Debug';
$_['entry_sort_order']              = 'Ordinamento';
$_['entry_cron_url']                = 'URL Cron Job';
$_['entry_cron_time']               = 'Ultima Esecuzione Cron Job';
$_['entry_order_status_pending']    = 'In Attesa';
$_['entry_order_status_processing'] = 'In Lavorazione';

// Help
$_['help_merchant_id']              = 'Il tuo ID commerciante personale dell\'account CardConnect.';
$_['help_api_username']             = 'Il tuo nome utente per accedere all\'API CardConnect.';
$_['help_api_password']             = 'La tua password per accedere all\'API CardConnect.';
$_['help_token']                    = 'Inserisci un token casuale per sicurezza o usa quello generato.';
$_['help_transaction']              = 'Scegli \'Pagamento\' per catturare il pagamento immediatamente o \'Autorizza\' per doverlo approvare.';
$_['help_site']                     = 'Questo determina la prima parte dell\'URL API. Cambia solo se indicato.';
$_['help_store_cards']              = 'Se desideri memorizzare le carte usando la tokenizzazione.';
$_['help_echeck']                   = 'Se desideri offrire la possibilità di pagare usando un eCheck.';
$_['help_total']                    = 'Il totale del checkout che l\'ordine deve raggiungere prima che questo metodo di pagamento diventi attivo. Deve essere un valore senza simbolo di valuta.';
$_['help_logging']                  = 'L\'abilitazione del debug scriverà dati sensibili in un file di log. Dovresti sempre disabilitarlo se non diversamente indicato.';
$_['help_cron_url']                 = 'Imposta un cron job per chiamare questo URL in modo che gli ordini vengano aggiornati automaticamente. È progettato per essere eseguito alcune ore dopo l\'ultima cattura di un giorno lavorativo.';
$_['help_cron_time']                = 'Questa è l\'ultima volta che l\'URL del cron job è stato eseguito.';
$_['help_order_status_pending']     = 'Lo stato dell\'ordine quando l\'ordine deve essere autorizzato dal commerciante.';
$_['help_order_status_processing']  = 'Lo stato dell\'ordine quando l\'ordine viene catturato automaticamente.';

// Button
$_['button_inquire_all']            = 'Richiedi Tutto';
$_['button_capture']                = 'Cattura';
$_['button_refund']                 = 'Rimborsa';
$_['button_void_all']               = 'Annulla Tutto';
$_['button_inquire']                = 'Richiedi';
$_['button_void']                   = 'Annulla';

// Error
$_['error_permission']              = 'Attenzione: Non hai il permesso di modificare il pagamento CardConnect!';
$_['error_merchant_id']             = 'ID Commerciante obbligatorio!';
$_['error_api_username']            = 'Nome Utente API obbligatorio!';
$_['error_api_password']            = 'Password API obbligatoria!';
$_['error_token']                   = 'Token Segreto obbligatorio!';
$_['error_site']                    = 'Sito obbligatorio!';
$_['error_amount_zero']             = 'L\'importo deve essere superiore a zero!';
$_['error_no_order']                = 'Nessuna informazione sull\'ordine corrispondente!';
$_['error_invalid_response']        = 'Risposta non valida ricevuta!';
$_['error_data_missing']            = 'Dati mancanti!';
$_['error_not_enabled']             = 'Modulo non abilitato!';
