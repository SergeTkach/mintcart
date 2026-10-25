<?php
// Heading
$_['heading_title']    = 'Ukrposhta';

// Text
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: you have modified Ukrposhta delivery!';
$_['text_edit']        = 'Edit Ukrposhta delivery';
$_['text_loading']     = 'Loading...';
$_['text_directory_empty']  = 'The list of cities and post offices has not been loaded yet.';
$_['text_directory_loaded'] = '%s post offices in %s cities. Updated: %s';
$_['text_directory_done']   = 'Loaded %b post offices in %c cities.';

// Entry
$_['entry_api_key']    = 'API key';
$_['entry_directory']  = 'Post offices';
$_['entry_free_from']  = 'Free delivery from';
$_['entry_status']     = 'Status';
$_['entry_sort_order'] = 'Sort order';

// Button
$_['button_sync']      = 'Update the list';

// Help
$_['help_api_key']     = 'The bearer token of your Ukrposhta account (ecom.ukrposhta.ua).';
$_['help_directory']   = 'Loads the cities and post offices from Ukrposhta, region by region. It takes a minute; run it once, then now and then to refresh.';
$_['help_free_from']   = 'Order total, in the default currency, from which delivery is marked free. Leave empty to always show "at the carrier\'s rates". The fee is paid to the carrier and is not added to the order.';

// Error
$_['error_permission'] = 'Warning: you do not have permission to modify Ukrposhta delivery!';
$_['error_api_key']    = 'An API key is required to enable this method.';
$_['error_free_from']  = 'Free delivery threshold must be a number.';
$_['error_api']        = 'Ukrposhta did not answer. Check the API key and try again.';
