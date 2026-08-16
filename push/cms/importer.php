<?php
// Server-Importer: zieht je Domäne aus Joomla/WordPress und schreibt
// data/app-<domain>.json (vom Phase-5-Override live übernommen).
//
// HINWEIS: Best-effort *generisches* Mapping (id/slug/title/name/body). Für
// reine Text-Inhalte (Infos) passt das gut; strukturierte Domänen (Artists,
// Slots, News mit Kategorie/Zeit) brauchen i. d. R. eine Nachbearbeitung im
// „Inhalte"-Tab bzw. ein Feinmapping gegen das konkrete CMS. Verbindung
// (baseUrl/Token) in push/config.php → 'sources'. Steuert NUR den Server-Import,
// unabhängig von content-sources.config.ts (lokaler Build-Import).

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

function cms_source_config(): array
{
    return cms_read_json('source-config.json') ?? [];
}

function cms_http_get(string $url, array $headers): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('curl-Erweiterung nicht verfügbar.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException("Netzwerkfehler: $err");
    }
    if ($code >= 400) {
        throw new RuntimeException("HTTP $code");
    }
    return (string) $res;
}

function cms_strip_html(string $html): string
{
    // Block-Enden → Absatz, <br> → Zeilenumbruch, dann Tags entfernen.
    $t = preg_replace('/<\s*\/(p|div|h[1-6]|li)\s*>/i', "\n\n", $html) ?? $html;
    $t = preg_replace('/<\s*br\s*\/?>/i', "\n", $t) ?? $t;
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/[ \t]+\n/', "\n", $t) ?? $t;     // trailing spaces je Zeile
    $t = preg_replace('/\n{3,}/', "\n\n", $t) ?? $t;     // max. eine Leerzeile
    $t = trim($t);
    // Absicherung: ungültiges UTF-8 bereinigen (sonst kann json_encode scheitern)
    // und Länge begrenzen (verhindert Speicher-/Renderprobleme bei Riesen-Importen).
    if (function_exists('mb_substr')) {
        if (!mb_check_encoding($t, 'UTF-8')) {
            $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
        }
        if (mb_strlen($t) > 20000) {
            $t = mb_substr($t, 0, 20000) . ' …';
        }
    } elseif (strlen($t) > 40000) {
        $t = substr($t, 0, 40000);
    }
    return $t;
}

// --- HTML erhalten + sanitizen (Bilder, iframes von erlaubten Hosts, Überschriften) ---

const CMS_HTML_KEEP    = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'u', 'a', 'img', 'iframe', 'blockquote'];
const CMS_HTML_DROP    = ['script', 'style', 'link', 'meta', 'noscript', 'head', 'title'];
const CMS_IFRAME_HOSTS = ['youtube.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'open.spotify.com'];

/** Erlaubter iframe-Host? Exakte Liste ODER Google Maps in allen Varianten. */
function cms_iframe_host_ok(string $host): bool
{
    return in_array($host, CMS_IFRAME_HOSTS, true)
        || (bool) preg_match('#^(www\.|maps\.)?google\.(com|at|de|ch)$#', $host);
}
const CMS_HTML_ATTRS   = [
    'a'      => ['href'],
    'img'    => ['src', 'alt'],
    'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'loading', 'title', 'referrerpolicy'],
];

/** Bereinigt CMS-HTML auf eine sichere Teilmenge (sonst Fallback: reiner Text). */
function cms_clean_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return cms_strip_html($html);
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $ok = $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div id="cms-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    $root = $ok ? $doc->getElementById('cms-root') : null;
    if ($root === null) {
        return cms_strip_html($html);
    }

    cms_clean_node($root);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    $out = trim($out);

    if (function_exists('mb_strlen')) {
        if (!mb_check_encoding($out, 'UTF-8')) {
            $out = mb_convert_encoding($out, 'UTF-8', 'UTF-8');
        }
        if (mb_strlen($out) > 60000) {
            $out = mb_substr($out, 0, 60000);
        }
    } elseif (strlen($out) > 120000) {
        $out = substr($out, 0, 120000);
    }
    return $out;
}

