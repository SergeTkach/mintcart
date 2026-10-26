<?php
// Heading
$_['heading_title']					 = 'Globalpay Remote';

// Text
$_['text_extension']				 = 'Extensions';
$_['text_success']					 = 'Succès : Vous avez modifié les détails du compte Globalpay !';
$_['text_edit']                      = 'Modifier Globalpay Remote';
$_['text_card_type']				 = 'Type de carte';
$_['text_enabled']					 = 'Activé';
$_['text_use_default']				 = 'Utiliser le défaut';
$_['text_merchant_id']				 = 'ID Marchand';
$_['text_subaccount']				 = 'Sous-compte';
$_['text_secret']					 = 'Secret partagé';
$_['text_card_visa']				 = 'Visa';
$_['text_card_master']				 = 'Mastercard';
$_['text_card_amex']				 = 'American Express';
$_['text_card_switch']				 = 'Switch/Maestro';
$_['text_card_laser']				 = 'Laser';
$_['text_card_diners']				 = 'Diners';
$_['text_capture_ok']				 = 'La capture a réussi';
$_['text_capture_ok_order']			 = 'La capture a réussi, le statut de la commande a été mis à jour en succès - réglé';
$_['text_rebate_ok']				 = 'Le remboursement a réussi';
$_['text_rebate_ok_order']			 = 'Le remboursement a réussi, le statut de la commande a été mis à jour en remboursé';
$_['text_void_ok']					 = 'L\'annulation a réussi, le statut de la commande a été mis à jour en annulé';
$_['text_settle_auto']				 = 'Automatique';
$_['text_settle_delayed']			 = 'Différé';
$_['text_settle_multi']				 = 'Multiple';
$_['text_ip_message']				 = 'Vous devez fournir l\'adresse IP de votre serveur à votre gestionnaire de compte Globalpay avant de passer en production.';
$_['text_payment_info']				 = 'Informations de paiement';
$_['text_capture_status']			 = 'Paiement capturé';
$_['text_void_status']				 = 'Paiement annulé';
$_['text_rebate_status']			 = 'Paiement remboursé';
$_['text_order_ref']				 = 'Réf. commande';
$_['text_order_total']				 = 'Total autorisé';
$_['text_total_captured']			 = 'Total capturé';
$_['text_transactions']				 = 'Transactions';
$_['text_confirm_void']				 = 'Êtes-vous sûr de vouloir annuler le paiement ?';
$_['text_confirm_capture']			 = 'Êtes-vous sûr de vouloir capturer le paiement ?';
$_['text_confirm_rebate']			 = 'Êtes-vous sûr de vouloir rembourser le paiement ?';
$_['text_globalpay_remote']			 = '<a target="_blank" href="https://resourcecentre.globaliris.com/getting-started.php?id=OpenCart"><img src="view/image/payment/globalpay.png" alt="Globalpay" title="Globalpay" style="border: 1px solid #EEEEEE;" /></a>';
$_['text_void']                      = 'Annuler';
$_['text_payment']                   = 'Paiement';
$_['text_rebate']                    = 'Remboursement';

// Column
$_['text_column_amount']			 = 'Montant';
$_['text_column_type']				 = 'Type';
$_['text_column_date_added']		 = 'Créé';

// Entry
$_['entry_merchant_id']				 = 'ID Marchand';
$_['entry_secret']					 = 'Secret partagé';
$_['entry_rebate_password']			 = 'Mot de passe de remboursement';
$_['entry_total']					 = 'Total';
$_['entry_sort_order']				 = 'Ordre de tri';
$_['entry_geo_zone']				 = 'Zone géographique';
$_['entry_status']					 = 'Statut';
$_['entry_debug']					 = 'Journalisation de débogage';
$_['entry_auto_settle']				 = 'Type de règlement';
$_['entry_tss_check']				 = 'Vérifications TSS';
$_['entry_card_data_status']		 = 'Journalisation des informations de carte';
$_['entry_3d']						 = 'Activer 3D Secure';
$_['entry_liability_shift']			 = 'Accepter les scénarios sans transfert de responsabilité';
$_['entry_status_success_settled']	 = 'Succès - réglé';
$_['entry_status_success_unsettled'] = 'Succès - non réglé';
$_['entry_status_decline']			 = 'Refus';
$_['entry_status_decline_pending']	 = 'Refus - authentification hors ligne';
$_['entry_status_decline_stolen']	 = 'Refus - carte perdue ou volée';
$_['entry_status_decline_bank']		 = 'Refus - erreur bancaire';
$_['entry_status_void']				 = 'Annulé';
$_['entry_status_rebate']			 = 'Remboursé';

// Help
$_['help_total']					 = 'Le total de la commande qui doit être atteint avant que ce mode de paiement devienne actif.';
$_['help_card_select']				 = 'Demandez à l\'utilisateur de choisir son type de carte avant d\'être redirigé.';
$_['help_notification']				 = 'Vous devez fournir cette URL à Globalpay pour recevoir les notifications de paiement.';
$_['help_debug']					 = 'Activer le débogage écrira des données sensibles dans un fichier journal. Vous devez toujours désactiver sauf instruction contraire.';
$_['help_liability']				 = 'Accepter la responsabilité signifie que vous continuerez à accepter les paiements lorsqu\'un utilisateur échoue à 3D Secure.';
$_['help_card_data_status']			 = 'Enregistre les 4 derniers chiffres de la carte, la date d\'expiration, le nom, le type et les informations de la banque émettrice.';

// Tab
$_['tab_api']					     = 'Détails API';
$_['tab_account']				     = 'Comptes';
$_['tab_order_status']				 = 'Statut de la commande';
$_['tab_payment']					 = 'Paramètres de paiement';

// Button
$_['button_capture']				 = 'Capturer';
$_['button_rebate']					 = 'Rembourser / rembourser';
$_['button_void']					 = 'Annuler';

// Error
$_['error_merchant_id']				 = 'L\'ID Marchand est requis';
$_['error_secret']					 = 'Le secret partagé est requis';