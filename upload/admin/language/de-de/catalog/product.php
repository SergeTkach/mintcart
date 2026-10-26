<?php
/**
 * @version		$Id: product.php 6819 2025-04-08 09:31:14Z mic $
 * @package		Language Translation German Backend
 * @author		mic - https://osworx.net
 * @copyright	2025 OSWorX
 * @license		GPL - www.gnu.org/copyleft/gpl.html
 */

/** OSWorX override */
$_['tab_image']					= 'Bilder';

// Heading
$_['heading_title']				= 'Produkte';

// Text
$_['text_success']				= 'Produkt erfolgreich bearbeitet';
$_['text_list']					= 'Übersicht';
$_['text_add']					= 'Neu';
$_['text_edit']					= 'Bearbeiten';
$_['text_filter']				= 'Filter<br><br><i style="color:coral; font-size:smaller">(Tipp: % am Begin des Suchbegriffs anwenden)</i>';

// < 3.1.x
$_['text_plus']					= '+';
$_['text_minus']				= '-';
$_['text_default']				= 'Standard';
$_['text_option']				= 'Option';
$_['text_option_value']			= 'Optionswert';
$_['text_percent']				= 'Prozentsatz';
$_['text_amount']				= 'Fixbetrag';
$_['text_keyword']				= '<b style="color:lightcoral;">Keine Umlaute, Leerzeichen sowie Sonderzeichen (&%$ usw.) verwenden!</b><br />Statt Leerzeichen - oder _ (Binde-, Unterstrich) verwenden, Umlaute z.B. ä als ae eintragen.<br />Der Begriff muss einmalig sein, da ansonsten der Linkaufbau nicht mehr stimmt und ein Begriff auf mehrere zutreffen könnte!';

// Column
$_['column_name']				= 'Name';
$_['column_model']				= 'Art.Nr.';
$_['column_image']				= 'Bild';
$_['column_price']				= 'Nettopreis<br />Angebotspreis';
$_['column_quantity']			= 'Lagerstand';
$_['column_status']				= 'Status';
$_['column_action']				= 'Aktion';

// Entry
$_['entry_name']				= 'Name';
$_['entry_description']			= 'Beschreibung';
$_['entry_meta_title']			= 'Metatag Titel';
$_['entry_meta_keyword']		= 'Metatag Schlüsselwörter (4-5 Begriffe)';
$_['entry_meta_description']	= 'Metatag Beschreibung (max. 255 Zeichen)';
$_['entry_store']				= 'Geschäfte';
$_['entry_keyword']				= 'SEO-URL';
$_['entry_model']				= 'Artikelnr.';
$_['entry_sku']					= 'Bestandseinheit (SKU)';
$_['entry_upc']					= 'Strichcode UPC';
$_['entry_ean']					= 'Strichcode EAN (GTIN)';
$_['entry_jan']					= 'Strichcode JAN';
$_['entry_isbn']				= 'Strichcode ISBN';
$_['entry_mpn']					= 'Strichcode MPN';
$_['entry_location']			= 'Lagerort';
$_['entry_shipping']			= 'Erfordert Versand';
$_['entry_manufacturer']		= 'Hersteller';
$_['entry_date_available']		= 'Verfügbar ab';
$_['entry_quantity']			= 'Menge';
$_['entry_minimum']				= 'Min. Bestellmenge';
$_['entry_stock_status']		= 'Kein Lagerstand';
$_['entry_price']				= 'Nettopreis';
$_['entry_tax_class']			= 'Steuerklasse';
$_['entry_points']				= 'Punkte';
$_['entry_subtract']			= 'Von Lagerstand abziehen';
$_['entry_weight_class']		= 'Gewichtseinheit';
$_['entry_weight']				= 'Gewicht';
$_['entry_dimension']			= 'Abmessungen<br>(L x B x H)';
$_['entry_length_class']		= 'Längeneinheit';
$_['entry_length']				= 'Länge';
$_['entry_width']				= 'Breite';
$_['entry_height']				= 'Höhe';
$_['entry_image']				= 'Bild';
$_['entry_additional_image']	= 'Weitere Bilder';
$_['entry_customer_group']		= 'Kundengruppe';
$_['entry_date_start']			= 'Ab';
$_['entry_date_end']			= 'Bis';
$_['entry_priority']			= 'Reihenfolge';
$_['entry_attribute']			= 'Eigenschaft';
$_['entry_attribute_group']		= 'Eigenschaftsgruppe';
$_['entry_text']				= 'Text';
$_['entry_required']			= 'Benötigt';
$_['entry_status']				= 'Status';
$_['entry_sort_order']			= 'Reihenfolge';
$_['entry_category']			= 'Kategorien';
$_['entry_filter']				= 'Filter';
$_['entry_download']			= 'Downloads';
$_['entry_related']				= 'Ähnliche Produkte';
$_['entry_tag']					= 'Suchbegriffe';
$_['entry_reward']				= 'Bonuspunkte';
$_['entry_layout']				= 'Layout überschreiben';
$_['entry_recurring']			= 'Abonnement';

