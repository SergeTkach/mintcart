<?php
namespace MintCart\Seo;

/**
 * SEO URL keywords from names: `Чашка "Мармурова" & Co` -> `chashka-marmurova-co`.
 *
 * Ukrainian and Russian are transliterated to Latin (the official Ukrainian table,
 * Cabinet of Ministers resolution No. 55 of 2010, with the Russian letters added); common
 * Latin diacritics are folded (é -> e, ß -> ss, ł -> l). Anything else that is not a
 * letter or digit becomes a separator. Pure: no OpenCart dependency.
 */
final class Slugger
{
    private const MAX_LENGTH = 100;

    /** Letters the same in Ukrainian and Russian. */
    private const COMMON = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
        'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ъ' => '', 'ю' => 'yu', 'я' => 'ya',
        'ґ' => 'g', 'є' => 'ye', 'ї' => 'yi', 'ё' => 'yo', 'э' => 'e', 'ы' => 'y',
    ];

    /** char => [at the start of a word, inside a word] */
    private const UK_INITIAL = [
        'є' => ['ye', 'ie'], 'ї' => ['yi', 'i'], 'й' => ['y', 'i'], 'ю' => ['yu', 'iu'], 'я' => ['ya', 'ia'],
    ];

    private const LATIN = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ğ' => 'g', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ı' => 'i', 'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ő' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'ś' => 's', 'š' => 's',
        'ş' => 's', 'ť' => 't', 'ţ' => 't', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u',
        'ű' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
    ];

    /**
     * @param string $language language code hint (`uk-ua`, `ru-ru`, '' = guess from the letters)
     */
    public static function slug(string $text, string $language = ''): string
    {
        $text = html_entity_decode(strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
        $text = mb_strtolower($text, 'UTF-8');

        $ukrainian = self::isUkrainian($text, $language);
        $out = '';
        $previous = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            // The Ukrainian apostrophe (п'ять) is dropped, not a separator, and does not start a new word.
            if ($char === "'" || $char === '’' || $char === 'ʼ') {
                $previous = "'";

                continue;
            }

            $initial = $previous === '' || preg_match("/[\p{L}\p{N}']/u", $previous) !== 1;

            if ($char === 'г') {
                $out .= $ukrainian ? 'h' : 'g';
            } elseif ($char === 'и') {
                $out .= $ukrainian ? 'y' : 'i';
            } elseif ($char === 'і') {
                $out .= 'i';
            } elseif ($ukrainian && isset(self::UK_INITIAL[$char])) {
                // KMU 2010: є ї й ю я are ye yi y yu ya at the start of a word, ie i i iu ia inside it.
                $out .= $initial ? self::UK_INITIAL[$char][0] : self::UK_INITIAL[$char][1];
            } elseif (isset(self::COMMON[$char])) {
                $out .= self::COMMON[$char];
            } elseif (isset(self::LATIN[$char])) {
                $out .= self::LATIN[$char];
            } else {
                $out .= $char;
            }

            $previous = $char;
        }

        $out = (string) preg_replace('/[^a-z0-9]+/', '-', $out);
        $out = trim($out, '-');

        if (strlen($out) > self::MAX_LENGTH) {
            $out = substr($out, 0, self::MAX_LENGTH);
            $cut = strrpos($out, '-');
            $out = $cut !== false && $cut > self::MAX_LENGTH * 0.6 ? substr($out, 0, $cut) : $out;
            $out = trim($out, '-');
        }

        return $out;
    }

    /**
     * The first free keyword: `base`, `base-2`, `base-3` ...
     *
     * @param callable(string): bool $taken
     */
    public static function unique(string $base, callable $taken): string
    {
        if ($base === '') {
            return '';
        }

        $keyword = $base;

        for ($i = 2; $taken($keyword) && $i < 1000; $i++) {
            $keyword = $base . '-' . $i;
        }

        return $keyword;
    }

    private static function isUkrainian(string $text, string $language): bool
    {
        $code = strtolower(substr($language, 0, 2));

        if ($code === 'uk') {
            return true;
        }

        if ($code === 'ru') {
            return false;
        }

        return preg_match('/[іїєґ]/u', $text) === 1;
    }
}
