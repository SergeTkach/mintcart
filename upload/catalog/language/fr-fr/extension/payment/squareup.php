<?php
// Text
$_['text_new_card']                     = '+ Ajouter une nouvelle carte';
$_['text_loading']                      = 'Chargement... Veuillez patienter...';
$_['text_card_details']                 = 'Détails de la Carte';
$_['text_saved_card']                   = 'Utiliser une Carte Enregistrée :';
$_['text_card_ends_in']                 = 'Payer avec la carte %s existante se terminant par XXXX XXXX XXXX %s';
$_['text_card_number']                  = 'Numéro de Carte :';
$_['text_card_expiry']                  = 'Expiration de la Carte (MM/AA) :';
$_['text_card_cvc']                     = 'Code de Sécurité de la Carte (CVC) :';
$_['text_card_zip']                     = 'Code Postal de la Carte :';
$_['text_card_save']                    = 'Enregistrer la carte pour une utilisation future ?';
$_['text_trial']                        = '%s tous les %s %s pendant %s paiements, puis ';
$_['text_recurring']                    = '%s tous les %s %s';
$_['text_length']                       = ' pendant %s paiements';
$_['text_cron_subject']                 = 'Résumé du travail CRON Square';
$_['text_cron_message']                 = 'Voici la liste de toutes les tâches CRON effectuées par votre extension Square :';
$_['text_squareup_profile_suspended']   = ' Vos paiements récurrents ont été suspendus. Veuillez nous contacter pour plus de détails.';
$_['text_squareup_trial_expired']       = ' Votre période d\'essai a expiré.';
$_['text_squareup_recurring_expired']   = ' Vos paiements récurrents ont expiré. C\'était votre dernier paiement.';
$_['text_cron_summary_token_heading']   = 'Actualisation du jeton d\'accès :';
$_['text_cron_summary_token_updated']   = 'Jeton d\'accès actualisé avec succès !';
$_['text_cron_summary_error_heading']   = 'Erreurs de Transaction :';
$_['text_cron_summary_fail_heading']    = 'Transactions Échouées (Profils Suspendus) :';
$_['text_cron_summary_success_heading'] = 'Transactions Réussies :';
$_['text_cron_fail_charge']             = 'Le profil <strong>#%s</strong> n\'a pas pu être débité de <strong>%s</strong>';
$_['text_cron_success_charge']          = 'Le profil <strong>#%s</strong> a été débité de <strong>%s</strong>';
$_['text_card_placeholder']             = 'XXXX XXXX XXXX XXXX';
$_['text_cvv']                          = 'CVV';
$_['text_expiry']                       = 'MM/AA';
$_['text_default_squareup_name']        = 'Carte de Crédit / Débit';
$_['text_token_issue_customer_error']   = 'Nous rencontrons une panne technique dans notre système de paiement. Veuillez réessayer plus tard.';
$_['text_token_revoked_subject']        = 'Votre jeton d\'accès Square a été révoqué !';
$_['text_token_revoked_message']        = "L'accès de l'extension de paiement Square à votre compte Square a été révoqué via le Tableau de Bord Square. Vous devez vérifier les identifiants de votre application dans les paramètres de l'extension et reconnecter.";
$_['text_token_expired_subject']        = 'Votre jeton d\'accès Square a expiré !';
$_['text_token_expired_message']        = "Le jeton d'accès de l'extension de paiement Square qui la connecte à votre compte Square a expiré. Vous devez vérifier les identifiants de votre application et le travail CRON dans les paramètres de l'extension et reconnecter.";

// Error
$_['error_browser_not_supported']       = 'Erreur : Le système de paiement ne prend plus en charge votre navigateur web. Veuillez le mettre à jour ou en utiliser un autre.';
$_['error_card_invalid']                = 'Erreur : La carte n\'est pas valide !';
$_['error_squareup_cron_token']         = 'Erreur : Le jeton d\'accès n\'a pas pu être actualisé. Veuillez connecter votre extension de paiement Square via le panneau d\'administration OpenCart.';

// Warning
$_['warning_test_mode']                 = 'Attention : Le mode bac à sable est activé ! Les transactions sembleront s\'effectuer, mais aucun frais ne sera prélevé.';

// Statuses
$_['squareup_status_comment_authorized']    = 'La transaction par carte a été autorisée mais pas encore capturée.';
$_['squareup_status_comment_captured']      = 'La transaction par carte a été autorisée puis capturée (c\'est-à-dire terminée).';
$_['squareup_status_comment_voided']        = 'La transaction par carte a été autorisée puis annulée (c\'est-à-dire annulée).   ';
$_['squareup_status_comment_failed']        = 'La transaction par carte a échoué.';

// Override errors
$_['squareup_override_error_billing_address.country']       = 'Le pays de l\'adresse de facturation n\'est pas valide. Veuillez le modifier et réessayer.';
$_['squareup_override_error_shipping_address.country']      = 'Le pays de l\'adresse de livraison n\'est pas valide. Veuillez le modifier et réessayer.';
$_['squareup_override_error_email_address']                 = 'L\'adresse e-mail de votre client n\'est pas valide. Veuillez la modifier et réessayer.';
$_['squareup_override_error_phone_number']                  = 'Le numéro de téléphone de votre client n\'est pas valide. Veuillez le modifier et réessayer.';
$_['squareup_error_field']                                  = ' Champ : %s';