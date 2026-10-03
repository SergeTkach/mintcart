<?php
// Heading
$_['heading_title'] = 'Compatibility Checker';

// Text
$_['text_home']       = 'Home';
$_['text_intro']      = 'Paste the contents of an extension\'s install.xml (OCMOD) to see which files, controllers and templates it modifies, which look like they assume Bootstrap 3 markup, and which collide with a file MintCart\'s own core already modifies. This is a heuristic warning only - it never blocks or performs the real install.';
$_['text_no_results'] = 'No files found in this install.xml.';

$_['text_type_controller'] = 'Controller';
$_['text_type_template']   = 'Template';
$_['text_type_other']      = 'Other';
$_['text_flag_theme']      = 'Theme file';
$_['text_flag_conflict']   = 'Potential conflict';

$_['text_summary']             = 'Summary';
$_['text_summary_files']       = 'files';
$_['text_summary_controllers'] = 'controllers';
$_['text_summary_templates']   = 'templates';
$_['text_summary_theme']       = 'theme files';
$_['text_summary_bootstrap3']  = 'Bootstrap 3 assumptions';
$_['text_summary_conflicts']   = 'potential conflicts';

$_['text_bootstrap3_heading'] = 'Files that look like they assume Bootstrap 3 markup:';
$_['text_conflicts_heading']  = 'Files that may conflict with a file MintCart\'s own core already modifies:';

// Column
$_['column_path']  = 'File';
$_['column_type']  = 'Type';
$_['column_flags'] = 'Flags';

// Entry
$_['entry_xml'] = 'install.xml contents';

// Button
$_['button_check'] = 'Check';

// Error
$_['error_permission'] = 'Warning: You do not have permission to access the Compatibility Checker!';
$_['error_empty']      = 'Warning: Paste the install.xml contents to analyze.';
