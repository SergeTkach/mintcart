<?php
// Heading
$_['heading_title']     = 'Storage';

// Text
$_['text_home']         = 'Home';
$_['text_intro']        = 'The storage directory holds the cache, logs, sessions, uploaded files and PHP libraries. It must not be reachable from the web, so it belongs next to your site folder, not inside it.';
$_['text_current']      = 'Current location';
$_['text_status']       = 'Status';
$_['text_inside']       = 'Inside the web root';
$_['text_inside_help']  = 'Anyone may be able to request these files. Move it.';
$_['text_outside']      = 'Outside the web root';
$_['text_outside_help'] = 'Nothing to do. You can still move it elsewhere.';
$_['text_move_title']   = 'Move the storage directory';
$_['text_move_help']    = 'Everything is copied first, the configuration is updated, and only then the old directory is removed. The new folder must not exist yet or must be empty, and its parent folder must be writable by the web server.';
$_['text_success']      = 'Storage moved to %s. You stay signed in.';

// Entry
$_['entry_path']        = 'New location';

// Button
$_['button_move']       = 'Move storage';

// Error
$_['error_permission']   = 'You do not have permission to move the storage directory.';
$_['error_invalid']      = 'Enter a full path, for example C:/www/storage or /home/user/storage.';
$_['error_exists']       = 'That folder already exists and is not empty.';
$_['error_not_writable'] = 'The parent folder does not exist or the web server cannot write to it.';
$_['error_config']       = 'The configuration files (config.php) are not writable, so nothing was moved.';
$_['error_copy']         = 'Copying failed. Nothing was changed.';
$_['error_failed']       = 'The storage directory was not moved.';