/** Rekursiv: erlaubte Tags behalten, gefährliche entfernen, unbekannte „auspacken". */
function cms_clean_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) { // Kommentare/PI
            $child->parentNode->removeChild($child);
            continue;
        }
        /** @var DOMElement $child */
        $tag = strtolower($child->nodeName);

        if (in_array($tag, CMS_HTML_DROP, true)) {
            $child->parentNode->removeChild($child);
            continue;
        }

        if ($tag === 'img') {
            $src = trim($child->getAttribute('src'));
            if ($src === '' || stripos($src, 'data:') === 0) {
                $child->parentNode->removeChild($child);
                continue;
            }
            $src   = preg_replace('/#.*$/', '', $src) ?? $src; // #joomlaImage-Fragment weg
            $attrs = ['src' => $src];
            if ($child->getAttribute('alt') !== '') {
                $attrs['alt'] = $child->getAttribute('alt');
            }
            cms_set_attrs($child, $attrs);
            continue;
        }

        if ($tag === 'iframe') {
            $src  = trim($child->getAttribute('src'));
            $host = strtolower((string) parse_url($src, PHP_URL_HOST));
            if ($host === '' || !cms_iframe_host_ok($host)) {
                $child->parentNode->removeChild($child);
                continue;
            }
            $attrs = ['src' => $src];
            foreach (CMS_HTML_ATTRS['iframe'] as $a) {
                if ($a !== 'src' && $child->hasAttribute($a)) {
                    $attrs[$a] = $child->getAttribute($a);
                }
            }
            cms_set_attrs($child, $attrs);
            continue;
        }

        if (!in_array($tag, CMS_HTML_KEEP, true)) {
            cms_clean_node($child); // Kinder zuerst säubern
            while ($child->firstChild) {
                $child->parentNode->insertBefore($child->firstChild, $child);
            }
            $child->parentNode->removeChild($child);
            continue;
        }

        // erlaubtes Tag: Attribute auf Whitelist beschränken
        $allowed = CMS_HTML_ATTRS[$tag] ?? [];
        $keep    = [];
        foreach ($allowed as $a) {
            if ($child->hasAttribute($a)) {
                $v = $child->getAttribute($a);
                if ($a === 'href' && stripos(trim($v), 'javascript:') === 0) {
                    continue;
                }
                $keep[$a] = $v;
            }
        }
        cms_set_attrs($child, $keep);
        cms_clean_node($child);
    }
}

/** Setzt genau die übergebenen Attribute (entfernt alle bisherigen). */
function cms_set_attrs(DOMElement $el, array $attrs): void
{
    foreach (iterator_to_array($el->attributes) as $attr) {
        $el->removeAttribute($attr->nodeName);
    }
    foreach ($attrs as $k => $v) {
        $el->setAttribute($k, (string) $v);
    }
}

/** Einzelner Joomla-Artikel nach ID → ['title'=>, 'body'=>(Markdown-ähnlich)]. */
function cms_joomla_article(string $id, array $conn): array
{
    $base  = rtrim((string) ($conn['baseUrl'] ?? ''), '/');
    $token = (string) ($conn['token'] ?? '');
    if ($base === '' || $token === '') {
        throw new RuntimeException('Joomla baseUrl/token fehlen in config.php.');
    }
    // SEF-Form ohne index.php: manche Server verschlucken den PATH_INFO nach index.php.
    $url  = "$base/api/v1/content/articles/" . rawurlencode($id);
    $json = json_decode(cms_http_get($url, ["Authorization: Bearer $token", 'Accept: application/vnd.api+json']), true);
    $a    = $json['data']['attributes'] ?? null;
    if (!is_array($a)) {
        throw new RuntimeException("Joomla-Artikel $id nicht gefunden.");
    }
    // Text kann in `text` (zusammengesetzt) oder getrennt in introtext/fulltext liegen.
    $raw = trim((string) ($a['text'] ?? ''));
    if ($raw === '') {
        $raw = trim(((string) ($a['introtext'] ?? '')) . "\n\n" . ((string) ($a['fulltext'] ?? '')));
    }
    return [
        'title' => (string) ($a['title'] ?? ''),
        'body'  => cms_clean_html($raw),
    ];
}

