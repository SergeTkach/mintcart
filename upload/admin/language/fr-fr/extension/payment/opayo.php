<?php
// Heading
$_['heading_title']		 				= 'Opayo';

// Text
$_['text_opayo']	  					= '<img src="view/image/payment/opayo.png" alt="Opayo" title="Opayo" />';
$_['text_extensions']     				= 'Extensions';
$_['text_edit']          				= 'Modifier Opayo';
$_['text_tab_general']				 	= 'Général';
$_['text_tab_cron']						= 'Cron';
$_['text_test']				  			= 'Test';
$_['text_live']				  			= 'En direct';
$_['text_defered']			  			= 'Différé';
$_['text_authenticate']		  			= 'Authentifier';
$_['text_payment']		  				= 'Paiement';
$_['text_payment_info']		  			= 'Informations de paiement';
$_['text_release_status']	  			= 'Paiement libéré';
$_['text_void_status']		 			= 'Paiement annulé';
$_['text_rebate_status']	  			= 'Paiement remboursé';
$_['text_order_ref']		  			= 'Réf. commande';
$_['text_order_total']		  			= 'Total autorisé';
$_['text_total_released']	  			= 'Total libéré';
$_['text_transactions']		  			= 'Transactions';
$_['text_column_amount']	  			= 'Montant';
$_['text_column_type']		  			= 'Type';
$_['text_column_date_added']  			= 'Créé';
$_['text_confirm_void']		  			= 'Êtes-vous sûr de vouloir annuler le paiement ?';
$_['text_confirm_release']	  			= 'Êtes-vous sûr de vouloir libérer le paiement ?';
$_['text_confirm_rebate']	  			= 'Êtes-vous sûr de vouloir rembourser le paiement ?';

// Entry
$_['entry_vendor']			  			= 'Vendeur';
$_['entry_environment']			  		= 'Environnement';
$_['entry_transaction_method']		  	= 'Méthode de Transaction';
$_['entry_total']             			= 'Total';
$_['entry_order_status']	  			= 'Statut de Commande';
$_['entry_geo_zone']		  			= 'Zone Géographique';
$_['entry_status']			  			= 'Statut';
$_['entry_sort_order']		  			= 'Ordre de Tri';
$_['entry_debug']			  			= 'Journalisation de débogage';
$_['entry_card_save']			  		= 'Stocker les Cartes';
$_['entry_cron_token']	  				= 'Jeton Secret';
$_['entry_cron_url']	  				= 'URL';
$_['entry_cron_last_run']	 			= 'Dernière exécution :';

// Help
$_['help_total']			  			= 'Le total de la commande qui doit être atteint avant que ce mode de paiement devienne actif.';
$_['help_debug']			  			= 'Activer le débogage écrira des données sensibles dans un fichier journal. Vous devez toujours désactiver sauf instruction contraire.';
$_['help_transaction_method']  			= 'La méthode de transaction DOIT être définie sur Paiement pour permettre les paiements par abonnement.';
$_['help_card_save']			  		= 'Pour que l\'acheteur puisse enregistrer les détails de la carte pour les paiements ultérieurs, MID TOKEN doit être souscrit. Vous devez contacter le support client Opayo pour discuter de l\'activation du système de jetons pour votre compte.';
$_['help_cron_token']	  				= 'Rendez-le long et difficile à deviner.';
$_['help_cron_url']		  				= 'Configurez un cron pour appeler cette URL.';

// Button
$_['button_release']		  			= 'Libérer';
$_['button_rebate']			  			= 'Rembourser / rembourser';
$_['button_void']			  			= 'Annuler';
$_['button_enable_recurring']			= 'Activer Récurrent';
$_['button_disable_recurring']			= 'Désactiver Récurrent';

// Success
$_['success_save']		 				= 'Succès : Vous avez modifié Opayo !';
$_['success_release_ok']		  		= 'Succès : La libération a réussi !';
$_['success_release_ok_order']	  		= 'Succès : La libération a réussi, le statut de la commande a été mis à jour en succès - réglé !';
$_['success_rebate_ok']		  			= 'Succès : Le remboursement a réussi !';
$_['success_rebate_ok_order']	  		= 'Succès : Le remboursement a réussi, le statut de la commande a été mis à jour en remboursé !';
$_['success_void_ok']			  		= 'Succès : L\'annulation a réussi, le statut de la commande a été mis à jour en annulé !';
$_['success_enable_recurring']			= 'Succès : Le paiement récurrent a été activé !';
$_['success_disable_recurring']			= 'Succès : Le paiement récurrent a été désactivé !';

// Error
$_['error_warning']          			= 'Attention : Veuillez vérifier le formulaire pour les erreurs !';
$_['error_permission']		  			= 'Attention : Vous n\'avez pas la permission de modifier le paiement Opayo !';
$_['error_vendor']			  			= 'ID Vendeur requis !';