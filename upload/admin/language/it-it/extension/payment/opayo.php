<?php
// Heading
$_['heading_title']		 				= 'Opayo';

// Text
$_['text_opayo']	  					= '<img src="view/image/payment/opayo.png" alt="Opayo" title="Opayo" />';
$_['text_extensions']     				= 'Estensioni';
$_['text_edit']          				= 'Modifica Opayo';
$_['text_tab_general']				 	= 'Generale';
$_['text_tab_cron']						= 'Cron';
$_['text_test']				  			= 'Test';
$_['text_live']				  			= 'Live';
$_['text_defered']			  			= 'Differito';
$_['text_authenticate']		  			= 'Autorizza';
$_['text_payment']		  				= 'Pagamento';
$_['text_payment_info']		  			= 'Informazioni sul pagamento';
$_['text_release_status']	  			= 'Pagamento rilasciato';
$_['text_void_status']		 			= 'Pagamento annullato';
$_['text_rebate_status']	  			= 'Pagamento rimborsato';
$_['text_order_ref']		  			= 'Rif. ordine';
$_['text_order_total']		  			= 'Totale autorizzato';
$_['text_total_released']	  			= 'Totale rilasciato';
$_['text_transactions']		  			= 'Transazioni';
$_['text_column_amount']	  			= 'Importo';
$_['text_column_type']		  			= 'Tipo';
$_['text_column_date_added']  			= 'Creato';
$_['text_confirm_void']		  			= 'Sei sicuro di voler annullare il pagamento?';
$_['text_confirm_release']	  			= 'Sei sicuro di voler rilasciare il pagamento?';
$_['text_confirm_rebate']	  			= 'Sei sicuro di voler rimborsare il pagamento?';

// Entry
$_['entry_vendor']			  			= 'Venditore';
$_['entry_environment']			  		= 'Ambiente';
$_['entry_transaction_method']		  	= 'Metodo Transazione';
$_['entry_total']             			= 'Totale';
$_['entry_order_status']	  			= 'Stato Ordine';
$_['entry_geo_zone']		  			= 'Zona Geografica';
$_['entry_status']			  			= 'Stato';
$_['entry_sort_order']		  			= 'Ordinamento';
$_['entry_debug']			  			= 'Log di Debug';
$_['entry_card_save']			  		= 'Memorizza Carte';
$_['entry_cron_token']	  				= 'Token Segreto';
$_['entry_cron_url']	  				= 'URL';
$_['entry_cron_last_run']	 			= 'Ultima esecuzione:';

// Help
$_['help_total']			  			= 'Il totale del checkout che l\'ordine deve raggiungere prima che questo metodo di pagamento diventi attivo.';
$_['help_debug']			  			= 'L\'abilitazione del debug scriverà dati sensibili in un file di log. Dovresti sempre disabilitarlo se non diversamente indicato.';
$_['help_transaction_method']  			= 'Il metodo di transazione DEVE essere impostato su Pagamento per consentire pagamenti in abbonamento.';
$_['help_card_save']			  		= 'Affinché l\'acquirente possa salvare i dettagli della carta per pagamenti successivi, è necessario sottoscrivere MID TOKEN. Dovrai contattare il supporto clienti Opayo per discutere l\'abilitazione del sistema di token per il tuo account.';
$_['help_cron_token']	  				= 'Rendilo lungo e difficile da indovinare.';
$_['help_cron_url']		  				= 'Imposta un cron per chiamare questo URL.';

// Button
$_['button_release']		  			= 'Rilascia';
$_['button_rebate']			  			= 'Rimborso / rimborsa';
$_['button_void']			  			= 'Annulla';
$_['button_enable_recurring']			= 'Abilita Ricorrente';
$_['button_disable_recurring']			= 'Disabilita Ricorrente';

// Success
$_['success_save']		 				= 'Successo: Hai modificato Opayo!';
$_['success_release_ok']		  		= 'Successo: Il rilascio è andato a buon fine!';
$_['success_release_ok_order']	  		= 'Successo: Il rilascio è andato a buon fine, lo stato dell\'ordine è stato aggiornato a successo - regolato!';
$_['success_rebate_ok']		  			= 'Successo: Il rimborso è andato a buon fine!';
$_['success_rebate_ok_order']	  		= 'Successo: Il rimborso è andato a buon fine, lo stato dell\'ordine è stato aggiornato a rimborsato!';
$_['success_void_ok']			  		= 'Successo: L\'annullamento è andato a buon fine, lo stato dell\'ordine è stato aggiornato ad annullato!';
$_['success_enable_recurring']			= 'Successo: Il pagamento ricorrente è stato abilitato!';
$_['success_disable_recurring']			= 'Successo: Il pagamento ricorrente è stato disabilitato!';

// Error
$_['error_warning']          			= 'Attenzione: Controlla attentamente il modulo per errori!';
$_['error_permission']		  			= 'Attenzione: Non hai il permesso di modificare il pagamento Opayo!';
$_['error_vendor']			  			= 'ID Venditore obbligatorio!';
