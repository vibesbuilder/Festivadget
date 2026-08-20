<?php
// Localized push texts. Content fields (news title/body) may be either a plain
// string or a language map like ['de' => ..., 'en' => ...]; generated texts
// ("Live soon" digests) come from the small translation tables below.
// Resolution order: subscription language → English → German → any value.

declare(strict_types=1);

const PUSH_LANGS = ['de', 'en', 'fr', 'es'];

const PUSH_TEXTS = [
    'en' => [
        'Gleich live'    => 'Live soon',
        'Gleich: {name}' => 'Up next: {name}',
        'Neuigkeit'      => 'News',
    ],
    'fr' => [
        'Gleich live'    => 'Bientôt en live',
        'Gleich: {name}' => 'Bientôt : {name}',
        'Neuigkeit'      => 'Actualité',
    ],
    'es' => [
        'Gleich live'    => 'Pronto en directo',
        'Gleich: {name}' => 'Pronto: {name}',
        'Neuigkeit'      => 'Novedad',
    ],
];

/** Translate a generated push text (German is the key language, en fallback). */
function push_tr(string $lang, string $text, array $params = []): string
{
    if ($lang !== 'de') {
        $out = PUSH_TEXTS[$lang][$text] ?? PUSH_TEXTS['en'][$text] ?? $text;
    } else {
        $out = $text;
    }
    foreach ($params as $key => $value) {
        $out = str_replace('{' . $key . '}', (string) $value, $out);
    }
    return $out;
}

/** Resolve a localized content field (string or language map) for one language. */
function push_localize($value, string $lang): string
{
    if (!is_array($value)) {
        return (string) ($value ?? '');
    }
    foreach ([$lang, 'en', 'de', 'fr', 'es'] as $candidate) {
        if (!empty($value[$candidate])) {
            return (string) $value[$candidate];
        }
    }
    return '';
}

/** Instance default language for subscriptions without a stored language. */
function push_default_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $cfgFile = push_config()['dataDir'] . '/app-config.json';
        $appCfg  = is_file($cfgFile)
            ? (json_decode((string) file_get_contents($cfgFile), true) ?: [])
            : [];
        $candidate = (string) ($appCfg['languageDefault'] ?? '');
        $lang = in_array($candidate, PUSH_LANGS, true) ? $candidate : 'en';
    }
    return $lang;
}

/** Group subscription rows by their effective language. */
function push_rows_by_lang(array $rows): array
{
    $default = push_default_lang();
    $groups  = [];
    foreach ($rows as $row) {
        $lang = (string) ($row['lang'] ?? '');
        if (!in_array($lang, PUSH_LANGS, true)) {
            $lang = $default;
        }
        $groups[$lang][] = $row;
    }
    return $groups;
}