/** Einzelner WordPress-Beitrag nach ID (numerisch) oder Slug → ['title'=>, 'body'=>]. */
function cms_wp_post(string $loc, array $conn): array
{
    $base = rtrim((string) ($conn['baseUrl'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('WordPress baseUrl fehlt in config.php.');
    }
    $headers = ['Accept: application/json'];
    if (($conn['user'] ?? '') !== '' && ($conn['appPassword'] ?? '') !== '') {
        $headers[] = 'Authorization: Basic ' . base64_encode($conn['user'] . ':' . $conn['appPassword']);
    }
    $url = ctype_digit($loc)
        ? "$base/wp-json/wp/v2/posts/" . rawurlencode($loc)
        : "$base/wp-json/wp/v2/posts?slug=" . rawurlencode($loc);
    $res = json_decode(cms_http_get($url, $headers), true);
    $p   = isset($res['id']) ? $res : ($res[0] ?? null);
    if (!is_array($p)) {
        throw new RuntimeException("WordPress-Beitrag '$loc' nicht gefunden.");
    }
    return [
        'title' => (string) ($p['title']['rendered'] ?? ''),
        'body'  => cms_clean_html((string) ($p['content']['rendered'] ?? '')),
    ];
}

/**
 * Item-weiser Info-Import: je Info-Eintrag mit `source` = joomla/wordpress wird
 * Titel/Text aus dem hinterlegten Artikel gezogen; Struktur (id/icon/order/
 * hidden/source/sourceLocator) und manuelle Einträge bleiben unangetastet.
 */
function cms_import_info(): array
{
    $items = cms_read_json('app-info.json');
    if ($items === null) {
        $items = cms_read_json('info.json') ?? [];
    }
    $any = false;
    foreach ($items as $it) {
        if (($it['source'] ?? 'manual') !== 'manual') {
            $any = true;
            break;
        }
    }
    if (!$any) {
        return [];
    }
    $conns  = (array) (push_config()['sources'] ?? []);
    $report = [];
    foreach ($items as &$it) {
        $src = (string) ($it['source'] ?? 'manual');
        if ($src === 'manual') {
            continue;
        }
        $id  = (string) ($it['id'] ?? '?');
        $loc = trim((string) ($it['sourceLocator'] ?? ''));
        try {
            if ($loc === '') {
                throw new RuntimeException('Locator (Artikel-ID/Slug) fehlt.');
            }
            $a = $src === 'joomla'
                ? cms_joomla_article($loc, (array) ($conns['joomla'] ?? []))
                : cms_wp_post($loc, (array) ($conns['wordpress'] ?? []));
            if (($a['title'] ?? '') !== '') {
                $it['title'] = $a['title'];
            }
            $emptyBody = ($a['body'] ?? '') === '';
            if (!$emptyBody) {
                $it['body'] = $a['body'];
            }
            $report[$id] = $emptyBody
                ? "⚠️ aus $src geholt, aber Text war leer (Titel übernommen)."
                : "✅ aus $src importiert.";
        } catch (Throwable $e) {
            $report[$id] = '❌ ' . $e->getMessage();
        }
    }
    unset($it);
    cms_write_json('app-info.json', array_values($items));
    return $report;
}

function cms_import_joomla(array $binding, array $conn): array
{
    $base  = rtrim((string) ($conn['baseUrl'] ?? ''), '/');
    $token = (string) ($conn['token'] ?? '');
    if ($base === '' || $token === '') {
        throw new RuntimeException('Joomla baseUrl/token fehlen in config.php.');
    }
    $loc = trim((string) ($binding['locator'] ?? ''));
    if ($loc === '') {
        throw new RuntimeException('Locator (Kategorie-ID) fehlt.');
    }
    $url  = "$base/api/v1/content/articles?filter[category]=" . rawurlencode($loc);
    $json = json_decode(cms_http_get($url, ["Authorization: Bearer $token", 'Accept: application/vnd.api+json']), true);
    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $out  = [];
    foreach ($rows as $e) {
        $a     = is_array($e['attributes'] ?? null) ? $e['attributes'] : [];
        $title = (string) ($a['title'] ?? '');
        $out[] = array_filter([
            'id'    => (string) ($e['id'] ?? cms_slug($title)),
            'slug'  => (string) ($a['alias'] ?? cms_slug($title)),
            'title' => $title,
            'name'  => $title,
            'body'  => cms_clean_html((string) ($a['introtext'] ?? $a['text'] ?? '')),
        ], static fn($v) => $v !== '');
    }
    return $out;
}

function cms_import_wordpress(array $binding, array $conn): array
{
    $base = rtrim((string) ($conn['baseUrl'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('WordPress baseUrl fehlt in config.php.');
    }
    $loc     = trim((string) ($binding['locator'] ?? ''));
    $headers = ['Accept: application/json'];
    if (($conn['user'] ?? '') !== '' && ($conn['appPassword'] ?? '') !== '') {
        $headers[] = 'Authorization: Basic ' . base64_encode($conn['user'] . ':' . $conn['appPassword']);
    }
    $url  = "$base/wp-json/wp/v2/posts?per_page=50" . ($loc !== '' ? '&categories_slug=' . rawurlencode($loc) : '');
    $rows = json_decode(cms_http_get($url, $headers), true);
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = [];
    foreach ($rows as $p) {
        $title = (string) ($p['title']['rendered'] ?? '');
        $out[] = array_filter([
            'id'    => (string) ($p['id'] ?? cms_slug($title)),
            'slug'  => (string) ($p['slug'] ?? cms_slug($title)),
            'title' => $title,
            'name'  => $title,
            'body'  => cms_clean_html((string) ($p['content']['rendered'] ?? $p['excerpt']['rendered'] ?? '')),
        ], static fn($v) => $v !== '');
    }
    return $out;
}

/** Import für alle Domänen mit provider != manual. Gibt einen Domain→Status-Report zurück. */
function cms_run_import(): array
{
    $cfg    = cms_source_config();
    $conns  = (array) (push_config()['sources'] ?? []);
    $report = [];
    foreach (CMS_CONTENT_DOMAINS as $domain => $meta) {
        // Infos werden item-weise importiert (Quelle je Eintrag im „Infos"-Tab).
        if ($domain === 'info') {
            foreach (cms_import_info() as $iid => $st) {
                $report["info:$iid"] = $st;
            }
            continue;
        }
        $binding  = is_array($cfg[$domain] ?? null) ? $cfg[$domain] : ['provider' => 'manual'];
        $provider = (string) ($binding['provider'] ?? 'manual');
        if ($provider === 'manual') {
            continue;
        }
        try {
            $records = $provider === 'joomla'
                ? cms_import_joomla($binding, (array) ($conns['joomla'] ?? []))
                : cms_import_wordpress($binding, (array) ($conns['wordpress'] ?? []));
            if (!$records) {
                $report[$domain] = '⚠️ 0 Datensätze – nichts geschrieben.';
                continue;
            }
            $report[$domain] = cms_write_json("app-$domain.json", $records)
                ? '✅ ' . count($records) . ' importiert.'
                : '❌ Schreiben fehlgeschlagen (Schreibrechte?).';
        } catch (Throwable $e) {
            $report[$domain] = '❌ ' . $e->getMessage();
        }
    }
    return $report;
}