// < 3.1.x
$_['entry_option_points']		= 'Punkte';
$_['entry_option']				= 'Option';
$_['entry_option_value']		= 'Optionswert';

// Help
$_['help_sku']					= 'Artikelnummer wenn lagergeführt (versch. Längen - alphanumerisch)<br>Nur für interne Zwecke';
$_['help_upc']					= 'Universale Artikelnummer oder GTIN-12 [auch UPC-A] (12 Zahlen)<br>Verwendung hauptsächlich in Nordamerika';
$_['help_ean']					= 'Europäische Artikelnummer (EAN) oder auch seit 2009 GTIN (Global Trade Item Number)<br>Entweder EAN-13 (13-stelling) oder EAN-8 (8-stellig) angeben<br>EAN-8 = Anwendung z.B. bei kleinen Einheiten (Flasche)<hr><i>Es dürfen nur Zahlen verwendet werden!</i>';
$_['help_jan']					= 'Japanische Artikelnummer (8 oder 13 Ziffern)<br>Verwendung nur in Japan';
$_['help_isbn']					= 'Internationale Standard Buchnummer (13 Ziffern)';
$_['help_mpn']					= 'Hersteller Artikelnummer (versch. Längen - alphanumerisch))';
$_['help_manufacturer']			= 'Von welchem Hersteller ist dieser Artikel<br>(Begriff eintippen, System zeigt passende an)';
$_['help_minimum']				= 'Mindestmenge welche bestellt werden muss';
$_['help_stock_status']			= 'Angezeigter Status wenn Produkt nicht (mehr) lagernd ist';
$_['help_points']				= 'Anzahl Punkte die nötig sind um dieses Produkt zu kaufen.<br>0 (Null) bedeutet, dass das Produkt nicht mit Punkten gekauft werden kann';
$_['help_category']				= 'In welchen Kategorien wird dieser Artikel geführt<br>(Begriff eintippen, System zeigt passende an)';
$_['help_filter']				= 'Welche Filter können angewendet werden<br>(Begriff eintippen, System zeigt passende an)';
$_['help_download']				= 'Welche Dateien sind diesem Artikel als Download zugewiesen<br>(Begriff eintippen, System zeigt passende an)';
$_['help_related']				= 'Welche ähnlichen Artikel passen zu diesem Artikel<br>(Begriff eintippen, System zeigt passende an)';
$_['help_tag']					= 'Begriffe mit Komma trennen, keine Wiederholungen<br />Wichtigste Begriffe an den Anfang, am besten 4-5 Begriffe';

