<?php
// Heading
$_['heading_title']                 = 'CardConnect';

// Tab
$_['tab_settings']                  = 'Paramètres';
$_['tab_order_status']              = 'Statut de Commande';

// Text
$_['text_extension']                = 'Extensions';
$_['text_success']                  = 'Succès : Vous avez modifié le module de paiement CardConnect !';
$_['text_edit']                     = 'Modifier CardConnect';
$_['text_cardconnect']              = '<a href="http://www.cardconnect.com" target="_blank"><img src="view/image/payment/cardconnect.png" alt="CardConnect" title="CardConnect"></a>';
$_['text_payment']                  = 'Paiement';
$_['text_refund']                   = 'Remboursement';
$_['text_authorize']                = 'Autoriser';
$_['text_live']                     = 'En direct';
$_['text_test']                     = 'Test';
$_['text_no_cron_time']             = 'Le cron n\'a pas encore été exécuté';
$_['text_payment_info']             = 'Informations de Paiement';
$_['text_payment_method']           = 'Méthode de Paiement';
$_['text_card']                     = 'Carte';
$_['text_echeck']                   = 'eCheck';
$_['text_reference']                = 'Référence';
$_['text_update']                   = 'Mettre à jour';
$_['text_order_total']              = 'Total de la Commande';
$_['text_total_captured']           = 'Total Capturé';
$_['text_capture_payment']          = 'Capturer le Paiement';
$_['text_refund_payment']           = 'Rembourser le Paiement';
$_['text_void']                     = 'Annuler';
$_['text_transactions']             = 'Transactions';
$_['text_column_type']              = 'Type';
$_['text_column_reference']         = 'Référence';
$_['text_column_amount']            = 'Montant';
$_['text_column_status']            = 'Statut';
$_['text_column_date_modified']     = 'Date de Modification';
$_['text_column_date_added']        = 'Date d\'ajout';
$_['text_column_update']            = 'Mettre à jour';
$_['text_column_void']              = 'Annuler';
$_['text_confirm_capture']          = 'Êtes-vous sûr de vouloir capturer le paiement ?';
$_['text_confirm_refund']           = 'Êtes-vous sûr de vouloir rembourser le paiement ?';
$_['text_inquire_success']          = 'La demande de renseignements a réussi';
$_['text_capture_success']          = 'La demande de capture a réussi';
$_['text_refund_success']           = 'La demande de remboursement a réussi';
$_['text_void_success']             = 'La demande d\'annulation a réussi';

// Entry
$_['entry_merchant_id']             = 'ID Marchand';
$_['entry_api_username']            = 'Nom d\'utilisateur API';
$_['entry_api_password']            = 'Mot de passe API';
$_['entry_token']                   = 'Jeton Secret';
$_['entry_transaction']             = 'Transaction';
$_['entry_environment']             = 'Environnement';
$_['entry_site']                    = 'Site';
$_['entry_store_cards']             = 'Stocker les Cartes';
$_['entry_echeck']                  = 'eCheck';
$_['entry_total']                   = 'Total';
$_['entry_geo_zone']                = 'Zone Géographique';
$_['entry_status']                  = 'Statut';
$_['entry_logging']                 = 'Journalisation de Débogage';
$_['entry_sort_order']              = 'Ordre de Tri';
$_['entry_cron_url']                = 'URL du Travail Cron';
$_['entry_cron_time']               = 'Dernière Exécution du Travail Cron';
$_['entry_order_status_pending']    = 'En attente';
$_['entry_order_status_processing'] = 'En traitement';

// Help
$_['help_merchant_id']              = 'Votre ID de marchand personnel CardConnect.';
$_['help_api_username']             = 'Votre nom d\'utilisateur pour accéder à l\'API CardConnect.';
$_['help_api_password']             = 'Votre mot de passe pour accéder à l\'API CardConnect.';
$_['help_token']                    = 'Entrez un jeton aléatoire par sécurité ou utilisez celui généré.';
$_['help_transaction']              = 'Choisissez "Paiement" pour capturer le paiement immédiatement ou "Autorisation" pour l\'approuver.';
$_['help_site']                     = 'Ceci détermine la première partie de l\'URL de l\'API. Ne modifiez que si on vous le demande.';
$_['help_store_cards']              = 'Si vous souhaitez stocker les cartes à l\'aide de la tokenisation.';
$_['help_echeck']                   = 'Si vous souhaitez offrir la possibilité de payer par eCheck.';
$_['help_total']                    = 'Le total de la commande qui doit être atteint avant que ce mode de paiement devienne actif. Doit être une valeur sans signe de devise.';
$_['help_logging']                  = 'Activer le débogage écrira des données sensibles dans un fichier journal. Vous devez toujours désactiver sauf instruction contraire.';
$_['help_cron_url']                 = 'Configurez un travail cron pour appeler cette URL afin que les commandes soient mises à jour automatiquement. Il est conçu pour être exécuté quelques heures après la dernière capture d\'un jour ouvrable.';
$_['help_cron_time']                = 'C\'est la dernière fois que l\'URL du travail cron a été exécutée.';
$_['help_order_status_pending']     = 'Le statut de la commande lorsque la commande doit être autorisée par le marchand.';
$_['help_order_status_processing']  = 'Le statut de la commande lorsque la commande est capturée automatiquement.';

// Button
$_['button_inquire_all']            = 'Tout Demander';
$_['button_capture']                = 'Capturer';
$_['button_refund']                 = 'Rembourser';
$_['button_void_all']               = 'Tout Annuler';
$_['button_inquire']                = 'Demander';
$_['button_void']                   = 'Annuler';

// Error
$_['error_permission']              = 'Attention : Vous n\'avez pas la permission de modifier le paiement CardConnect !';
$_['error_merchant_id']             = 'ID Marchand requis !';
$_['error_api_username']            = 'Nom d\'utilisateur API requis !';
$_['error_api_password']            = 'Mot de passe API requis !';
$_['error_token']                   = 'Jeton Secret requis !';
$_['error_site']                    = 'Site requis !';
$_['error_amount_zero']             = 'Le montant doit être supérieur à zéro !';
$_['error_no_order']                = 'Aucune information de commande correspondante !';
$_['error_invalid_response']        = 'Réponse invalide reçue !';
$_['error_data_missing']            = 'Données manquantes !';
$_['error_not_enabled']             = 'Module non activé !';