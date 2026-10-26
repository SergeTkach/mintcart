<?php
/**
 * @version		$Id: paypal.php 6867 2025-11-21 12:19:23Z mic $
 * @package		Language Translation German Backend
 * @author		mic - https://osworx.net
 * @copyright	2025 OSWorX
 * @license		GPL - www.gnu.org/copyleft/gpl.html
 */

// Text
$_['text_paypal']							= 'PayPal';
$_['text_paypal_fastlane']					= 'Kredit- & Debitkarten (Fastlane)';
$_['text_paypal_title']						= 'Bezahlen über PayPal';
$_['text_paypal_paylater_title']			= 'Jetzt kaufen und später mit PayPal bezahlen';
$_['text_paypal_googlepay_title']			= 'Google Pay';
$_['text_paypal_applepay_title']			= 'Apple Pay';
$_['text_paypal_fastlane_title']			= 'Kredit- & Debitkarten (Fastlane)';
$_['text_checkout_payment_address']  		= 'Rechnungsadresse';
$_['text_checkout_shipping_address'] 		= 'Lieferadresse';
$_['text_checkout_shipping_method']  		= 'Lieferart';
$_['text_checkout_payment_method']  		= 'Zahlungsart';
$_['text_your_details']						= 'Meine persönlichen Daten';
$_['text_your_address']						= 'Adresse';
$_['text_cart']								= 'Warenkorb';
$_['text_shipping_updated']					= 'Versandart aktualisiert';
$_['text_fastlane']  						= 'Fastlane';
$_['text_fastlane_shipping']  				= 'Versand';
$_['text_fastlane_payment']  				= 'Zahlung';
$_['text_day']								= 'Tag';
$_['text_week']								= 'Woche';
$_['text_semi_month']						= '14-Tägig';
$_['text_month']							= 'Monat';
$_['text_year']								= 'Jahr';
$_['text_card_number']          			= '%s endet in %s';
$_['text_trial']							= '%s alle %s %s für %s Zahlungen, dann ';
$_['text_recurring']						= '%s alle %s %s';
$_['text_recurring_item']					= 'Produkt';
$_['text_payment_recurring']				= 'Zahlungsprofil';
$_['text_trial_description']				= '%s alle %d %s(s) für %d Zahlung(en), dann';
$_['text_payment_description']				= '%s alle %d %s(s) für %d Zahlung(en)';
$_['text_payment_cancel']					= '%s alle %d %s(s) bis Storno';
$_['text_length']							= ' für %s Zahlungen';
$_['text_order_message']					= 'PayPal Käuferschutz - %s';
$_['text_wait']								= 'Bitte warten ..';
$_['text_loading']							= 'Lade ..';
$_['text_failure_page_title']				= 'Auftrag konnte nicht ausgeführt werden.';
$_['text_failure_page_message']				= 'Wir bedauern, aber die Zahlung konnte nicht ausgeführt werden.<br>Bitte eine andere Zahlungsart wählen oder <a href="%s" target="_blank">uns kontaktieren.</a>';

// Column
$_['column_image']							= 'Bild';
$_['column_name']							= 'Produkt';
$_['column_model']							= 'Art.Nr.';
$_['column_quantity']						= 'Menge';
$_['column_price']							= 'Einzelpreis';
$_['column_total']							= 'Gesamt';

// Entry
$_['entry_email']							= 'Email';
$_['entry_firstname']						= 'Vorname';
$_['entry_lastname']						= 'Nachname';
$_['entry_telephone']						= 'Telefon';
$_['entry_company']							= 'Firma';
$_['entry_address_1']						= 'Adresszeile 1';
$_['entry_address_2']						= 'Adresszeile 2';
$_['entry_postcode']						= 'Postleitzahl';
$_['entry_city']							= 'Stadt';
$_['entry_country']							= 'Land';
$_['entry_zone']							= 'Region';
$_['entry_card_number']						= 'Kartennummer';
$_['entry_expiration_date']					= 'Ablaufdatum';
$_['entry_cvv']								= 'CVV';
$_['entry_card_save']						= 'Kartendaten speichern';
$_['entry_shipping']						= 'Diese Bestellung erfordert einen Versand';


// Button
$_['button_continue']  						= 'Weiter';
$_['button_confirm']  						= 'Kostenpflichtig Kaufen';
$_['button_shipping'] 						= 'Versand aktualisieren';
$_['button_pay']							= 'Bezahlen mit Karte';
$_['button_edit']  							= 'Bearbeiten';

// Success
$_['success_order']		 					= 'Auftrag erfolgreich bearbeitet';

