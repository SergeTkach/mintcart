<?php
// Heading
$_['heading_title']					 = 'First Data EMEA Web Service API';

// Text
$_['text_firstdata_remote']			 = '<img src="view/image/payment/firstdata.png" alt="First Data" title="First Data" style="border: 1px solid #EEEEEE;" />';
$_['text_extension']					 = 'Extensions';
$_['text_success']					 = 'Succès : Vous avez modifié les détails du compte First Data !';
$_['text_edit']                      = 'Modifier First Data EMEA Web Service API';
$_['text_card_type']				 = 'Type de carte';
$_['text_enabled']					 = 'Activé';
$_['text_merchant_id']				 = 'ID de la Boutique';
$_['text_subaccount']				 = 'Sous-compte';
$_['text_user_id']					 = 'ID Utilisateur';
$_['text_capture_ok']				 = 'La capture a réussi';
$_['text_capture_ok_order']			 = 'La capture a réussi, le statut de la commande a été mis à jour en succès - réglé';
$_['text_refund_ok']				 = 'Le remboursement a réussi';
$_['text_refund_ok_order']			 = 'Le remboursement a réussi, le statut de la commande a été mis à jour en remboursé';
$_['text_void_ok']					 = 'L\'annulation a réussi, le statut de la commande a été mis à jour en annulé';
$_['text_settle_auto']				 = 'Vente';
$_['text_settle_delayed']			 = 'Préautorisation';
$_['text_mastercard']				 = 'Mastercard';
$_['text_visa']						 = 'Visa';
$_['text_diners']					 = 'Diners';
$_['text_amex']						 = 'American Express';
$_['text_maestro']					 = 'Maestro';
$_['text_payment_info']				 = 'Informations de paiement';
$_['text_capture_status']			 = 'Paiement capturé';
$_['text_void_status']				 = 'Paiement annulé';
$_['text_refund_status']			 = 'Paiement remboursé';
$_['text_order_ref']				 = 'Réf. commande';
$_['text_order_total']				 = 'Total autorisé';
$_['text_total_captured']			 = 'Total capturé';
$_['text_transactions']				 = 'Transactions';
$_['text_column_amount']			 = 'Montant';
$_['text_column_type']				 = 'Type';
$_['text_column_date_added']		 = 'Créé';
$_['text_confirm_void']				 = 'Êtes-vous sûr de vouloir annuler le paiement ?';
$_['text_confirm_capture']			 = 'Êtes-vous sûr de vouloir capturer le paiement ?';
$_['text_confirm_refund']			 = 'Êtes-vous sûr de vouloir rembourser le paiement ?';
$_['text_void']                      = 'Annuler';
$_['text_payment']                   = 'Paiement';
$_['text_refund']                    = "Remboursement";

// Entry
$_['entry_certificate_path']		 = 'Chemin du certificat';
$_['entry_certificate_key_path']	 = 'Chemin de la clé privée';
$_['entry_certificate_key_pw']		 = 'Mot de passe de la clé privée';
$_['entry_certificate_ca_path']		 = 'Chemin CA';
$_['entry_merchant_id']				 = 'ID de la Boutique';
$_['entry_user_id']					 = 'ID Utilisateur';
$_['entry_password']				 = 'Mot de passe';
$_['entry_total']					 = 'Total';
$_['entry_sort_order']				 = 'Ordre de tri';
$_['entry_geo_zone']				 = 'Zone géographique';
$_['entry_status']					 = 'Statut';
$_['entry_debug']					 = 'Journalisation de débogage';
$_['entry_auto_settle']				 = 'Type de règlement';
$_['entry_status_success_settled']	 = 'Succès - réglé';
$_['entry_status_success_unsettled'] = 'Succès - non réglé';
$_['entry_status_decline']			 = 'Refus';
$_['entry_status_void']				 = 'Annulé';
$_['entry_status_refund']			 = 'Remboursé';
$_['entry_enable_card_store']		 = 'Activer les jetons de stockage de cartes';
$_['entry_cards_accepted']			 = 'Types de cartes acceptés';

// Help
$_['help_total']					 = 'Le total de la commande qui doit être atteint avant que ce mode de paiement devienne actif.';
$_['help_certificate']				 = 'Les certificats et les clés privées doivent être stockés en dehors de vos dossiers web publics.';
$_['help_card_select']				 = 'Demandez à l\'utilisateur de choisir son type de carte avant d\'être redirigé.';
$_['help_notification']				 = 'Vous devez fournir cette URL à First Data pour recevoir les notifications de paiement.';
$_['help_debug']					 = 'Activer le débogage écrira des données sensibles dans un fichier journal. Vous devez toujours désactiver sauf instruction contraire.';
$_['help_settle']					 = 'Si vous utilisez une préautorisation, vous devez effectuer une action de post-autorisation dans les 3 à 5 jours, sinon votre transaction sera abandonnée.';

// Tab
$_['tab_account']					 = 'Informations API';
$_['tab_order_status']				 = 'Statut de la commande';
$_['tab_payment']					 = 'Paramètres de paiement';

// Button
$_['button_capture']				 = 'Capturer';
$_['button_refund']					 = 'Rembourser';
$_['button_void']					 = 'Annuler';

// Error
$_['error_merchant_id']				 = 'L\'ID de la boutique est requis';
$_['error_user_id']					 = 'L\'ID Utilisateur est requis';
$_['error_password']				 = 'Le mot de passe est requis';
$_['error_certificate']				 = 'Le chemin du certificat est requis';
$_['error_key']						 = 'La clé du certificat est requise';
$_['error_key_pw']					 = 'Le mot de passe de la clé du certificat est requis';
$_['error_ca']						 = 'L\'Autorité de Certification (CA) est requise';