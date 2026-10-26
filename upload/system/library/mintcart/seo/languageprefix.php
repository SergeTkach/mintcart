<?php
namespace MintCart\Seo;

/**
 * Language folder in the URL: `/uk/kategoriya` for a non-default language,
 * `/kategoriya` for the default one. Pure URL logic, no OpenCart dependency;
 * the startup controllers `startup/mc_language_prefix` (incoming) and
 * `startup/mc_language_url` (outgoing) do the wiring.
 *
 * The folder is the language code before the dash (`uk-ua` => `uk`); when two
 * enabled languages share that part (`en-gb`, `en-us`) the full code is used
 * for them, so the folder is always unambiguous without a new setting.
 */
final class LanguagePrefix
{
    /** @var array<string, string> language code => folder */
    private array $folders = [];

    private string $default;

    /**
     * @param list<string> $codes enabled OpenCart language codes
     */
    public function __construct(array $codes, string $default)
    {
        $this->default = $default;

        $short = [];

        foreach ($codes as $code) {
            $short[$code] = strtolower(explode('-', $code)[0]);
        }

        $count = array_count_values($short);

        foreach ($codes as $code) {
            $this->folders[$code] = $count[$short[$code]] > 1 ? strtolower($code) : $short[$code];
        }
    }

    /** The folder links of this language carry; '' for the default language. */
    public function folder(string $code): string
    {
        return $code === $this->default ? '' : ($this->folders[$code] ?? '');
    }

    /**
     * Splits the leading language folder off a request route (`uk/foo/bar`).
     *
     * @return array{code: ?string, rest: string} code is null when the first
     *         segment is not a language folder (the default language's own
     *         folder is recognised too, so the caller can redirect it away)
     */
    public function split(string $route): array
    {
        $parts = explode('/', $route, 2);

        foreach ($this->folders as $code => $folder) {
            if ($parts[0] !== '' && strtolower($parts[0]) === $folder) {
                return ['code' => $code, 'rest' => $parts[1] ?? ''];
            }
        }

        return ['code' => null, 'rest' => $route];
    }

    /**
     * Puts the language folder right after the store's base URL.
     *
     * @param list<string> $bases the store's http and https base URLs
     */
    public function link(string $link, array $bases, string $code): string
    {
        $folder = $this->folder($code);

        if ($folder === '') {
            return $link;
        }

        foreach ($bases as $base) {
            if ($base !== '' && strpos($link, $base) === 0) {
                return $base . $folder . '/' . substr($link, strlen($base));
            }
        }

        return $link;
    }
}
