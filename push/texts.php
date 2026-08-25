<?php
// Localized push texts. Content fields (news title/body) may be either a plain
// string or a language map like ['de' => ..., 'en' => ...]; generated texts
// ("Live soon" digests) come from the small translation tables below.
// Resolution order: subscription language → English → German → any value.

declare(strict_types=1);

// All visitor languages (code => native display name). Single source for the
// push layer, the subscribe endpoint and the CMS default-language dropdown.
// The admin/CMS UI itself stays four-language (de/en/fr/es).
const FESTIVADGET_LANGS = [
    'de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español',
    'it' => 'Italiano', 'nl' => 'Nederlands', 'cs' => 'Čeština', 'pl' => 'Polski',
    'pt' => 'Português', 'fi' => 'Suomi', 'hu' => 'Magyar', 'sk' => 'Slovenčina',
    'hr' => 'Hrvatski', 'da' => 'Dansk', 'sv' => 'Svenska', 'uk' => 'Українська',
    'ro' => 'Română', 'sl' => 'Slovenščina', 'tr' => 'Türkçe',
    'pt-BR' => 'Português (Brasil)', 'zh-CN' => '简体中文', 'ja-JP' => '日本語',
    'ko-KR' => '한국어', 'sq' => 'Shqip', 'af' => 'Afrikaans', 'el' => 'Ελληνικά',
    'hi' => 'हिन्दी', 'id' => 'Bahasa Indonesia', 'is' => 'Íslenska',
    'nb' => 'Norsk (bokmål)', 'ru' => 'Русский', 'th' => 'ไทย',
];
define('PUSH_LANGS', array_keys(FESTIVADGET_LANGS));

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
    'it' => ['Gleich live' => 'Tra poco dal vivo', 'Gleich: {name}' => 'A breve: {name}', 'Neuigkeit' => 'Novità'],
    'nl' => ['Gleich live' => 'Zo meteen live', 'Gleich: {name}' => 'Zo meteen: {name}', 'Neuigkeit' => 'Nieuws'],
    'cs' => ['Gleich live' => 'Brzy naživo', 'Gleich: {name}' => 'Brzy: {name}', 'Neuigkeit' => 'Novinka'],
    'pl' => ['Gleich live' => 'Wkrótce na żywo', 'Gleich: {name}' => 'Wkrótce: {name}', 'Neuigkeit' => 'Aktualność'],
    'pt' => ['Gleich live' => 'Em breve ao vivo', 'Gleich: {name}' => 'A seguir: {name}', 'Neuigkeit' => 'Novidade'],
    'fi' => ['Gleich live' => 'Pian livenä', 'Gleich: {name}' => 'Seuraavaksi: {name}', 'Neuigkeit' => 'Uutinen'],
    'hu' => ['Gleich live' => 'Hamarosan élőben', 'Gleich: {name}' => 'Hamarosan: {name}', 'Neuigkeit' => 'Hír'],
    'sk' => ['Gleich live' => 'Čoskoro naživo', 'Gleich: {name}' => 'Čoskoro: {name}', 'Neuigkeit' => 'Novinka'],
    'hr' => ['Gleich live' => 'Uskoro uživo', 'Gleich: {name}' => 'Uskoro: {name}', 'Neuigkeit' => 'Novost'],
    'da' => ['Gleich live' => 'Snart live', 'Gleich: {name}' => 'Snart: {name}', 'Neuigkeit' => 'Nyhed'],
    'sv' => ['Gleich live' => 'Snart live', 'Gleich: {name}' => 'Strax: {name}', 'Neuigkeit' => 'Nyhet'],
    'uk' => ['Gleich live' => 'Скоро наживо', 'Gleich: {name}' => 'Скоро: {name}', 'Neuigkeit' => 'Новина'],
    'ro' => ['Gleich live' => 'În curând live', 'Gleich: {name}' => 'Urmează: {name}', 'Neuigkeit' => 'Noutate'],
    'sl' => ['Gleich live' => 'Kmalu v živo', 'Gleich: {name}' => 'Kmalu: {name}', 'Neuigkeit' => 'Novica'],
    'tr' => ['Gleich live' => 'Birazdan sahnede', 'Gleich: {name}' => 'Sırada: {name}', 'Neuigkeit' => 'Haber'],
    'pt-BR' => ['Gleich live' => 'Em breve ao vivo', 'Gleich: {name}' => 'A seguir: {name}', 'Neuigkeit' => 'Novidade'],
    'zh-CN' => ['Gleich live' => '即将开演', 'Gleich: {name}' => '即将登场：{name}', 'Neuigkeit' => '新消息'],
    'ja-JP' => ['Gleich live' => 'まもなく開演', 'Gleich: {name}' => 'まもなく：{name}', 'Neuigkeit' => 'お知らせ'],
    'ko-KR' => ['Gleich live' => '곧 공연 시작', 'Gleich: {name}' => '곧 시작: {name}', 'Neuigkeit' => '새 소식'],
    'sq' => ['Gleich live' => 'Së shpejti live', 'Gleich: {name}' => 'Së shpejti: {name}', 'Neuigkeit' => 'Lajm'],
    'af' => ['Gleich live' => 'Binnekort regstreeks', 'Gleich: {name}' => 'Binnekort: {name}', 'Neuigkeit' => 'Nuus'],
    'el' => ['Gleich live' => 'Σε λίγο live', 'Gleich: {name}' => 'Σε λίγο: {name}', 'Neuigkeit' => 'Νέο'],
    'hi' => ['Gleich live' => 'जल्द ही लाइव', 'Gleich: {name}' => 'जल्द ही: {name}', 'Neuigkeit' => 'समाचार'],
    'id' => ['Gleich live' => 'Segera tampil', 'Gleich: {name}' => 'Segera: {name}', 'Neuigkeit' => 'Berita'],
    'is' => ['Gleich live' => 'Bráðum í beinni', 'Gleich: {name}' => 'Bráðum: {name}', 'Neuigkeit' => 'Frétt'],
    'nb' => ['Gleich live' => 'Snart live', 'Gleich: {name}' => 'Snart: {name}', 'Neuigkeit' => 'Nyhet'],
    'ru' => ['Gleich live' => 'Скоро на сцене', 'Gleich: {name}' => 'Скоро: {name}', 'Neuigkeit' => 'Новость'],
    'th' => ['Gleich live' => 'ใกล้เริ่มการแสดง', 'Gleich: {name}' => 'เร็ว ๆ นี้: {name}', 'Neuigkeit' => 'ข่าว'],
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
    foreach ($value as $text) {
        if (!empty($text)) {
            return (string) $text;
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
