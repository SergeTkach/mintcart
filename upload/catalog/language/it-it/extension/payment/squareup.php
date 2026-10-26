<?php
// Text
$_['text_new_card']                     = '+ Aggiungi nuova carta';
$_['text_loading']                      = 'Caricamento... Attendere prego...';
$_['text_card_details']                 = 'Dettagli Carta';
$_['text_saved_card']                   = 'Usa Carta Salvata:';
$_['text_card_ends_in']                 = 'Paga con la carta %s esistente che termina con XXXX XXXX XXXX %s';
$_['text_card_number']                  = 'Numero Carta:';
$_['text_card_expiry']                  = 'Scadenza Carta (MM/YY):';
$_['text_card_cvc']                     = 'Codice di Sicurezza Carta (CVC):';
$_['text_card_zip']                     = 'Codice Postale Carta:';
$_['text_card_save']                    = 'Salvare la carta per usi futuri?';
$_['text_trial']                        = '%s ogni %s %s per %s pagamenti poi ';
$_['text_recurring']                    = '%s ogni %s %s';
$_['text_length']                       = ' per %s pagamenti';
$_['text_cron_subject']                 = 'Riepilogo cron job Square';
$_['text_cron_message']                 = 'Ecco un elenco di tutte le attività CRON eseguite dalla tua estensione Square:';
$_['text_squareup_profile_suspended']   = ' I tuoi pagamenti ricorrenti sono stati sospesi. Contattaci per maggiori dettagli.';
$_['text_squareup_trial_expired']       = ' Il tuo periodo di prova è scaduto.';
$_['text_squareup_recurring_expired']   = ' I tuoi pagamenti ricorrenti sono scaduti. Questo è stato il tuo ultimo pagamento.';
$_['text_cron_summary_token_heading']   = 'Aggiornamento del token di accesso:';
$_['text_cron_summary_token_updated']   = 'Token di accesso aggiornato con successo!';
$_['text_cron_summary_error_heading']   = 'Errori di Transazione:';
$_['text_cron_summary_fail_heading']    = 'Transazioni Fallite (Profili Sospesi):';
$_['text_cron_summary_success_heading'] = 'Transazioni Riuscite:';
$_['text_cron_fail_charge']             = 'Il Profilo <strong>#%s</strong> non può essere addebitato con <strong>%s</strong>';
$_['text_cron_success_charge']          = 'Il Profilo <strong>#%s</strong> è stato addebitato con <strong>%s</strong>';
$_['text_card_placeholder']             = 'XXXX XXXX XXXX XXXX';
$_['text_cvv']                          = 'CVV';
$_['text_expiry']                       = 'MM/YY';
$_['text_default_squareup_name']        = 'Carta di Credito / Debito';
$_['text_token_issue_customer_error']   = 'Stiamo riscontrando un\'interruzione tecnica nel nostro sistema di pagamento. Riprova più tardi.';
$_['text_token_revoked_subject']        = 'Il tuo token di accesso Square è stato revocato!';
$_['text_token_revoked_message']        = "The Square payment extension's access to your Square account has been revoked through the Square Dashboard. You need to verify your application credentials in the extension settings and connect again.";
$_['text_token_expired_subject']        = 'Il tuo token di accesso Square è scaduto!';
$_['text_token_expired_message']        = "The Square payment extension's access token connecting it to your Square account has expired. You need to verify your application credentials and CRON job in the extension settings and connect again.";

// Error
$_['error_browser_not_supported']       = 'Errore: Il sistema di pagamento non supporta più il tuo browser web. Aggiornalo o usane un altro.';
$_['error_card_invalid']                = 'Errore: La carta non è valida!';
$_['error_squareup_cron_token']         = 'Errore: Impossibile aggiornare il token di accesso. Collega la tua estensione di pagamento Square tramite il pannello di amministrazione OpenCart.';

// Warning
$_['warning_test_mode']                 = 'Attenzione: La modalità sandbox è abilitata! Le transazioni sembreranno andare a buon fine, ma nessun addebito verrà effettuato.';

// Statuses
$_['squareup_status_comment_authorized']    = 'La transazione con carta è stata autorizzata ma non ancora catturata.';
$_['squareup_status_comment_captured']      = 'La transazione con carta è stata autorizzata e successivamente catturata (es. completata).';
$_['squareup_status_comment_voided']        = 'La transazione con carta è stata autorizzata e successivamente annullata (es. cancellata). ';
$_['squareup_status_comment_failed']        = 'La transazione con carta è fallita.';

// Override errors
$_['squareup_override_error_billing_address.country']       = 'Il paese dell\'Indirizzo di Pagamento non è valido. Modificalo e riprova.';
$_['squareup_override_error_shipping_address.country']      = 'Il paese dell\'Indirizzo di Spedizione non è valido. Modificalo e riprova.';
$_['squareup_override_error_email_address']                 = 'L\'indirizzo e-mail del tuo cliente non è valido. Modificalo e riprova.';
$_['squareup_override_error_phone_number']                  = 'Il numero di telefono del tuo cliente non è valido. Modificalo e riprova.';
$_['squareup_error_field']                                  = ' Campo: %s';