// Error
$_['error_warning']							= 'Bitte alle Eingaben auf Fehler überprüfen';
$_['error_stock']							= 'Produkte mit *** sind entweder in der gewünschten Menge nicht verfügbar oder lieferbar';
$_['error_minimum']							= 'Mindestbestellbetrag für %s beträgt %s';
$_['error_required']						= '%s erforderlich';
$_['error_product']							= 'Keine Produkte im Warenkorb';
$_['error_recurring_required']				= 'Bitte eine wiederkehrende Zahlung wählen (Abo)';
$_['error_unavailable']						= 'Bitte den normalen Kassabereich mit diesem Auftrag anwenden';
$_['error_shipping']						= 'Versandart erforderlich';
$_['error_no_shipping']						= 'Leider keine Versandoptionen verfügbar';
$_['error_firstname']						= 'Vorname muss zwischen 1 und 32 Zeichen lang sein';
$_['error_lastname']						= 'Nachname muss zwischen 1 und 32 Zeichen lang sein';
$_['error_email']							= 'Bitte eine gültige Emailadresse angeben';
$_['error_telephone']						= 'Telefonnummer muss zwischen 3 und 32 Zeichen lang sein';
$_['error_password']						= 'Passwort muss zwischen 4 und 20 Zeichen lang sein';
$_['error_confirm']							= 'Passwortwiederholung stimmt nicht überein';
$_['error_address_1']						= 'Adresszeile 1 muss zwischen 3 und 128 Zeichen lang sein';
$_['error_city']							= 'Stadt muss zwischen 2 und 128 Zeichen lang sein';
$_['error_postcode']						= 'Postleitzahl muss zwischen 2 und 10 Zeichen lang sein';
$_['error_country']							= 'Bitte ein Land auswählen';
$_['error_zone']							= 'Bitte eine Region auswählen';
$_['error_agree']							= 'Die <b>%s</b> müssen zur Kenntnis genommen werden';
$_['error_address']							= 'Eine Adresse muss ausgewählt werden';
$_['error_custom_field']					= '%s ist erforderlich';
$_['error_fastlane_billing_address']        = 'Versandadresse scheint nicht zu stimmen';
$_['error_order_voided']					= 'Zahlung konnte leider nicht durchgeführt werden, dieser Auftrag ist nicht gültig. Bitte uns <a href="%s" target="_blank">kontaktieren</a>.';
$_['error_order_completed']					= 'Zahlung konnte leider nicht abgeschlossen werden, Zahlung wurde genehmigt bzw. in Auftrag gegeben. Bitte uns <a href="%s" target="_blank">kontaktieren</a>.';
$_['error_authorization_captured']			= 'Zahlung konnte nicht durchgeführt werden da ein oder mehrere Gründe dagegen sprechen. Summe der angenommenen Zahlung ist größer als die genehmigte Originalsumme. Bitte uns <a href="%s" target="_blank">kontaktieren</a>.';
$_['error_authorization_denied']			= 'Transaktion konnte mit dieser Karte nicht durchgeführt werden - bitte eine andere Karte oder Zahlungsart verwenden.';
$_['error_authorization_expired']			= 'Transaktion konnte nicht duchgeführt werden da die Genehmigung abgelaufen ist. Bitte uns <a href="%s" target="_blank">kontaktieren</a>.';
$_['error_capture_declined']				= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden - bitte eine andere Karte oder Zahlungsart verwenden';
$_['error_capture_failed']					= 'Zahlung konnte nicht durchgeführt werden, es gab einen Fehler bei der Transaktion. Bitte uns <a href="%s" target="_blank">kontaktieren</a>.';
$_['error_3ds_failed_authentication']		= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. Entweder wurden die Anforderungen nicht erfüllt, oder sie ist nicht genehmigt';
$_['error_3ds_rejected_authentication']		= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden da die 3D-Secure Genehmigung abgebrochen wurde';
$_['error_3ds_attempted_authentication'] 	= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. Karte ist nicht für 3D-Secure zugelassen da die austellende Bank nicht daran teilnimmt';
$_['error_3ds_unable_authentication']		= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. Ausstellende Bank konnte Genehmigung nicht durchführen';
$_['error_3ds_challenge_authentication']	= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. Für die Genehmigung fehlt ein wichtiger Bestandteil';
$_['error_3ds_card_ineligible']				= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. Kartenart und ausstellende Bank sind nicht bereit für die 3D-Secure Genehmigung';
$_['error_3ds_system_unavailable']			= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden da ein Fehler mit dem 3D-Secure-Verfahren auftrat';
$_['error_3ds_system_bypassed'] 			= 'Transaktion mit dieser Karte konnte nicht durchgeführt werden. 3D-Secure-Verfahren wurde übergangen da ein wichtiger Teil fehlt';
$_['error_payment']							= 'Bitte entweder eine andere Zahlungsart auswählen oder <a href="%s" target="_blank">uns kontaktieren</a>.';
$_['error_timeout']							= 'Wir bedauern, aber PayPal ist aktuell überbelastet .. bitte später nochmal probieren. Vielen Dank.';

// older values < v.3.0.0
$_['text_title']					= 'PayPal (Express, Karte)';
$_['text_paypal_express']			= 'PayPal Express';
$_['text_paypal_card']				= 'PayPal Karte';

$_['error_3ds_error']				= 'Während der 3D-Secure-Genehmigung trat ein Fehler auf';
$_['error_3ds_skipped_by_buyer']	= '3D-Secure-Genehmigung wurde übersprungen';
$_['error_3ds_failure']				= 'Die Aufgabe wurde entweder nicht gelöst oder die Karte ist nicht genehmigt';
$_['error_3ds_undefined']			= 'Kartenaussteller benötigt kein 3D-Secure';
$_['error_3ds_bypassed']			= '3D-Secure wurde übersprungen';
$_['error_3ds_unavailable']			= 'Ausstellende Bank kann die Genehmigung nicht abschließen';
$_['error_3ds_attempted']			= 'Karte oder ausstellende Bank nimmt nicht am 3D-Secure Verfahren teil';
$_['error_3ds_card_ineligible']		= 'Karte kann nicht am 3D-Secure Verfahren teilnehmen';