// Error
$_['error_warning']				= 'Erforderliche Daten nicht angegeben - bitte Felder überprüfen.';
$_['error_permission']			= 'Keine Rechte für diese Aktion';
$_['error_name']				= 'Produktname muss zwischen 1 und 255 Buchstaben lang sein';
$_['error_meta_title']			= 'Metatag Titel muss zwischen 1 und 255 Zeichen lang sein';
$_['error_model']				= 'Artikelnr. muss zwischen 1 und 64 Zeichen lang sein';
$_['error_seo']					= 'SEO URL muss einmalig sein';
$_['error_keyword']				= 'SEO URL wird bereits verwendet';
// < 3.1.x
$_['error_unique']				= 'SEO URL muss einmalig sein';

// MintCart
$_['entry_mc_badges'] = 'Badges';
$_['error_bulk_field'] = 'Achtung: Wählen Sie ein gültiges Feld für die Massenaktualisierung!';
$_['error_bulk_value'] = 'Achtung: Der Wert ist für das gewählte Feld nicht gültig!';
$_['error_manufacturer_picker_create'] = 'Geben Sie einen Namen (1–64 Zeichen) ein und versuchen Sie es erneut.';
$_['text_attribute_picker_browse'] = 'Durchsuchen';
$_['text_attribute_picker_confirm'] = 'Auswählen';
$_['text_attribute_picker_empty'] = 'Keine Attribute gefunden.';
$_['text_attribute_picker_next'] = 'Weiter';
$_['text_attribute_picker_prev'] = 'Zurück';
$_['text_attribute_picker_search'] = 'Attribute suchen';
$_['text_bulk_field_category'] = 'Kategorie';
$_['text_bulk_field_manufacturer'] = 'Hersteller';
$_['text_bulk_field_price'] = 'Preis';
$_['text_bulk_field_quantity'] = 'Menge';
$_['text_bulk_field_special'] = 'Sonderpreis';
$_['text_bulk_field_status'] = 'Status';
$_['text_bulk_field_stock_status'] = 'Lagerstatus';
$_['text_bulk_update'] = 'Massenaktualisierung';
$_['text_category_picker_browse'] = 'Kategorien durchsuchen';
$_['text_category_picker_confirm'] = 'Auswahl hinzufügen';
$_['text_category_picker_empty'] = 'Keine Kategorien gefunden.';
$_['text_category_picker_next'] = 'Weiter';
$_['text_category_picker_prev'] = 'Zurück';
$_['text_category_picker_search'] = 'Kategorien suchen';
$_['text_manufacturer_picker_browse'] = 'Hersteller durchsuchen';
$_['text_manufacturer_picker_confirm'] = 'Auswählen';
$_['text_manufacturer_picker_create'] = 'Neu hinzufügen';
$_['text_manufacturer_picker_create_placeholder'] = 'Name des neuen Herstellers';
$_['text_manufacturer_picker_empty'] = 'Keine Hersteller gefunden.';
$_['text_manufacturer_picker_next'] = 'Weiter';
$_['text_manufacturer_picker_prev'] = 'Zurück';
$_['text_manufacturer_picker_search'] = 'Hersteller suchen';
$_['text_mc_badges_manage'] = 'Badges verwalten';
$_['text_mc_badges_none'] = 'Noch keine Badges.';
$_['text_option_picker_browse'] = 'Optionen durchsuchen';
$_['text_option_picker_confirm'] = 'Option hinzufügen';
$_['text_option_picker_empty'] = 'Keine Optionen gefunden.';
$_['text_option_picker_next'] = 'Weiter';
$_['text_option_picker_prev'] = 'Zurück';
$_['text_option_picker_search'] = 'Optionen suchen';
$_['text_related_picker_browse'] = 'Produkte durchsuchen';
$_['text_related_picker_confirm'] = 'Auswahl hinzufügen';
$_['text_related_picker_empty'] = 'Keine Produkte gefunden.';
$_['text_related_picker_next'] = 'Weiter';
$_['text_related_picker_prev'] = 'Zurück';
$_['text_related_picker_search'] = 'Produkte suchen';
