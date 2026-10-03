<?php
// Heading
$_['heading_title']       = 'Homepage';

// Text
$_['text_success']        = 'Success: You have modified the homepage!';
$_['text_list']           = 'Homepage Blocks';
$_['text_add']            = 'Add Block';
$_['text_edit']           = 'Edit Block';
$_['text_home_page_name'] = 'Homepage';
$_['text_no_results']     = 'No blocks yet — add one to start building the homepage.';
$_['text_drag_hint']      = 'Drag a block (or use the Move Up/Move Down buttons) to reorder, then click Save Order.';
$_['text_drag_handle']    = 'Drag to reorder';
$_['text_move_up']        = 'Move up';
$_['text_move_down']      = 'Move down';
$_['text_confirm']        = 'Are you sure you want to delete the selected block(s)?';
$_['text_settings_suffix'] = 'settings';

// Block types
$_['text_type_products']     = 'Products';
$_['text_type_hero']         = 'Hero';
$_['text_type_categories']   = 'Categories';
$_['text_type_banner_grid']  = 'Banner Grid';
$_['text_type_benefits']     = 'Benefits';
$_['text_type_faq']          = 'FAQ';
$_['text_type_testimonials'] = 'Testimonials';
$_['text_type_brands']       = 'Brands';
$_['text_type_text_image']   = 'Text / Image';
$_['text_type_cta']          = 'CTA';
$_['text_type_newsletter']   = 'Newsletter';
$_['text_type_spacer']       = 'Spacer';
$_['text_type_text']         = 'Text';
$_['text_type_html']         = 'HTML';
$_['text_type_gallery']      = 'Gallery';

// Column
$_['column_type']         = 'Type';
$_['column_title']        = 'Title';
$_['column_status']       = 'Status';
$_['column_sort_order']   = 'Sort Order';
$_['column_action']       = 'Action';

// Entry
$_['entry_type']            = 'Block Type';
$_['entry_status']          = 'Status';
$_['entry_title']           = 'Title';
$_['entry_category_id']     = 'Category ID';
$_['entry_limit']           = 'Limit';
$_['entry_sort']            = 'Sort';
$_['entry_order']           = 'Order';
$_['entry_image']           = 'Image';
$_['entry_image_width']     = 'Image Width';
$_['entry_image_height']    = 'Image Height';
$_['entry_image_alt']       = 'Image Alt Text';
$_['entry_image_position']  = 'Image Position';
$_['entry_subtitle']        = 'Subtitle';
$_['entry_cta_label']       = 'Button Label';
$_['entry_cta_url']         = 'Button URL';
$_['entry_parent_id']       = 'Parent Category ID';
$_['entry_columns']         = 'Columns';
$_['entry_action']          = 'Form Action URL';
$_['entry_method']          = 'Form Method';
$_['entry_placeholder']     = 'Email Placeholder';
$_['entry_submit_label']    = 'Submit Button Label';
$_['entry_size']            = 'Size';
$_['entry_text_align']      = 'Text Alignment';
$_['entry_text']            = 'Text';
$_['entry_body']            = 'Body';
$_['entry_content_html']    = 'Content (HTML)';
$_['entry_html']            = 'HTML';
$_['entry_banner_items']      = 'Items (JSON)';
$_['entry_benefit_items']     = 'Items (JSON)';
$_['entry_testimonial_items'] = 'Items (JSON)';
$_['entry_brand_items']       = 'Items (JSON)';
$_['entry_faq_items']         = 'Items (JSON)';
$_['entry_gallery_items']     = 'Items (JSON)';

// Help
$_['help_category_id']  = 'Leave blank to show products from any category.';
$_['help_parent_id']    = 'Leave at 0 to show top-level categories.';
$_['help_image']        = 'Path relative to image/, e.g. catalog/banner1.jpg. There is no image picker on this screen yet - upload the file first (e.g. via Catalog > Products\' image manager) and type its path here.';
$_['help_body']         = 'Basic HTML is allowed and rendered as-is (rich text editor not wired up yet).';
$_['help_html']         = 'Raw HTML, rendered as-is - only paste markup you trust (embed codes, etc.).';
$_['help_action']       = 'Leave blank to hide the subscribe form and show only the title/text as an announcement.';
$_['help_type_change']  = 'Changing the type reloads the form with that type\'s own fields - save again to apply it.';
$_['help_banner_items_settings']      = 'One object per banner. Example: [{"image": "catalog/banner1.jpg", "url": "index.php?route=product/category&path=20", "image_width": 600, "image_height": 400}]';
$_['help_banner_items_content']       = 'Same order as the Items list in Settings. Example: [{"alt": "Summer sale", "caption": "Up to 50% off"}]';
$_['help_benefit_items_settings']     = 'One object per item. Example: [{"icon": "truck"}, {"icon": "shield-check"}]';
$_['help_benefit_items_content']      = 'Same order as the Items list in Settings. Example: [{"title": "Free shipping", "description": "On orders over $50"}]';
$_['help_testimonial_items_settings'] = 'One object per testimonial. Example: [{"avatar": "catalog/jane.jpg", "avatar_width": 64, "avatar_height": 64, "rating": 5}]';
$_['help_testimonial_items_content']  = 'Same order as the Items list in Settings. Example: [{"quote": "Great store!", "author_name": "Jane Doe", "author_role": "Verified buyer"}]';
$_['help_brand_items_settings']       = 'One object per logo. Example: [{"image": "catalog/brand1.png", "url": "https://example.com", "image_width": 160, "image_height": 80}]';
$_['help_brand_items_content']        = 'Same order as the Items list in Settings. Example: [{"alt": "Acme Inc."}]';
$_['help_faq_items']                  = 'Example: [{"question": "Do you ship internationally?", "answer": "Yes, worldwide."}]';
$_['help_gallery_items_settings']     = 'One object per photo. Example: [{"image": "catalog/photo1.jpg", "image_width": 600, "image_height": 600}]';
$_['help_gallery_items_content']      = 'Same order as the Items list in Settings. Example: [{"alt": "Store front", "caption": "Our flagship location"}]';

// Button
$_['button_save_order']   = 'Save Order';

// Error
$_['error_permission']    = 'Warning: You do not have permission to modify the homepage!';
$_['error_type']          = 'Please select a valid block type!';
$_['error_json']          = 'One of the Items (JSON) fields is not valid JSON - please fix it and save again.';
