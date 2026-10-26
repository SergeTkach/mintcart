<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Default alias for the Customer reviews page (`/reviews`), added after the first
 * set of system-page aliases (202610221300). Same rules: per language, skipped when
 * the route already has an alias or the keyword is taken. Inert until SEO URLs are on.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $prefix = DB_PREFIX;
        $languages = $db->query("SELECT `language_id` FROM `{$prefix}language`");

        foreach ($languages->rows as $language) {
            $languageId = (int) $language['language_id'];

            $taken = $db->query("SELECT `query`, `keyword` FROM `{$prefix}seo_url` WHERE `store_id` = '0' AND `language_id` = '{$languageId}'");

            foreach ($taken->rows as $row) {
                if ($row['query'] === 'mintcart/reviews' || $row['keyword'] === 'reviews') {
                    continue 2;
                }
            }

            $db->query("INSERT INTO `{$prefix}seo_url` SET `store_id` = '0', `language_id` = '{$languageId}', `query` = 'mintcart/reviews', `keyword` = 'reviews'");
        }
    }
};
