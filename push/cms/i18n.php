<?php
// CMS-Mehrsprachigkeit: Deutsch ist die Quellsprache (= Schlüssel), en/fr/es
// werden über die Tabelle CMS_I18N übersetzt (unbekannte Schlüssel fallen auf
// Deutsch zurück). Die gewählte Sprache liegt serverseitig in
// push/cms-settings.json und wird im Tab „Einstellungen" umgestellt.

declare(strict_types=1);

const CMS_LANGS = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español'];

function cms_settings_file(): string
{
    return __DIR__ . '/../cms-settings.json';
}

/** Aktuelle CMS-Sprache (einmal geladen; per zweitem Argument umschaltbar). */
function cms_lang(?string $set = null): string
{
    static $lang = null;
    if ($set !== null && isset(CMS_LANGS[$set])) {
        $lang = $set;
    }
    if ($lang === null) {
        $data = json_decode((string) @file_get_contents(cms_settings_file()), true);
        // Ohne gespeicherte Wahl: Englisch (GitHub-/Release-Standard).
        $l    = is_array($data) ? (string) ($data['lang'] ?? 'en') : 'en';
        $lang = isset(CMS_LANGS[$l]) ? $l : 'en';
    }
    return $lang;
}

/** Sprache speichern (cms-settings.json) und sofort für diese Antwort anwenden. */
function cms_set_lang(string $l): bool
{
    if (!isset(CMS_LANGS[$l])) {
        return false;
    }
    $f   = cms_settings_file();
    $tmp = $f . '.tmp';
    $ok  = @file_put_contents($tmp, json_encode(['lang' => $l], JSON_PRETTY_PRINT) . "\n") !== false
        && @rename($tmp, $f);
    if ($ok) {
        cms_lang($l);
    }
    return $ok;
}

/**
 * Übersetzt einen deutschen UI-Text in die CMS-Sprache. Mit weiteren Argumenten
 * wird der (übersetzte) Text als sprintf-Vorlage behandelt. Einige Schlüssel
 * enthalten bewusst Inline-HTML (<b>/<code>) – diese NICHT durch cms_h() jagen.
 */
function cms_t(string $text, ...$args): string
{
    $lang = cms_lang();
    if ($lang !== 'de') {
        $text = CMS_I18N[$text][$lang] ?? $text;
    }
    return $args === [] ? $text : vsprintf($text, $args);
}

/** Für Inline-JS (onclick="confirm('…')"): JS-Single-Quote- + HTML-Escaping. */
function cms_j(string $s): string
{
    return htmlspecialchars(str_replace(['\\', "'"], ['\\\\', "\\'"], $s), ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Übersetzungstabelle. Schlüssel = exakter deutscher UI-Text.
// ---------------------------------------------------------------------------
const CMS_I18N = [
    // --- Allgemein / Auth ---------------------------------------------------
    'Anmelden' => ['en' => 'Log in', 'fr' => 'Se connecter', 'es' => 'Iniciar sesión'],
    'Abmelden' => ['en' => 'Log out', 'fr' => 'Se déconnecter', 'es' => 'Cerrar sesión'],
    'Passwort' => ['en' => 'Password', 'fr' => 'Mot de passe', 'es' => 'Contraseña'],
    'Falsches Passwort.' => ['en' => 'Wrong password.', 'fr' => 'Mot de passe incorrect.', 'es' => 'Contraseña incorrecta.'],
    'Passwort = Admin-Passwort aus <code>push/config.php</code>.' => [
        'en' => 'Password = admin password from <code>push/config.php</code>.',
        'fr' => 'Mot de passe = mot de passe admin de <code>push/config.php</code>.',
        'es' => 'Contraseña = contraseña de administrador de <code>push/config.php</code>.',
    ],
    'Speichern' => ['en' => 'Save', 'fr' => 'Enregistrer', 'es' => 'Guardar'],
    'Löschen'   => ['en' => 'Delete', 'fr' => 'Supprimer', 'es' => 'Eliminar'],
    'Sicherheits-Token ungültig – bitte erneut speichern.' => [
        'en' => 'Invalid security token – please save again.',
        'fr' => 'Jeton de sécurité invalide – merci de réenregistrer.',
        'es' => 'Token de seguridad no válido; guarda de nuevo.',
    ],

    // --- Tabs ---------------------------------------------------------------
    'MEHR-Menü'      => ['en' => 'More menu', 'fr' => 'Menu Plus', 'es' => 'Menú Más'],
    'Infos'          => ['en' => 'Info', 'fr' => 'Infos', 'es' => 'Información'],
    'Inhalte'        => ['en' => 'Content', 'fr' => 'Contenus', 'es' => 'Contenido'],
    'Bilder'         => ['en' => 'Images', 'fr' => 'Images', 'es' => 'Imágenes'],
    'Quellen'        => ['en' => 'Sources', 'fr' => 'Sources', 'es' => 'Fuentes'],
    'Einstellungen'  => ['en' => 'Settings', 'fr' => 'Réglages', 'es' => 'Ajustes'],
    'News'           => ['en' => 'News', 'fr' => 'Actus', 'es' => 'Noticias'],
    'Push'           => ['en' => 'Push', 'fr' => 'Push', 'es' => 'Push'],
    'Wetter'         => ['en' => 'Weather', 'fr' => 'Météo', 'es' => 'Tiempo'],
    'Statistik'      => ['en' => 'Statistics', 'fr' => 'Statistiques', 'es' => 'Estadísticas'],
    'Protokoll'      => ['en' => 'Log', 'fr' => 'Journal', 'es' => 'Registro'],
    'Hilfe'          => ['en' => 'Help', 'fr' => 'Aide', 'es' => 'Ayuda'],

    // --- MEHR-Menü-Tab ------------------------------------------------------
    'Sichtbare Punkte im MEHR-Menü' => [
        'en' => 'Visible items in the More menu',
        'fr' => 'Éléments visibles du menu Plus',
        'es' => 'Elementos visibles del menú Más',
    ],
    'Angehakt = sichtbar. Abgehakte Punkte werden in der App ausgeblendet.' => [
        'en' => 'Checked = visible. Unchecked items are hidden in the app.',
        'fr' => "Coché = visible. Les éléments décochés sont masqués dans l'app.",
        'es' => 'Marcado = visible. Los elementos desmarcados se ocultan en la app.',
    ],
    'Newsfeed'  => ['en' => 'News feed', 'fr' => 'Actualités', 'es' => 'Noticias'],
    'Karte'     => ['en' => 'Map', 'fr' => 'Carte', 'es' => 'Mapa'],
    'Sponsoren' => ['en' => 'Sponsors', 'fr' => 'Sponsors', 'es' => 'Patrocinadores'],
    'Tickets'   => ['en' => 'Tickets', 'fr' => 'Billets', 'es' => 'Entradas'],
    'Kontakt'   => ['en' => 'Contact', 'fr' => 'Contact', 'es' => 'Contacto'],
    'Impressum' => ['en' => 'Imprint', 'fr' => 'Mentions légales', 'es' => 'Aviso legal'],
    'Dark / Light' => ['en' => 'Dark / Light', 'fr' => 'Sombre / Clair', 'es' => 'Oscuro / Claro'],
    'Sprache'   => ['en' => 'Language', 'fr' => 'Langue', 'es' => 'Idioma'],

    // --- Infos-Tab ----------------------------------------------------------
    'Import-Ergebnis' => ['en' => 'Import result', 'fr' => "Résultat de l'import", 'es' => 'Resultado de la importación'],
    'Kein Info-Eintrag auf Joomla/WordPress gesetzt.' => [
        'en' => 'No info entry is set to Joomla/WordPress.',
        'fr' => "Aucune info n'est reliée à Joomla/WordPress.",
        'es' => 'Ninguna entrada de información está asignada a Joomla/WordPress.',
    ],
    'Infos verwalten' => ['en' => 'Manage info pages', 'fr' => 'Gérer les infos', 'es' => 'Gestionar información'],
    'Ein-/ausblenden (Häkchen „Sichtbar"), umbenennen, Text und Reihenfolge ändern, neue Einträge unten hinzufügen, „Löschen" entfernt beim Speichern. Reihenfolge darf Dezimal sein (z. B. 1.5). Text = Markdown; Leerzeile = neuer Absatz.' => [
        'en' => 'Show/hide, rename, edit text and order, add new entries at the bottom; "Delete" removes on save. Order may be decimal (e.g. 1.5). Text = Markdown; blank line = new paragraph.',
        'fr' => "Afficher/masquer, renommer, modifier le texte et l'ordre, ajouter de nouvelles entrées en bas ; « Supprimer » retire à l'enregistrement. L'ordre peut être décimal (p. ex. 1.5). Texte = Markdown ; ligne vide = nouveau paragraphe.",
        'es' => 'Mostrar/ocultar, renombrar, cambiar texto y orden, añadir nuevas entradas abajo; «Eliminar» quita al guardar. El orden puede ser decimal (p. ej. 1.5). Texto = Markdown; línea vacía = nuevo párrafo.',
    ],
    '<b>Quelle je Eintrag:</b> <code>manual</code> = die hier getippten Werte. <code>joomla</code>/<code>wordpress</code> = Titel/Text werden beim Import aus dem Artikel (Locator: Joomla-Artikel-ID bzw. WP-Slug/ID) gezogen; Reihenfolge/Icon/Sichtbarkeit bleiben hier. Erst <b>Speichern</b>, dann <b>Importieren</b>.' => [
        'en' => '<b>Source per entry:</b> <code>manual</code> = the values typed here. <code>joomla</code>/<code>wordpress</code> = title/text are pulled from the article on import (locator: Joomla article ID or WP slug/ID); order/icon/visibility stay here. First <b>save</b>, then <b>import</b>.',
        'fr' => "<b>Source par entrée :</b> <code>manual</code> = les valeurs saisies ici. <code>joomla</code>/<code>wordpress</code> = le titre/texte sont tirés de l'article à l'import (locator : ID d'article Joomla ou slug/ID WP) ; ordre/icône/visibilité restent ici. D'abord <b>enregistrer</b>, puis <b>importer</b>.",
        'es' => '<b>Fuente por entrada:</b> <code>manual</code> = los valores escritos aquí. <code>joomla</code>/<code>wordpress</code> = el título/texto se toman del artículo al importar (locator: ID del artículo de Joomla o slug/ID de WP); orden/icono/visibilidad se mantienen aquí. Primero <b>guardar</b>, luego <b>importar</b>.',
    ],
    'Aus Joomla/WordPress importieren' => [
        'en' => 'Import from Joomla/WordPress',
        'fr' => 'Importer depuis Joomla/WordPress',
        'es' => 'Importar desde Joomla/WordPress',
    ],
    'Titel/Text aller auf Joomla/WordPress gesetzten Einträge jetzt importieren? (zuvor gespeicherte Quellen)' => [
        'en' => 'Import title/text of all entries set to Joomla/WordPress now? (previously saved sources)',
        'fr' => 'Importer maintenant le titre/texte de toutes les entrées reliées à Joomla/WordPress ? (sources enregistrées auparavant)',
        'es' => '¿Importar ahora el título/texto de todas las entradas asignadas a Joomla/WordPress? (fuentes guardadas previamente)',
    ],
    'Neuer Eintrag' => ['en' => 'New entry', 'fr' => 'Nouvelle entrée', 'es' => 'Nueva entrada'],
    'Titel'        => ['en' => 'Title', 'fr' => 'Titre', 'es' => 'Título'],
    'Reihenfolge'  => ['en' => 'Order', 'fr' => 'Ordre', 'es' => 'Orden'],
    'Icon (optional)' => ['en' => 'Icon (optional)', 'fr' => 'Icône (optionnel)', 'es' => 'Icono (opcional)'],
    'Versteckt (nicht im Menü/Suche)' => [
        'en' => 'Hidden (not in menu/search)',
        'fr' => 'Masqué (ni menu ni recherche)',
        'es' => 'Oculto (no en menú/búsqueda)',
    ],
    'Als Frage/Antwort-Accordion anzeigen (jede „## Frage“ wird aufklappbar; Text davor = Intro)' => [
        'en' => 'Show as Q&A accordion (each "## question" becomes collapsible; text before = intro)',
        'fr' => 'Afficher comme accordéon question/réponse (chaque « ## question » devient dépliable ; texte avant = intro)',
        'es' => 'Mostrar como acordeón de preguntas y respuestas (cada «## pregunta» se puede desplegar; el texto anterior = introducción)',
    ],
    'Quelle' => ['en' => 'Source', 'fr' => 'Source', 'es' => 'Fuente'],
    'manual (getippt)' => ['en' => 'manual (typed)', 'fr' => 'manual (saisi)', 'es' => 'manual (escrito)'],
    'Locator (Joomla-Artikel-ID / WP-Slug)' => [
        'en' => 'Locator (Joomla article ID / WP slug)',
        'fr' => "Locator (ID d'article Joomla / slug WP)",
        'es' => 'Locator (ID de artículo de Joomla / slug de WP)',
    ],
    'z. B. 123' => ['en' => 'e.g. 123', 'fr' => 'p. ex. 123', 'es' => 'p. ej. 123'],
    'Text (Markdown)' => ['en' => 'Text (Markdown)', 'fr' => 'Texte (Markdown)', 'es' => 'Texto (Markdown)'],

    // --- Inhalte-Tab --------------------------------------------------------
    'Jede Datei aus <code>/content</code> bearbeitbar → live wirksam (überschreibt den Build-Stand). „Override entfernen" stellt den Build-Stand wieder her.' => [
        'en' => '<code>/content</code> files are editable here → effective live (overrides the build state). "Remove override" restores the build state.',
        'fr' => "Chaque fichier de <code>/content</code> est modifiable → effet immédiat (remplace l'état du build). « Retirer l'override » restaure l'état du build.",
        'es' => 'Cada archivo de <code>/content</code> es editable → efecto inmediato (sustituye el estado del build). «Quitar el override» restaura el estado del build.',
    ],
    'Domäne' => ['en' => 'Domain', 'fr' => 'Domaine', 'es' => 'Dominio'],
    '🟡 Override aktiv.' => ['en' => '🟡 Override active.', 'fr' => '🟡 Override actif.', 'es' => '🟡 Override activo.'],
    '⚪ Kein Override – Build-Stand.' => [
        'en' => '⚪ No override – build state.',
        'fr' => "⚪ Pas d'override – état du build.",
        'es' => '⚪ Sin override – estado del build.',
    ],
    'Als JSON bearbeiten' => ['en' => 'Edit as JSON', 'fr' => 'Modifier en JSON', 'es' => 'Editar como JSON'],
    'Formular-Ansicht'    => ['en' => 'Form view', 'fr' => 'Vue formulaire', 'es' => 'Vista de formulario'],
    'Override entfernen'  => ['en' => 'Remove override', 'fr' => "Retirer l'override", 'es' => 'Quitar el override'],
    'Override entfernen und zum Build-Stand zurück?' => [
        'en' => 'Remove the override and return to the build state?',
        'fr' => "Retirer l'override et revenir à l'état du build ?",
        'es' => '¿Quitar el override y volver al estado del build?',
    ],
    'Pro Slot: Act, Bühne, Tag, Beginn/Ende. Neue Zeile unten. „Löschen" entfernt beim Speichern. (Acts/Bühnen/Tage kommen aus den jeweiligen Inhalten.)' => [
        'en' => 'Per slot: act, stage, day, start/end. New row at the bottom. "Delete" removes on save. (Acts/stages/days come from the respective content.)',
        'fr' => "Par créneau : artiste, scène, jour, début/fin. Nouvelle ligne en bas. « Supprimer » retire à l'enregistrement. (Artistes/scènes/jours viennent des contenus respectifs.)",
        'es' => 'Por actuación: artista, escenario, día, inicio/fin. Nueva fila abajo. «Eliminar» quita al guardar. (Artistas/escenarios/días provienen de los contenidos respectivos.)',
    ],
    'Neuer Slot' => ['en' => 'New slot', 'fr' => 'Nouveau créneau', 'es' => 'Nueva actuación'],
    'Act'    => ['en' => 'Act', 'fr' => 'Artiste', 'es' => 'Artista'],
    'Bühne'  => ['en' => 'Stage', 'fr' => 'Scène', 'es' => 'Escenario'],
    'Tag'    => ['en' => 'Day', 'fr' => 'Jour', 'es' => 'Día'],
    'abgesagt' => ['en' => 'cancelled', 'fr' => 'annulé', 'es' => 'cancelado'],
    'Beginn' => ['en' => 'Start', 'fr' => 'Début', 'es' => 'Inicio'],
    'Ende'   => ['en' => 'End', 'fr' => 'Fin', 'es' => 'Fin'],
    'Notiz (optional)' => ['en' => 'Note (optional)', 'fr' => 'Note (optionnel)', 'es' => 'Nota (opcional)'],

    // Domänen-Labels (CMS_CONTENT_DOMAINS)
    'Festival-Eckdaten' => ['en' => 'Festival basics', 'fr' => 'Données du festival', 'es' => 'Datos del festival'],
    'Bühnen'            => ['en' => 'Stages', 'fr' => 'Scènes', 'es' => 'Escenarios'],
    'Artists'           => ['en' => 'Artists', 'fr' => 'Artistes', 'es' => 'Artistas'],
    'Timetable (Slots)' => ['en' => 'Timetable (slots)', 'fr' => 'Programme (créneaux)', 'es' => 'Horarios (actuaciones)'],
    'Karten-Punkte (POIs)' => ['en' => 'Map points (POIs)', 'fr' => 'Points de carte (POI)', 'es' => 'Puntos del mapa (POI)'],
    'POI-Kategorien'    => ['en' => 'POI categories', 'fr' => 'Catégories de POI', 'es' => 'Categorías de POI'],
    'Infos (auch eigener Tab)' => [
        'en' => 'Info pages (also own tab)',
        'fr' => 'Infos (aussi onglet dédié)',
        'es' => 'Información (también pestaña propia)',
    ],

    // Feld-Labels (CMS_DOMAIN_FIELDS)
    'Name'        => ['en' => 'Name', 'fr' => 'Nom', 'es' => 'Nombre'],
    'Kurzname'    => ['en' => 'Short name', 'fr' => 'Nom court', 'es' => 'Nombre corto'],
    'Farbe (Hex)' => ['en' => 'Color (hex)', 'fr' => 'Couleur (hex)', 'es' => 'Color (hex)'],
    'Logo-Pfad'   => ['en' => 'Logo path', 'fr' => 'Chemin du logo', 'es' => 'Ruta del logo'],
    'Stufe'       => ['en' => 'Tier', 'fr' => 'Niveau', 'es' => 'Nivel'],
    'Website'     => ['en' => 'Website', 'fr' => 'Site web', 'es' => 'Sitio web'],
    'Kategorie'   => ['en' => 'Category', 'fr' => 'Catégorie', 'es' => 'Categoría'],
    'Icon (Emoji ODER Bildpfad /data/uploads/…; leer = Kategorie-Icon)' => [
        'en' => 'Icon (emoji OR image path /data/uploads/…; empty = category icon)',
        'fr' => "Icône (emoji OU chemin d'image /data/uploads/… ; vide = icône de la catégorie)",
        'es' => 'Icono (emoji O ruta de imagen /data/uploads/…; vacío = icono de la categoría)',
    ],
    'ID (Schlüssel, z. B. parking)' => [
        'en' => 'ID (key, e.g. parking)',
        'fr' => 'ID (clé, p. ex. parking)',
        'es' => 'ID (clave, p. ej. parking)',
    ],
    'Bezeichnung' => ['en' => 'Label', 'fr' => 'Libellé', 'es' => 'Etiqueta'],
    'Icon (Emoji ODER Bildpfad /data/uploads/…)' => [
        'en' => 'Icon (emoji OR image path /data/uploads/…)',
        'fr' => "Icône (emoji OU chemin d'image /data/uploads/…)",
        'es' => 'Icono (emoji O ruta de imagen /data/uploads/…)',
    ],
    'Farbe (Hex, z. B. #9aa0a6)' => [
        'en' => 'Color (hex, e.g. #9aa0a6)',
        'fr' => 'Couleur (hex, p. ex. #9aa0a6)',
        'es' => 'Color (hex, p. ej. #9aa0a6)',
    ],
    'Ausblenden (komplett von Karte + Filter)' => [
        'en' => 'Hide (entirely from map + filter)',
        'fr' => 'Masquer (carte + filtre)',
        'es' => 'Ocultar (por completo del mapa y filtros)',
    ],
    'Slug (auto, falls leer)' => ['en' => 'Slug (auto if empty)', 'fr' => 'Slug (auto si vide)', 'es' => 'Slug (automático si está vacío)'],
    'Genres (Komma-getrennt)' => [
        'en' => 'Genres (comma-separated)',
        'fr' => 'Genres (séparés par des virgules)',
        'es' => 'Géneros (separados por comas)',
    ],
    'Land' => ['en' => 'Country', 'fr' => 'Pays', 'es' => 'País'],
    'Headliner' => ['en' => 'Headliner', 'fr' => "Tête d'affiche", 'es' => 'Cabeza de cartel'],
    'Im Line-Up zeigen' => ['en' => 'Show in line-up', 'fr' => 'Afficher dans le line-up', 'es' => 'Mostrar en el cartel'],
    'Bild-Pfad' => ['en' => 'Image path', 'fr' => "Chemin de l'image", 'es' => 'Ruta de la imagen'],
    'Bio (Markdown)' => ['en' => 'Bio (Markdown)', 'fr' => 'Bio (Markdown)', 'es' => 'Biografía (Markdown)'],
    '/data/uploads/… (Tab Bilder)' => [
        'en' => '/data/uploads/… (Images tab)',
        'fr' => '/data/uploads/… (onglet Images)',
        'es' => '/data/uploads/… (pestaña Imágenes)',
    ],

    // --- Bilder-Tab ---------------------------------------------------------
    'Bild hochladen' => ['en' => 'Upload image', 'fr' => 'Téléverser une image', 'es' => 'Subir imagen'],
    'Erlaubt: %s · max. 5 MB. Wird unter <code>/data/uploads/</code> gespeichert; den angezeigten Pfad kopierst du in „Inhalte" (z. B. Artist-<code>image</code> oder Sponsor-<code>logo</code>).' => [
        'en' => 'Allowed: %s · max. 5 MB. Stored under <code>/data/uploads/</code>; copy the shown path into "Content" (e.g. artist <code>image</code> or sponsor <code>logo</code>).',
        'fr' => "Autorisé : %s · 5 Mo max. Stocké sous <code>/data/uploads/</code> ; copie le chemin affiché dans « Contenus » (p. ex. <code>image</code> d'artiste ou <code>logo</code> de sponsor).",
        'es' => 'Permitido: %s · máx. 5 MB. Se guarda en <code>/data/uploads/</code>; copia la ruta mostrada en «Contenido» (p. ej. <code>image</code> de artista o <code>logo</code> de patrocinador).',
    ],
    'Datei' => ['en' => 'File', 'fr' => 'Fichier', 'es' => 'Archivo'],
    'Dateiname überschreiben (optional, ohne Endung)' => [
        'en' => 'Override file name (optional, without extension)',
        'fr' => 'Renommer le fichier (optionnel, sans extension)',
        'es' => 'Cambiar el nombre del archivo (opcional, sin extensión)',
    ],
    'z. B. logo-firma' => ['en' => 'e.g. company-logo', 'fr' => 'p. ex. logo-entreprise', 'es' => 'p. ej. logo-empresa'],
    'Hochladen' => ['en' => 'Upload', 'fr' => 'Téléverser', 'es' => 'Subir'],
    'Pfad:' => ['en' => 'Path:', 'fr' => 'Chemin :', 'es' => 'Ruta:'],
    'Vorhandene Uploads' => ['en' => 'Existing uploads', 'fr' => 'Fichiers téléversés', 'es' => 'Archivos subidos'],
    'Keine Datei gewählt.' => ['en' => 'No file selected.', 'fr' => 'Aucun fichier sélectionné.', 'es' => 'No se ha seleccionado ningún archivo.'],
    'Upload-Fehler (Code %d).' => ['en' => 'Upload error (code %d).', 'fr' => 'Erreur de téléversement (code %d).', 'es' => 'Error de subida (código %d).'],
    'Datei zu groß (max. 5 MB).' => ['en' => 'File too large (max. 5 MB).', 'fr' => 'Fichier trop volumineux (5 Mo max).', 'es' => 'Archivo demasiado grande (máx. 5 MB).'],
    'Nur %s erlaubt.' => ['en' => 'Only %s allowed.', 'fr' => 'Seulement %s autorisés.', 'es' => 'Solo se permite %s.'],
    'Upload-Ordner (data/uploads) nicht beschreibbar – Schreibrechte prüfen.' => [
        'en' => 'Upload folder (data/uploads) is not writable – check write permissions.',
        'fr' => "Dossier d'upload (data/uploads) non inscriptible – vérifier les droits d'écriture.",
        'es' => 'La carpeta de subidas (data/uploads) no es escribible; revisa los permisos de escritura.',
    ],
    'Konnte Datei nicht speichern.' => ['en' => 'Could not save the file.', 'fr' => "Impossible d'enregistrer le fichier.", 'es' => 'No se pudo guardar el archivo.'],
    'Hochgeladen. Pfad unten kopieren und z. B. als Artist-„image" oder Sponsor-„logo" einsetzen.' => [
        'en' => 'Uploaded. Copy the path below and use it e.g. as artist "image" or sponsor "logo".',
        'fr' => "Téléversé. Copie le chemin ci-dessous et utilise-le p. ex. comme « image » d'artiste ou « logo » de sponsor.",
        'es' => 'Subido. Copia la ruta de abajo y úsala p. ej. como «image» de artista o «logo» de patrocinador.',
    ],

    // --- Quellen-Tab --------------------------------------------------------
    'Datenquelle je Domäne' => ['en' => 'Data source per domain', 'fr' => 'Source de données par domaine', 'es' => 'Fuente de datos por dominio'],
    'Pro Domäne wählen, woher die Daten kommen. <b>manual</b> = der „Inhalte"-Editor bzw. Build-Stand. <b>joomla</b>/<b>wordpress</b> = Server-Import. Locator: Joomla = Kategorie-ID, WordPress = Kategorie-Slug. Verbindung/Token in <code>push/config.php</code> → <code>sources</code>. <i>Generisches Mapping (Titel/Text); strukturierte Domänen ggf. im „Inhalte"-Tab nachbearbeiten.</i>' => [
        'en' => 'Choose per domain where the data comes from. <b>manual</b> = the "Content" editor or build state. <b>joomla</b>/<b>wordpress</b> = server import. Locator: Joomla = category ID, WordPress = category slug. Connection/token in <code>push/config.php</code> → <code>sources</code>. <i>Generic mapping (title/text); post-edit structured domains in the "Content" tab if needed.</i>',
        'fr' => "Choisir par domaine d'où viennent les données. <b>manual</b> = l'éditeur « Contenus » ou l'état du build. <b>joomla</b>/<b>wordpress</b> = import serveur. Locator : Joomla = ID de catégorie, WordPress = slug de catégorie. Connexion/token dans <code>push/config.php</code> → <code>sources</code>. <i>Mapping générique (titre/texte) ; retoucher les domaines structurés dans l'onglet « Contenus » si besoin.</i>",
        'es' => 'Elige por dominio de dónde vienen los datos. <b>manual</b> = el editor «Contenido» o el estado del build. <b>joomla</b>/<b>wordpress</b> = importación del servidor. Locator: Joomla = ID de categoría, WordPress = slug de categoría. Conexión/token en <code>push/config.php</code> → <code>sources</code>. <i>Mapeo genérico (título/texto); retoca los dominios estructurados en la pestaña «Contenido» si hace falta.</i>',
    ],
    'Locator (Kategorie-ID / -Slug)' => [
        'en' => 'Locator (category ID / slug)',
        'fr' => 'Locator (ID/slug de catégorie)',
        'es' => 'Locator (ID/slug de categoría)',
    ],
    'Quellen speichern' => ['en' => 'Save sources', 'fr' => 'Enregistrer les sources', 'es' => 'Guardar fuentes'],
    'Jetzt importieren' => ['en' => 'Import now', 'fr' => 'Importer maintenant', 'es' => 'Importar ahora'],
    'Jetzt aus den konfigurierten Quellen importieren?' => [
        'en' => 'Import from the configured sources now?',
        'fr' => 'Importer maintenant depuis les sources configurées ?',
        'es' => '¿Importar ahora desde las fuentes configuradas?',
    ],
    'Keine Domäne auf Joomla/WordPress gesetzt.' => [
        'en' => 'No domain is set to Joomla/WordPress.',
        'fr' => "Aucun domaine n'est relié à Joomla/WordPress.",
        'es' => 'Ningún dominio está asignado a Joomla/WordPress.',
    ],
    'Quellen gespeichert.' => ['en' => 'Sources saved.', 'fr' => 'Sources enregistrées.', 'es' => 'Fuentes guardadas.'],
    'Import ausgeführt.' => ['en' => 'Import executed.', 'fr' => 'Import effectué.', 'es' => 'Importación ejecutada.'],
    'Keine Domäne auf Joomla/WordPress gesetzt – nichts zu importieren.' => [
        'en' => 'No domain is set to Joomla/WordPress – nothing to import.',
        'fr' => 'Aucun domaine relié à Joomla/WordPress – rien à importer.',
        'es' => 'Ningún dominio asignado a Joomla/WordPress; nada que importar.',
    ],
    'Info-Import ausgeführt.' => ['en' => 'Info import executed.', 'fr' => 'Import des infos effectué.', 'es' => 'Importación de información ejecutada.'],
    'Kein Info-Eintrag auf Joomla/WordPress gesetzt – nichts zu importieren.' => [
        'en' => 'No info entry is set to Joomla/WordPress – nothing to import.',
        'fr' => 'Aucune info reliée à Joomla/WordPress – rien à importer.',
        'es' => 'Ninguna entrada de información asignada a Joomla/WordPress; nada que importar.',
    ],

    // --- Einstellungen-Tab --------------------------------------------------
    'Globale Einstellungen' => ['en' => 'Global settings', 'fr' => 'Réglages globaux', 'es' => 'Ajustes globales'],
    'CMS-Sprache' => ['en' => 'CMS language', 'fr' => 'Langue du CMS', 'es' => 'Idioma del CMS'],
    'Sprache dieser Admin-Oberfläche. Die App-Sprache wählt jeder Gast selbst in der App.' => [
        'en' => 'Language of this admin interface. Each visitor picks the app language in the app itself.',
        'fr' => "Langue de cette interface d'administration. Chaque visiteur choisit la langue de l'app dans l'app elle-même.",
        'es' => 'Idioma de esta interfaz de administración. Cada visitante elige el idioma de la app en la propia app.',
    ],
    'Line-Up: Anzahl Acts mit Bild' => [
        'en' => 'Line-up: number of acts with image',
        'fr' => "Line-up : nombre d'artistes avec image",
        'es' => 'Cartel: número de artistas con imagen',
    ],
    'Standard (20)' => ['en' => 'Default (20)', 'fr' => 'Défaut (20)', 'es' => 'Predeterminado (20)'],
    'Leer = Standardwert aus dem App-Code (20). Alle weiteren Acts ohne Bild.' => [
        'en' => 'Empty = default from the app code (20). All further acts without image.',
        'fr' => "Vide = valeur par défaut du code de l'app (20). Tous les artistes suivants sans image.",
        'es' => 'Vacío = valor predeterminado del código de la app (20). El resto de artistas sin imagen.',
    ],
    'Standard-Theme (solange der Gast nicht selbst wählt)' => [
        'en' => 'Default theme (until the visitor picks one)',
        'fr' => "Thème par défaut (tant que le visiteur n'a pas choisi)",
        'es' => 'Tema predeterminado (mientras el visitante no elija)',
    ],
    'App-Standard (Dark)' => ['en' => 'App default (dark)', 'fr' => "Défaut de l'app (sombre)", 'es' => 'Predeterminado de la app (oscuro)'],
    'Dark'  => ['en' => 'Dark', 'fr' => 'Sombre', 'es' => 'Oscuro'],
    'Light' => ['en' => 'Light', 'fr' => 'Clair', 'es' => 'Claro'],
    'Hintergrundgrafik anzeigen' => ['en' => 'Show background artwork', 'fr' => 'Afficher le fond graphique', 'es' => 'Mostrar gráfico de fondo'],
    'Hintergrundbild' => ['en' => 'Background image', 'fr' => 'Image de fond', 'es' => 'Imagen de fondo'],
    'Standard (mitgelieferte Grafik)' => [
        'en' => 'Default (bundled artwork)',
        'fr' => 'Standard (visuel fourni)',
        'es' => 'Predeterminado (gráfico incluido)',
    ],
    'Eigenes Bild zuerst im Tab „Bilder" hochladen (Querformat empfohlen). Wirkt nur, solange „Hintergrundgrafik anzeigen" aktiv ist.' => [
        'en' => 'Upload your own image in the "Images" tab first (landscape recommended). Only takes effect while "Show background artwork" is active.',
        'fr' => "Téléverse d'abord ta propre image dans l'onglet « Images » (paysage recommandé). N'agit que tant que « Afficher le fond graphique » est actif.",
        'es' => 'Sube primero tu propia imagen en la pestaña «Imágenes» (se recomienda horizontal). Solo tiene efecto mientras «Mostrar gráfico de fondo» esté activo.',
    ],
    'Home: Festivalname und Datum anzeigen' => [
        'en' => 'Home: show festival name and date',
        'fr' => 'Accueil : afficher le nom et les dates du festival',
        'es' => 'Inicio: mostrar nombre y fechas del festival',
    ],
    'Push-Automatik' => ['en' => 'Push automation', 'fr' => 'Automatisation des push', 'es' => 'Automatización de push'],
    'Steuert die automatischen Pushes des Cron-Jobs (läuft je nach Server z. B. stündlich). Greift nur, wenn der Cron eingerichtet ist (siehe <code>docs/PUSH.md</code>).' => [
        'en' => "Controls the cron job's automatic pushes (runs e.g. hourly depending on the server). Only applies if the cron is set up (see <code>docs/PUSH.md</code>).",
        'fr' => "Contrôle les push automatiques du cron (exécuté p. ex. toutes les heures selon le serveur). Ne s'applique que si le cron est configuré (voir <code>docs/PUSH.md</code>).",
        'es' => 'Controla los push automáticos del cron (se ejecuta p. ej. cada hora según el servidor). Solo aplica si el cron está configurado (ver <code>docs/PUSH.md</code>).',
    ],
    'Konzert-Digest „Gleich live" (Timetable: bald startende Acts)' => [
        'en' => 'Concert digest "On soon" (timetable: acts starting soon)',
        'fr' => 'Digest concerts « Bientôt en live » (programme : artistes qui commencent bientôt)',
        'es' => 'Resumen de conciertos «En breve» (horarios: artistas que empiezan pronto)',
    ],
    'Digest-Vorlaufzeit (Minuten) – an die Cron-Frequenz anpassen' => [
        'en' => 'Digest lead time (minutes) – adapt to the cron frequency',
        'fr' => "Délai d'annonce du digest (minutes) – à adapter à la fréquence du cron",
        'es' => 'Antelación del resumen (minutos); ajústala a la frecuencia del cron',
    ],
    'Standard (60)' => ['en' => 'Default (60)', 'fr' => 'Défaut (60)', 'es' => 'Predeterminado (60)'],
    'Acts, die innerhalb dieser Zeit starten, werden (einmalig) gepusht. Bei häufigem Cron kleiner wählen (z. B. 15–20), sonst kommt der Push zu früh. Leer = 60.' => [
        'en' => 'Acts starting within this window are pushed (once). With a frequent cron choose a smaller value (e.g. 15–20), otherwise the push arrives too early. Empty = 60.',
        'fr' => 'Les artistes commençant dans ce délai sont poussés (une fois). Avec un cron fréquent, choisir plus petit (p. ex. 15–20), sinon le push arrive trop tôt. Vide = 60.',
        'es' => 'Los artistas que empiecen dentro de este margen se notifican (una vez). Con un cron frecuente elige un valor menor (p. ej. 15–20); si no, el push llega demasiado pronto. Vacío = 60.',
    ],
    'Neue News automatisch pushen' => [
        'en' => 'Automatically push new news',
        'fr' => 'Pousser automatiquement les nouvelles actus',
        'es' => 'Enviar push automáticamente con noticias nuevas',
    ],
    'Auto-Push: Kategorien' => ['en' => 'Auto-push: categories', 'fr' => 'Push auto : catégories', 'es' => 'Push automático: categorías'],
    'Welche News-Kategorien automatisch als Push gehen (sofern „Neue News automatisch pushen" aktiv ist). <b>Sicherheit</b> wird <b>immer</b> gepusht. Welche dieser Kategorien jeder Gast tatsächlich erhält, wählt er zusätzlich selbst in der App.' => [
        'en' => 'Which news categories are pushed automatically (if "Automatically push new news" is active). <b>Safety</b> is <b>always</b> pushed. Which of these categories each visitor actually receives is additionally chosen by them in the app.',
        'fr' => "Quelles catégories d'actus sont poussées automatiquement (si « Pousser automatiquement les nouvelles actus » est actif). <b>Sécurité</b> est <b>toujours</b> poussée. Chaque visiteur choisit en plus dans l'app celles qu'il reçoit réellement.",
        'es' => 'Qué categorías de noticias se envían automáticamente como push (si «Enviar push automáticamente con noticias nuevas» está activo). <b>Seguridad</b> se envía <b>siempre</b>. Cada visitante elige además en la app cuáles recibe realmente.',
    ],
    'Allgemein'  => ['en' => 'General', 'fr' => 'Général', 'es' => 'General'],
    'Info'       => ['en' => 'Info', 'fr' => 'Info', 'es' => 'Info'],
    'Sicherheit' => ['en' => 'Safety', 'fr' => 'Sécurité', 'es' => 'Seguridad'],
    'Line-Up'    => ['en' => 'Line-up', 'fr' => 'Line-up', 'es' => 'Cartel'],
    '(immer aktiv)' => ['en' => '(always active)', 'fr' => '(toujours actif)', 'es' => '(siempre activo)'],
    'Einstellungen gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Settings saved. The app picks it up within ~2 minutes.',
        'fr' => "Réglages enregistrés. L'app les reprend sous ~2 minutes.",
        'es' => 'Ajustes guardados. La app los aplica en ~2 minutos.',
    ],

    // --- News-Tab -----------------------------------------------------------
    'News verwalten' => ['en' => 'Manage news', 'fr' => 'Gérer les actus', 'es' => 'Gestionar noticias'],
    'Diese News erscheinen im Newsfeed (zusätzlich zu Telegram-Live-News). Sichtbar ab „Veröffentlichen am", optional bis „Ablauf am". „Angepinnt" und „Sicherheit" stehen oben. Text = Markdown.' => [
        'en' => 'These news appear in the news feed (in addition to Telegram live news). Visible from "Publish at", optionally until "Expires at". "Pinned" and "Safety" stay on top. Text = Markdown.',
        'fr' => "Ces actus apparaissent dans le fil (en plus des actus live Telegram). Visibles à partir de « Publier le », optionnellement jusqu'à « Expire le ». « Épinglé » et « Sécurité » restent en haut. Texte = Markdown.",
        'es' => 'Estas noticias aparecen en el feed (además de las noticias en vivo de Telegram). Visibles desde «Publicar el», opcionalmente hasta «Caduca el». «Fijado» y «Seguridad» van arriba. Texto = Markdown.',
    ],
    'Neue News' => ['en' => 'New news item', 'fr' => 'Nouvelle actu', 'es' => 'Nueva noticia'],
    'Angepinnt' => ['en' => 'Pinned', 'fr' => 'Épinglé', 'es' => 'Fijado'],
    'Veröffentlichen am' => ['en' => 'Publish at', 'fr' => 'Publier le', 'es' => 'Publicar el'],
    'Ablauf am (optional)' => ['en' => 'Expires at (optional)', 'fr' => 'Expire le (optionnel)', 'es' => 'Caduca el (opcional)'],
    'Link-Text (optional)' => ['en' => 'Link text (optional)', 'fr' => 'Texte du lien (optionnel)', 'es' => 'Texto del enlace (opcional)'],
    'Link-URL (optional)' => ['en' => 'Link URL (optional)', 'fr' => 'URL du lien (optionnel)', 'es' => 'URL del enlace (opcional)'],
    'Beim Speichern <b>sofort pushen</b> <span class="muted">(einmalig; nur wenn bereits veröffentlicht; Web-Push muss eingerichtet sein)</span>' => [
        'en' => '<b>Push immediately</b> on save <span class="muted">(once; only if already published; web push must be set up)</span>',
        'fr' => "<b>Pousser immédiatement</b> à l'enregistrement <span class=\"muted\">(une fois ; seulement si déjà publié ; le web push doit être configuré)</span>",
        'es' => '<b>Enviar push de inmediato</b> al guardar <span class="muted">(una vez; solo si ya está publicada; el web push debe estar configurado)</span>',
    ],
    'News gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'News saved. The app picks it up within ~2 minutes.',
        'fr' => "Actus enregistrées. L'app les reprend sous ~2 minutes.",
        'es' => 'Noticias guardadas. La app las aplica en ~2 minutos.',
    ],
    ' Sofort gepusht: %d zugestellt' => [
        'en' => ' Pushed immediately: %d delivered',
        'fr' => ' Poussé immédiatement : %d distribués',
        'es' => ' Push inmediato: %d entregados',
    ],
    ' · %d übersprungen (schon gepusht oder noch nicht veröffentlicht)' => [
        'en' => ' · %d skipped (already pushed or not yet published)',
        'fr' => ' · %d ignorés (déjà poussés ou pas encore publiés)',
        'es' => ' · %d omitidos (ya enviados o aún no publicados)',
    ],
    ' (Sofort-Push fehlgeschlagen: %s)' => [
        'en' => ' (immediate push failed: %s)',
        'fr' => ' (échec du push immédiat : %s)',
        'es' => ' (falló el push inmediato: %s)',
    ],

    // --- Push-Tab -----------------------------------------------------------
    'Push-Nachricht senden' => ['en' => 'Send push message', 'fr' => 'Envoyer une notification push', 'es' => 'Enviar mensaje push'],
    'Geht sofort an alle Push-Abos (Web-Push muss eingerichtet sein, siehe <code>docs/PUSH.md</code>). Für getimte/automatische Pushes siehe News &amp; Cron.' => [
        'en' => 'Goes out immediately to all push subscriptions (web push must be set up, see <code>docs/PUSH.md</code>). For scheduled/automatic pushes see News &amp; cron.',
        'fr' => 'Part immédiatement vers tous les abonnements push (le web push doit être configuré, voir <code>docs/PUSH.md</code>). Pour les push programmés/automatiques, voir Actus &amp; cron.',
        'es' => 'Se envía de inmediato a todas las suscripciones push (el web push debe estar configurado, ver <code>docs/PUSH.md</code>). Para push programados/automáticos, ver Noticias y cron.',
    ],
    'Text' => ['en' => 'Text', 'fr' => 'Texte', 'es' => 'Texto'],
    'Ziel-URL (optional)' => ['en' => 'Target URL (optional)', 'fr' => 'URL cible (optionnel)', 'es' => 'URL de destino (opcional)'],
    'Senden' => ['en' => 'Send', 'fr' => 'Envoyer', 'es' => 'Enviar'],
    'Push jetzt an alle Abos senden?' => [
        'en' => 'Send the push to all subscriptions now?',
        'fr' => 'Envoyer le push à tous les abonnements maintenant ?',
        'es' => '¿Enviar el push a todas las suscripciones ahora?',
    ],
    'Push-Titel fehlt.' => ['en' => 'Push title is missing.', 'fr' => 'Le titre du push manque.', 'es' => 'Falta el título del push.'],
    'Push gesendet: %1$d zugestellt · %2$d abgelaufen entfernt · %3$d Abos gesamt.' => [
        'en' => 'Push sent: %1$d delivered · %2$d expired removed · %3$d subscriptions total.',
        'fr' => 'Push envoyé : %1$d distribués · %2$d expirés supprimés · %3$d abonnements au total.',
        'es' => 'Push enviado: %1$d entregados · %2$d caducados eliminados · %3$d suscripciones en total.',
    ],
    'Push fehlgeschlagen: %s' => ['en' => 'Push failed: %s', 'fr' => 'Échec du push : %s', 'es' => 'Falló el push: %s'],
    'Abo-Statistik' => ['en' => 'Subscription statistics', 'fr' => "Statistiques d'abonnements", 'es' => 'Estadísticas de suscripciones'],
    '(anonym)' => ['en' => '(anonymous)', 'fr' => '(anonyme)', 'es' => '(anónimo)'],
    'Aktuelle Push-Abos und gewählte Kategorien. Es werden ausschließlich <b>Zähler</b> gespeichert – keine personenbezogenen Daten. Der Verlauf wird vom Cron (~stündlich) fortgeschrieben.' => [
        'en' => 'Current push subscriptions and chosen categories. Only <b>counters</b> are stored – no personal data. The history is extended by the cron (~hourly).',
        'fr' => "Abonnements push actuels et catégories choisies. Seuls des <b>compteurs</b> sont stockés – aucune donnée personnelle. L'historique est complété par le cron (~toutes les heures).",
        'es' => 'Suscripciones push actuales y categorías elegidas. Solo se guardan <b>contadores</b>, sin datos personales. El historial lo actualiza el cron (~cada hora).',
    ],
    'Abos gesamt' => ['en' => 'Total subscriptions', 'fr' => 'Abonnements au total', 'es' => 'Suscripciones en total'],
    'immer aktiv' => ['en' => 'always active', 'fr' => 'toujours actif', 'es' => 'siempre activo'],
    'Verlauf' => ['en' => 'History', 'fr' => 'Historique', 'es' => 'Historial'],
    'Als CSV exportieren' => ['en' => 'Export as CSV', 'fr' => 'Exporter en CSV', 'es' => 'Exportar como CSV'],
    'Zeit' => ['en' => 'Time', 'fr' => 'Heure', 'es' => 'Hora'],
    'Gesamt' => ['en' => 'Total', 'fr' => 'Total', 'es' => 'Total'],
    'Zeitpunkt' => ['en' => 'Time', 'fr' => 'Horodatage', 'es' => 'Momento'],
    'Noch keine Verlaufsdaten – der erste Snapshot entsteht beim nächsten Cron-Lauf.' => [
        'en' => 'No history yet – the first snapshot is taken on the next cron run.',
        'fr' => "Pas encore d'historique – le premier instantané sera pris au prochain passage du cron.",
        'es' => 'Aún no hay historial; la primera instantánea se toma en la próxima ejecución del cron.',
    ],
    'Abo-Statistik nicht verfügbar (DB/Push nicht eingerichtet).' => [
        'en' => 'Subscription statistics unavailable (DB/push not set up).',
        'fr' => "Statistiques d'abonnements indisponibles (BDD/push non configurés).",
        'es' => 'Estadísticas de suscripciones no disponibles (BD/push sin configurar).',
    ],

    // --- Wetter-Tab ---------------------------------------------------------
    'Wetter-Anbieter' => ['en' => 'Weather provider', 'fr' => 'Fournisseur météo', 'es' => 'Proveedor meteorológico'],
    'Anbieter (Vorhersage fürs Home-Widget + Wetterseite)' => [
        'en' => 'Provider (forecast for the home widget + weather page)',
        'fr' => "Fournisseur (prévisions pour le widget d'accueil + page météo)",
        'es' => 'Proveedor (pronóstico para el widget de inicio y la página del tiempo)',
    ],
    'Breite (Latitude)' => ['en' => 'Latitude', 'fr' => 'Latitude', 'es' => 'Latitud'],
    'Länge (Longitude)' => ['en' => 'Longitude', 'fr' => 'Longitude', 'es' => 'Longitud'],
    'Standortname (Anzeige in der App)' => [
        'en' => 'Location name (shown in the app)',
        'fr' => "Nom du lieu (affiché dans l'app)",
        'es' => 'Nombre del lugar (mostrado en la app)',
    ],
    'TAWES-Station-ID (optional, NUR GeoSphere – Messwert „aktuell")' => [
        'en' => 'TAWES station ID (optional, GeoSphere ONLY – "current" reading)',
        'fr' => 'ID de station TAWES (optionnel, GeoSphere UNIQUEMENT – mesure « actuelle »)',
        'es' => 'ID de estación TAWES (opcional, SOLO GeoSphere; medición «actual»)',
    ],
    'API-Key OpenWeather (nur bei Anbieter OpenWeather nötig)' => [
        'en' => 'OpenWeather API key (only needed with provider OpenWeather)',
        'fr' => 'Clé API OpenWeather (nécessaire seulement avec OpenWeather)',
        'es' => 'Clave API de OpenWeather (solo necesaria con el proveedor OpenWeather)',
    ],
    'API-Key WeatherAPI.com (nur bei Anbieter WeatherAPI.com nötig)' => [
        'en' => 'WeatherAPI.com API key (only needed with provider WeatherAPI.com)',
        'fr' => 'Clé API WeatherAPI.com (nécessaire seulement avec WeatherAPI.com)',
        'es' => 'Clave API de WeatherAPI.com (solo necesaria con el proveedor WeatherAPI.com)',
    ],
    'Speichern &amp; Verbindung testen' => [
        'en' => 'Save &amp; test connection',
        'fr' => 'Enregistrer &amp; tester la connexion',
        'es' => 'Guardar y probar la conexión',
    ],
    'Attribution in der App: „%s". GeoSphere/MET Norway sind ohne Key nutzbar; die Keys landen in <code>push/weather-settings.json</code> (per .htaccess gesperrt, nie im Repo).' => [
        'en' => 'Attribution in the app: "%s". GeoSphere/MET Norway work without a key; the keys are stored in <code>push/weather-settings.json</code> (blocked via .htaccess, never in the repo).',
        'fr' => "Attribution dans l'app : « %s ». GeoSphere/MET Norway fonctionnent sans clé ; les clés sont stockées dans <code>push/weather-settings.json</code> (bloqué par .htaccess, jamais dans le dépôt).",
        'es' => 'Atribución en la app: «%s». GeoSphere/MET Norway funcionan sin clave; las claves se guardan en <code>push/weather-settings.json</code> (bloqueado por .htaccess, nunca en el repositorio).',
    ],
    'Status' => ['en' => 'Status', 'fr' => 'Statut', 'es' => 'Estado'],
    'Cache vom %s' => ['en' => 'Cache from %s', 'fr' => 'Cache du %s', 'es' => 'Caché del %s'],
    '(Anbieter: %1$s, aktuell %2$s °C)' => [
        'en' => '(provider: %1$s, currently %2$s °C)',
        'fr' => '(fournisseur : %1$s, actuellement %2$s °C)',
        'es' => '(proveedor: %1$s, actualmente %2$s °C)',
    ],
    'Kein Cache vorhanden – der nächste App-Abruf holt frische Daten (TTL 15 min).' => [
        'en' => 'No cache present – the next app request fetches fresh data (TTL 15 min).',
        'fr' => "Pas de cache – la prochaine requête de l'app récupérera des données fraîches (TTL 15 min).",
        'es' => 'No hay caché; la próxima petición de la app traerá datos frescos (TTL 15 min).',
    ],
    'Wetter-Cache leeren' => ['en' => 'Clear weather cache', 'fr' => 'Vider le cache météo', 'es' => 'Vaciar la caché del tiempo'],
    'Ungültige Koordinaten (Breite -90..90, Länge -180..180).' => [
        'en' => 'Invalid coordinates (latitude -90..90, longitude -180..180).',
        'fr' => 'Coordonnées invalides (latitude -90..90, longitude -180..180).',
        'es' => 'Coordenadas no válidas (latitud -90..90, longitud -180..180).',
    ],
    'Speichern fehlgeschlagen – Schreibrechte des push-Ordners prüfen.' => [
        'en' => 'Saving failed – check write permissions of the push folder.',
        'fr' => "Échec de l'enregistrement – vérifier les droits d'écriture du dossier push.",
        'es' => 'Error al guardar; revisa los permisos de escritura de la carpeta push.',
    ],
    'Wetter-Einstellungen gespeichert (Cache geleert).' => [
        'en' => 'Weather settings saved (cache cleared).',
        'fr' => 'Réglages météo enregistrés (cache vidé).',
        'es' => 'Ajustes del tiempo guardados (caché vaciada).',
    ],
    'Verbindungstest OK: %1$d Vorhersage-Zeilen von %2$s.' => [
        'en' => 'Connection test OK: %1$d forecast rows from %2$s.',
        'fr' => 'Test de connexion OK : %1$d lignes de prévision de %2$s.',
        'es' => 'Prueba de conexión OK: %1$d filas de pronóstico de %2$s.',
    ],
    'Nächste Temperatur: %s °C.' => [
        'en' => 'Next temperature: %s °C.',
        'fr' => 'Prochaine température : %s °C.',
        'es' => 'Próxima temperatura: %s °C.',
    ],
    'Verbindungstest fehlgeschlagen: %s' => [
        'en' => 'Connection test failed: %s',
        'fr' => 'Échec du test de connexion : %s',
        'es' => 'Falló la prueba de conexión: %s',
    ],
    'Wetter-Cache geleert – der nächste App-Abruf holt frische Daten.' => [
        'en' => 'Weather cache cleared – the next app request fetches fresh data.',
        'fr' => "Cache météo vidé – la prochaine requête de l'app récupérera des données fraîches.",
        'es' => 'Caché del tiempo vaciada; la próxima petición de la app traerá datos frescos.',
    ],
    'Kein Wetter-Cache vorhanden.' => ['en' => 'No weather cache present.', 'fr' => 'Pas de cache météo.', 'es' => 'No hay caché del tiempo.'],

    // --- Statistik-Tab ------------------------------------------------------
    'Letzte 7 Tage' => ['en' => 'Last 7 days', 'fr' => '7 derniers jours', 'es' => 'Últimos 7 días'],
    'Heute' => ['en' => 'Today', 'fr' => "Aujourd'hui", 'es' => 'Hoy'],
    'Eindeutige Nutzer' => ['en' => 'Unique users', 'fr' => 'Utilisateurs uniques', 'es' => 'Usuarios únicos'],
    'Sitzungen' => ['en' => 'Sessions', 'fr' => 'Sessions', 'es' => 'Sesiones'],
    'Seitenaufrufe' => ['en' => 'Page views', 'fr' => 'Pages vues', 'es' => 'Páginas vistas'],
    'Sitzungen je Nutzer' => ['en' => 'Sessions per user', 'fr' => 'Sessions par utilisateur', 'es' => 'Sesiones por usuario'],
    'Meistgenutzte Bereiche' => ['en' => 'Most used sections', 'fr' => 'Sections les plus utilisées', 'es' => 'Secciones más usadas'],
    'Noch keine Daten.' => ['en' => 'No data yet.', 'fr' => 'Pas encore de données.', 'es' => 'Aún no hay datos.'],
    'Seite' => ['en' => 'Page', 'fr' => 'Page', 'es' => 'Página'],
    'Aufrufe' => ['en' => 'Views', 'fr' => 'Vues', 'es' => 'Vistas'],
    'Nutzer' => ['en' => 'Users', 'fr' => 'Utilisateurs', 'es' => 'Usuarios'],
    'Stunden-Verteilung' => ['en' => 'Hourly distribution', 'fr' => 'Répartition horaire', 'es' => 'Distribución por horas'],
    'Festivaltage (%s)' => ['en' => 'Festival days (%s)', 'fr' => 'Jours du festival (%s)', 'es' => 'Días del festival (%s)'],
    'letzte 7 Tage' => ['en' => 'last 7 days', 'fr' => '7 derniers jours', 'es' => 'últimos 7 días'],
    ' – an den Festivaltagen noch keine Daten' => [
        'en' => ' – no data on the festival days yet',
        'fr' => ' – pas encore de données pour les jours du festival',
        'es' => ' – aún sin datos en los días del festival',
    ],
    '%s Uhr' => ['en' => '%s h', 'fr' => '%s h', 'es' => '%s h'],
    'PWA-Installationen' => ['en' => 'PWA installs', 'fr' => 'Installations PWA', 'es' => 'Instalaciones PWA'],
    '„Installiert"-Events (Android/Chrome)' => [
        'en' => '"Installed" events (Android/Chrome)',
        'fr' => 'Événements « installé » (Android/Chrome)',
        'es' => 'Eventos de «instalado» (Android/Chrome)',
    ],
    'Geräte mit App-Start (standalone)' => [
        'en' => 'Devices launching as app (standalone)',
        'fr' => "Appareils lançant l'app (standalone)",
        'es' => 'Dispositivos que abren como app (standalone)',
    ],
    'von %s Geräten' => ['en' => 'of %s devices', 'fr' => 'sur %s appareils', 'es' => 'de %s dispositivos'],
    'iOS meldet kein Installations-Event – dort zählt nur der App-Start vom Home-Bildschirm (standalone).' => [
        'en' => 'iOS reports no install event – there only launches from the home screen count (standalone).',
        'fr' => "iOS ne signale pas d'événement d'installation – seuls comptent les lancements depuis l'écran d'accueil (standalone).",
        'es' => 'iOS no informa de eventos de instalación; allí solo cuentan los inicios desde la pantalla de inicio (standalone).',
    ],
    'Theme' => ['en' => 'Theme', 'fr' => 'Thème', 'es' => 'Tema'],
    'Dunkel' => ['en' => 'Dark', 'fr' => 'Sombre', 'es' => 'Oscuro'],
    'Hell' => ['en' => 'Light', 'fr' => 'Clair', 'es' => 'Claro'],
    'je Gerät, letzter Stand' => ['en' => 'per device, latest state', 'fr' => 'par appareil, dernier état', 'es' => 'por dispositivo, último estado'],
    'Push-Abo-Verlauf' => ['en' => 'Push subscription history', 'fr' => 'Historique des abonnements push', 'es' => 'Historial de suscripciones push'],
    'Aktuell %s Abos' => ['en' => 'Currently %s subscriptions', 'fr' => 'Actuellement %s abonnements', 'es' => 'Actualmente %s suscripciones'],
    '(Infos %1$d · Line-Up %2$d · Allgemein %3$d)' => [
        'en' => '(info %1$d · line-up %2$d · general %3$d)',
        'fr' => '(infos %1$d · line-up %2$d · général %3$d)',
        'es' => '(info %1$d · cartel %2$d · general %3$d)',
    ],
    'Push-Abo-Verlauf nicht verfügbar (Push nicht eingerichtet).' => [
        'en' => 'Push subscription history unavailable (push not set up).',
        'fr' => 'Historique des abonnements push indisponible (push non configuré).',
        'es' => 'Historial de suscripciones push no disponible (push sin configurar).',
    ],
    'Anonym erhoben: zufällige Geräte-/Sitzungskennung, Seitenname, Sprache/Theme, Zeitpunkt. Keine IP-Adressen, keine User-Agents, keine personenbezogenen Daten.' => [
        'en' => 'Collected anonymously: random device/session identifier, page name, language/theme, timestamp. No IP addresses, no user agents, no personal data.',
        'fr' => "Collecte anonyme : identifiant aléatoire d'appareil/session, nom de page, langue/thème, horodatage. Pas d'adresses IP, pas de user agents, pas de données personnelles.",
        'es' => 'Recogido de forma anónima: identificador aleatorio de dispositivo/sesión, nombre de página, idioma/tema, momento. Sin direcciones IP, sin user agents, sin datos personales.',
    ],
    'Statistik zurücksetzen' => ['en' => 'Reset statistics', 'fr' => 'Réinitialiser les statistiques', 'es' => 'Restablecer estadísticas'],
    'Wirklich ALLE Statistik-Daten unwiderruflich löschen? (Push-Abo-Verlauf bleibt erhalten)' => [
        'en' => 'Really delete ALL statistics data irreversibly? (push subscription history is kept)',
        'fr' => "Vraiment supprimer TOUTES les données statistiques de façon irréversible ? (l'historique des abonnements push est conservé)",
        'es' => '¿Seguro que quieres borrar TODOS los datos estadísticos de forma irreversible? (el historial de suscripciones push se conserva)',
    ],
    'Statistik nicht verfügbar: Datenbank nicht erreichbar (MySQL-Zugang in <code>push/config.php</code> prüfen).' => [
        'en' => 'Statistics unavailable: database unreachable (check MySQL access in <code>push/config.php</code>).',
        'fr' => 'Statistiques indisponibles : base de données injoignable (vérifier l\'accès MySQL dans <code>push/config.php</code>).',
        'es' => 'Estadísticas no disponibles: base de datos inaccesible (revisa el acceso MySQL en <code>push/config.php</code>).',
    ],
    'Statistik zurückgesetzt (%d Einträge gelöscht).' => [
        'en' => 'Statistics reset (%d entries deleted).',
        'fr' => 'Statistiques réinitialisées (%d entrées supprimées).',
        'es' => 'Estadísticas restablecidas (%d entradas eliminadas).',
    ],
    'Zurücksetzen fehlgeschlagen: %s' => [
        'en' => 'Reset failed: %s',
        'fr' => 'Échec de la réinitialisation : %s',
        'es' => 'Falló el restablecimiento: %s',
    ],

    // --- Protokoll-Tab ------------------------------------------------------
    'Stufe' => ['en' => 'Level', 'fr' => 'Niveau', 'es' => 'Nivel'],
    'alle' => ['en' => 'all', 'fr' => 'tous', 'es' => 'todos'],
    'Filtern' => ['en' => 'Filter', 'fr' => 'Filtrer', 'es' => 'Filtrar'],
    '(neueste zuerst, max. 200)' => [
        'en' => '(newest first, max. 200)',
        'fr' => "(plus récents d'abord, max. 200)",
        'es' => '(más recientes primero, máx. 200)',
    ],
    'Keine Einträge' => ['en' => 'No entries', 'fr' => 'Aucune entrée', 'es' => 'Sin entradas'],
    ' für diesen Filter' => ['en' => ' for this filter', 'fr' => ' pour ce filtre', 'es' => ' para este filtro'],
    ' – das Protokoll füllt sich mit Push-Versand, Logins, Wetter- und App-Fehlern' => [
        'en' => ' – the log fills with push delivery, logins, weather and app errors',
        'fr' => ' – le journal se remplit avec les envois push, connexions, erreurs météo et app',
        'es' => ' – el registro se llena con envíos push, inicios de sesión y errores del tiempo y de la app',
    ],
    'Meldung' => ['en' => 'Message', 'fr' => 'Message', 'es' => 'Mensaje'],
    'Aufbewahrung ~90 Tage (ältere Einträge werden automatisch bereinigt). Keine IPs, keine personenbezogenen Daten.' => [
        'en' => 'Retention ~90 days (older entries are cleaned up automatically). No IPs, no personal data.',
        'fr' => "Conservation ~90 jours (les entrées plus anciennes sont purgées automatiquement). Pas d'IP, pas de données personnelles.",
        'es' => 'Retención ~90 días (las entradas antiguas se limpian automáticamente). Sin IP, sin datos personales.',
    ],
    'Protokoll leeren' => ['en' => 'Clear log', 'fr' => 'Vider le journal', 'es' => 'Vaciar el registro'],
    'Protokoll wirklich komplett leeren?' => [
        'en' => 'Really clear the entire log?',
        'fr' => 'Vraiment vider tout le journal ?',
        'es' => '¿Seguro que quieres vaciar todo el registro?',
    ],
    'Protokoll geleert (%d Einträge gelöscht).' => [
        'en' => 'Log cleared (%d entries deleted).',
        'fr' => 'Journal vidé (%d entrées supprimées).',
        'es' => 'Registro vaciado (%d entradas eliminadas).',
    ],
    'Leeren fehlgeschlagen: %s' => ['en' => 'Clearing failed: %s', 'fr' => 'Échec du vidage : %s', 'es' => 'Falló el vaciado: %s'],

    // --- Branding-Tab (Paket Y) ---------------------------------------------
    'Branding' => ['en' => 'Branding', 'fr' => 'Branding', 'es' => 'Branding'],
    'Farben, Logo, Titel, Schrift und App-Icons der Besucher-App – vorausgefüllt mit den Standardwerten. Änderungen wirken ohne Neu-Build binnen ~2 Minuten.' => [
        'en' => 'Colors, logo, title, font and app icons of the visitor app – pre-filled with the defaults. Changes take effect without a rebuild within ~2 minutes.',
        'fr' => "Couleurs, logo, titre, police et icônes de l'app visiteurs – préremplis avec les valeurs par défaut. Les changements prennent effet sans rebuild sous ~2 minutes.",
        'es' => 'Colores, logo, título, tipografía e iconos de la app de visitantes, prellenados con los valores predeterminados. Los cambios se aplican sin rebuild en ~2 minutos.',
    ],
    'Titel & App-Name' => ['en' => 'Title & app name', 'fr' => "Titre & nom de l'app", 'es' => 'Título y nombre de la app'],
    'Browser-Titel / App-Name (leer = Festivalname)' => [
        'en' => 'Browser title / app name (empty = festival name)',
        'fr' => "Titre du navigateur / nom de l'app (vide = nom du festival)",
        'es' => 'Título del navegador / nombre de la app (vacío = nombre del festival)',
    ],
    'Kurzname (Home-Bildschirm, max. 12 Zeichen; leer = Festival-Kurzname)' => [
        'en' => 'Short name (home screen, max. 12 characters; empty = festival short name)',
        'fr' => "Nom court (écran d'accueil, 12 caractères max ; vide = nom court du festival)",
        'es' => 'Nombre corto (pantalla de inicio, máx. 12 caracteres; vacío = nombre corto del festival)',
    ],
    'Schrift' => ['en' => 'Font', 'fr' => 'Police', 'es' => 'Tipografía'],
    'Schrift-Set (systemweite Schriften, keine Downloads nötig)' => [
        'en' => 'Font set (system fonts, no downloads needed)',
        'fr' => 'Jeu de polices (polices système, aucun téléchargement nécessaire)',
        'es' => 'Conjunto tipográfico (fuentes del sistema, sin descargas)',
    ],
    'Standard (Oswald & Inter, kondensiert)' => [
        'en' => 'Default (Oswald & Inter, condensed)',
        'fr' => 'Standard (Oswald & Inter, condensé)',
        'es' => 'Predeterminado (Oswald e Inter, condensada)',
    ],
    'System (neutral, Gerätestandard)' => [
        'en' => 'System (neutral, device default)',
        'fr' => "Système (neutre, standard de l'appareil)",
        'es' => 'Sistema (neutral, estándar del dispositivo)',
    ],
    'Serif (klassisch, Georgia)' => [
        'en' => 'Serif (classic, Georgia)',
        'fr' => 'Serif (classique, Georgia)',
        'es' => 'Serif (clásica, Georgia)',
    ],
    'Plakativ (fett, Arial Black)' => [
        'en' => 'Poster (bold, Arial Black)',
        'fr' => 'Affiche (gras, Arial Black)',
        'es' => 'Cartel (negrita, Arial Black)',
    ],
    'Farben' => ['en' => 'Colors', 'fr' => 'Couleurs', 'es' => 'Colores'],
    'Akzentfarbe' => ['en' => 'Accent color', 'fr' => "Couleur d'accent", 'es' => 'Color de acento'],
    'Sekundärfarbe' => ['en' => 'Secondary color', 'fr' => 'Couleur secondaire', 'es' => 'Color secundario'],
    'Dunkles Theme' => ['en' => 'Dark theme', 'fr' => 'Thème sombre', 'es' => 'Tema oscuro'],
    'Helles Theme' => ['en' => 'Light theme', 'fr' => 'Thème clair', 'es' => 'Tema claro'],
    'Hintergrund' => ['en' => 'Background', 'fr' => 'Fond', 'es' => 'Fondo'],
    'Fläche' => ['en' => 'Surface', 'fr' => 'Surface', 'es' => 'Superficie'],
    'Fläche 2' => ['en' => 'Surface 2', 'fr' => 'Surface 2', 'es' => 'Superficie 2'],
    'Gedämpfter Text' => ['en' => 'Muted text', 'fr' => 'Texte atténué', 'es' => 'Texto atenuado'],
    'Rahmen' => ['en' => 'Border', 'fr' => 'Bordure', 'es' => 'Borde'],
    'Beim Speichern alle Farben auf die Standardwerte zurücksetzen' => [
        'en' => 'Reset all colors to the defaults when saving',
        'fr' => "Réinitialiser toutes les couleurs aux valeurs par défaut à l'enregistrement",
        'es' => 'Restablecer todos los colores a los valores predeterminados al guardar',
    ],
    'Logo' => ['en' => 'Logo', 'fr' => 'Logo', 'es' => 'Logo'],
    'Ersetzt das Kopfzeilen-Logo der App (Querformat empfohlen, wird 36 px hoch angezeigt). Leer = mitgeliefertes Logo.' => [
        'en' => "Replaces the app's header logo (landscape recommended, displayed 36 px tall). Empty = bundled logo.",
        'fr' => "Remplace le logo d'en-tête de l'app (paysage recommandé, affiché sur 36 px de haut). Vide = logo fourni.",
        'es' => 'Sustituye el logo de la cabecera de la app (se recomienda horizontal; se muestra con 36 px de alto). Vacío = logo incluido.',
    ],
    'Logo entfernen' => ['en' => 'Remove logo', 'fr' => 'Retirer le logo', 'es' => 'Quitar el logo'],
    'App-Icon (PWA)' => ['en' => 'App icon (PWA)', 'fr' => "Icône de l'app (PWA)", 'es' => 'Icono de la app (PWA)'],
    'Quadratisches PNG hochladen (mindestens 192×192, empfohlen 512×512; transparenter Hintergrund möglich). Daraus werden die Install-Icons erzeugt (192, 512 und „maskable" mit der dunklen Hintergrundfarbe). Bereits installierte Apps übernehmen neue Icons erst verzögert.' => [
        'en' => 'Upload a square PNG (at least 192×192, recommended 512×512; transparent background allowed). The install icons are generated from it (192, 512 and "maskable" with the dark background color). Already installed apps pick up new icons with a delay.',
        'fr' => "Téléverse un PNG carré (au moins 192×192, recommandé 512×512 ; fond transparent possible). Les icônes d'installation en sont générées (192, 512 et « maskable » avec la couleur de fond sombre). Les apps déjà installées reprennent les nouvelles icônes avec du retard.",
        'es' => 'Sube un PNG cuadrado (mínimo 192×192, recomendado 512×512; fondo transparente permitido). A partir de él se generan los iconos de instalación (192, 512 y «maskable» con el color de fondo oscuro). Las apps ya instaladas adoptan los iconos nuevos con retraso.',
    ],
    'Vorschau' => ['en' => 'Preview', 'fr' => 'Aperçu', 'es' => 'Vista previa'],
    'Icons entfernen' => ['en' => 'Remove icons', 'fr' => 'Retirer les icônes', 'es' => 'Quitar los iconos'],
    'Branding gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Branding saved. The app picks it up within ~2 minutes.',
        'fr' => "Branding enregistré. L'app le reprend sous ~2 minutes.",
        'es' => 'Branding guardado. La app lo aplica en ~2 minutos.',
    ],
    'Logo hochgeladen. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Logo uploaded. The app picks it up within ~2 minutes.',
        'fr' => "Logo téléversé. L'app le reprend sous ~2 minutes.",
        'es' => 'Logo subido. La app lo aplica en ~2 minutos.',
    ],
    'Logo entfernt – die App zeigt wieder das mitgelieferte Logo.' => [
        'en' => 'Logo removed – the app shows the bundled logo again.',
        'fr' => "Logo retiré – l'app affiche à nouveau le logo fourni.",
        'es' => 'Logo eliminado; la app vuelve a mostrar el logo incluido.',
    ],
    'App-Icons erzeugt (192, 512, maskable). Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'App icons generated (192, 512, maskable). The app picks them up within ~2 minutes.',
        'fr' => "Icônes de l'app générées (192, 512, maskable). L'app les reprend sous ~2 minutes.",
        'es' => 'Iconos de la app generados (192, 512, maskable). La app los aplica en ~2 minutos.',
    ],
    'Icons entfernt – es gelten wieder die mitgelieferten App-Icons.' => [
        'en' => 'Icons removed – the bundled app icons apply again.',
        'fr' => "Icônes retirées – les icônes fournies s'appliquent à nouveau.",
        'es' => 'Iconos eliminados; vuelven a regir los iconos incluidos.',
    ],
    'Die PHP-Erweiterung GD fehlt auf dem Server – Icons können nicht erzeugt werden.' => [
        'en' => 'The PHP GD extension is missing on the server – icons cannot be generated.',
        'fr' => "L'extension PHP GD manque sur le serveur – les icônes ne peuvent pas être générées.",
        'es' => 'Falta la extensión GD de PHP en el servidor; no se pueden generar los iconos.',
    ],
    'PNG konnte nicht gelesen werden.' => [
        'en' => 'The PNG could not be read.',
        'fr' => 'Le PNG n\'a pas pu être lu.',
        'es' => 'No se pudo leer el PNG.',
    ],
    'Bild zu klein – mindestens 192×192 Pixel.' => [
        'en' => 'Image too small – at least 192×192 pixels.',
        'fr' => 'Image trop petite – au moins 192×192 pixels.',
        'es' => 'Imagen demasiado pequeña; mínimo 192×192 píxeles.',
    ],
    'Nur PNG erlaubt.' => ['en' => 'Only PNG allowed.', 'fr' => 'Seul le PNG est autorisé.', 'es' => 'Solo se permite PNG.'],

    // --- Hilfe-Tab ----------------------------------------------------------
    'Handbücher' => ['en' => 'Manuals', 'fr' => 'Manuels', 'es' => 'Manuales'],
    'Alle Handbücher als Markdown-Dateien, jeweils in Deutsch, Englisch, Französisch und Spanisch. Sie werden mit der App ausgeliefert (Ordner /docs).' => [
        'en' => 'All manuals as Markdown files, each in German, English, French and Spanish. They are shipped with the app (folder /docs).',
        'fr' => "Tous les manuels en fichiers Markdown, chacun en allemand, anglais, français et espagnol. Ils sont livrés avec l'app (dossier /docs).",
        'es' => 'Todos los manuales como archivos Markdown, cada uno en alemán, inglés, francés y español. Se entregan con la app (carpeta /docs).',
    ],
    'Standard-Sprache der App (solange der Gast nicht selbst wählt)' => [
        'en' => 'Default app language (until the visitor picks one themselves)',
        'fr' => "Langue par défaut de l'app (tant que le visiteur n'a pas choisi)",
        'es' => 'Idioma predeterminado de la app (mientras el visitante no elija)',
    ],
    'Build-Standard' => ['en' => 'Build default', 'fr' => 'Standard du build', 'es' => 'Estándar del build'],
    // --- Intro-Video auf Home (Branding-Tab) ---
    'Intro-Video (Home)' => ['en' => 'Intro video (home)', 'fr' => 'Vidéo d’intro (accueil)', 'es' => 'Vídeo de intro (inicio)'],
    'Wird in voller Breite oberhalb des Newsfeeds angezeigt. Quelle „Link/Datei": direkte Videodatei (per FTP hochgeladen oder https-Link; YouTube/Vimeo werden automatisch als Player eingebettet). Quelle „Microsoft-Cloud": in OneDrive/SharePoint „Einbetten" wählen und die iframe-URL eintragen.' => [
        'en' => 'Shown full-width above the news feed. Source "Link/file": a direct video file (uploaded via FTP or an https link; YouTube/Vimeo are embedded as players automatically). Source "Microsoft cloud": choose "Embed" in OneDrive/SharePoint and paste the iframe URL.',
        'fr' => "Affichée en pleine largeur au-dessus du fil d'actualités. Source « Lien/fichier » : fichier vidéo direct (téléversé par FTP ou lien https ; YouTube/Vimeo sont intégrés automatiquement). Source « Cloud Microsoft » : choisir « Incorporer » dans OneDrive/SharePoint et coller l'URL de l'iframe.",
        'es' => 'Se muestra a ancho completo encima del feed de noticias. Fuente «Enlace/archivo»: archivo de vídeo directo (subido por FTP o enlace https; YouTube/Vimeo se incrustan automáticamente). Fuente «Nube de Microsoft»: elegir «Insertar» en OneDrive/SharePoint y pegar la URL del iframe.',
    ],
    'Quelle' => ['en' => 'Source', 'fr' => 'Source', 'es' => 'Fuente'],
    'Link/Datei (FTP, YouTube, Vimeo)' => ['en' => 'Link/file (FTP, YouTube, Vimeo)', 'fr' => 'Lien/fichier (FTP, YouTube, Vimeo)', 'es' => 'Enlace/archivo (FTP, YouTube, Vimeo)'],
    'Microsoft-Cloud (OneDrive/SharePoint-Einbetten-Link)' => [
        'en' => 'Microsoft cloud (OneDrive/SharePoint embed link)',
        'fr' => 'Cloud Microsoft (lien d’intégration OneDrive/SharePoint)',
        'es' => 'Nube de Microsoft (enlace de inserción de OneDrive/SharePoint)',
    ],
    'Video-URL (leer = Video entfernen)' => ['en' => 'Video URL (empty = remove video)', 'fr' => 'URL de la vidéo (vide = retirer la vidéo)', 'es' => 'URL del vídeo (vacío = quitar el vídeo)'],
    'Video auf der Home-Seite anzeigen' => ['en' => 'Show the video on the home page', 'fr' => "Afficher la vidéo sur la page d'accueil", 'es' => 'Mostrar el vídeo en la página de inicio'],
    'Video-URL muss mit https:// (oder /data/uploads/…) beginnen.' => [
        'en' => 'The video URL must start with https:// (or /data/uploads/…).',
        'fr' => "L'URL de la vidéo doit commencer par https:// (ou /data/uploads/…).",
        'es' => 'La URL del vídeo debe empezar por https:// (o /data/uploads/…).',
    ],
    'Intro-Video entfernt.' => ['en' => 'Intro video removed.', 'fr' => 'Vidéo d’intro retirée.', 'es' => 'Vídeo de intro quitado.'],
    'Intro-Video gespeichert (%s).' => ['en' => 'Intro video saved (%s).', 'fr' => 'Vidéo d’intro enregistrée (%s).', 'es' => 'Vídeo de intro guardado (%s).'],
    'aktiv' => ['en' => 'active', 'fr' => 'active', 'es' => 'activo'],
    'deaktiviert' => ['en' => 'disabled', 'fr' => 'désactivée', 'es' => 'desactivado'],

    // --- Update-Tab (Task #92.4, 1-Klick-Updater) ---
    'Update' => ['en' => 'Update', 'fr' => 'Mise à jour', 'es' => 'Actualización'],
    'App-Update einspielen' => ['en' => 'Apply app update', 'fr' => 'Appliquer la mise à jour', 'es' => 'Aplicar actualización'],
    'Installierte Version: %s' => ['en' => 'Installed version: %s', 'fr' => 'Version installée : %s', 'es' => 'Versión instalada: %s'],
    'unbekannt (keine VERSION-Datei – Installation stammt nicht aus einem Release-Paket)' => [
        'en' => 'unknown (no VERSION file – this installation does not come from a release package)',
        'fr' => "inconnue (pas de fichier VERSION – l'installation ne provient pas d'un paquet de release)",
        'es' => 'desconocida (sin archivo VERSION; la instalación no procede de un paquete de release)',
    ],
    'Hier das Update-Paket (festivadget-update-v*.zip) hochladen. Deine Inhalte bleiben unangetastet: data/ (Inhalte, Uploads, Branding), push/config.php sowie CMS-/Wetter-Einstellungen werden nie überschrieben.' => [
        'en' => 'Upload the update package (festivadget-update-v*.zip) here. Your content stays untouched: data/ (content, uploads, branding), push/config.php and the CMS/weather settings are never overwritten.',
        'fr' => 'Téléverser ici le paquet de mise à jour (festivadget-update-v*.zip). Vos contenus restent intacts : data/ (contenus, uploads, branding), push/config.php et les réglages CMS/météo ne sont jamais écrasés.',
        'es' => 'Subir aquí el paquete de actualización (festivadget-update-v*.zip). Tu contenido queda intacto: data/ (contenidos, subidas, branding), push/config.php y los ajustes de CMS/meteo nunca se sobrescriben.',
    ],
    'Update jetzt einspielen? Die App ist währenddessen kurz inkonsistent.' => [
        'en' => 'Apply the update now? The app is briefly inconsistent while it runs.',
        'fr' => "Appliquer la mise à jour maintenant ? L'app est brièvement incohérente pendant l'opération.",
        'es' => 'Aplicar la actualización ahora? La app queda brevemente inconsistente durante el proceso.',
    ],
    'Update einspielen' => ['en' => 'Apply update', 'fr' => 'Appliquer la mise à jour', 'es' => 'Aplicar actualización'],
    'Alternative ohne CMS (Minimal): Update-Paket entpacken und per FTP über die Installation kopieren – data/ und push/config.php sind im Update-Paket nicht enthalten. Details: Hilfe → Installation.' => [
        'en' => 'Alternative without the CMS (minimal): extract the update package and copy it over the installation via FTP – data/ and push/config.php are not part of the update package. Details: Help → Installation.',
        'fr' => "Alternative sans CMS (minimale) : décompresser le paquet et le copier par FTP par-dessus l'installation – data/ et push/config.php ne font pas partie du paquet. Détails : Aide → Installation.",
        'es' => 'Alternativa sin CMS (mínima): descomprimir el paquete y copiarlo por FTP sobre la instalación; data/ y push/config.php no forman parte del paquete. Detalles: Ayuda → Instalación.',
    ],
    'Maximale Upload-Größe (PHP): %s' => [
        'en' => 'Maximum upload size (PHP): %s',
        'fr' => 'Taille maximale de téléversement (PHP) : %s',
        'es' => 'Tamaño máximo de subida (PHP): %s',
    ],
    'Nur ZIP-Dateien (festivadget-update-v*.zip) erlaubt.' => [
        'en' => 'Only ZIP files (festivadget-update-v*.zip) are allowed.',
        'fr' => 'Seuls les fichiers ZIP (festivadget-update-v*.zip) sont autorisés.',
        'es' => 'Solo se permiten archivos ZIP (festivadget-update-v*.zip).',
    ],
    'Upload konnte nicht gespeichert werden.' => [
        'en' => 'Could not store the upload.',
        'fr' => "Impossible d'enregistrer le téléversement.",
        'es' => 'No se pudo guardar la subida.',
    ],
    'Update eingespielt: %1$d Dateien aktualisiert, %2$d geschützte übersprungen. Installierte Version: %3$s.' => [
        'en' => 'Update applied: %1$d files updated, %2$d protected files skipped. Installed version: %3$s.',
        'fr' => 'Mise à jour appliquée : %1$d fichiers mis à jour, %2$d fichiers protégés ignorés. Version installée : %3$s.',
        'es' => 'Actualización aplicada: %1$d archivos actualizados, %2$d protegidos omitidos. Versión instalada: %3$s.',
    ],
    'ZIP-Datei konnte nicht gelesen werden.' => [
        'en' => 'Could not read the ZIP file.',
        'fr' => 'Impossible de lire le fichier ZIP.',
        'es' => 'No se pudo leer el archivo ZIP.',
    ],
    'Am Server fehlt die PHP-Erweiterung zip (und phar).' => [
        'en' => 'The server is missing the PHP zip extension (and phar).',
        'fr' => "L'extension PHP zip (et phar) manque sur le serveur.",
        'es' => 'Al servidor le falta la extensión PHP zip (y phar).',
    ],
    'Das ist kein Festivadget-Update-Paket.' => [
        'en' => 'This is not a Festivadget update package.',
        'fr' => "Ce n'est pas un paquet de mise à jour Festivadget.",
        'es' => 'Esto no es un paquete de actualización de Festivadget.',
    ],
    'Das ist das VOLLE Release-Paket (enthält data/) – zum Updaten bitte das Update-Paket (festivadget-update-v*.zip) verwenden.' => [
        'en' => 'This is the FULL release package (contains data/) – please use the update package (festivadget-update-v*.zip) for updates.',
        'fr' => "C'est le paquet de release COMPLET (contient data/) – pour les mises à jour, utiliser le paquet de mise à jour (festivadget-update-v*.zip).",
        'es' => 'Este es el paquete de release COMPLETO (contiene data/); para actualizar usa el paquete de actualización (festivadget-update-v*.zip).',
    ],
    'Unsicherer Pfad im Paket – Update abgebrochen.' => [
        'en' => 'Unsafe path in the package – update aborted.',
        'fr' => 'Chemin non sûr dans le paquet – mise à jour interrompue.',
        'es' => 'Ruta insegura en el paquete; actualización cancelada.',
    ],
    'Paket unvollständig lesbar – Update abgebrochen.' => [
        'en' => 'Package not fully readable – update aborted.',
        'fr' => 'Paquet partiellement illisible – mise à jour interrompue.',
        'es' => 'Paquete no legible por completo; actualización cancelada.',
    ],
    'Schreiben fehlgeschlagen (Datei-Rechte prüfen) – Update unvollständig!' => [
        'en' => 'Write failed (check file permissions) – update incomplete!',
        'fr' => "Échec d'écriture (vérifier les droits) – mise à jour incomplète !",
        'es' => 'Fallo de escritura (revisar permisos); ¡actualización incompleta!',
    ],
    'Installation (Web-Installer)' => ['en' => 'Installation (web installer)', 'fr' => 'Installation (installeur web)', 'es' => 'Instalación (instalador web)'],
    'Release-Paket hochladen und im Browser einrichten – ohne Build-Maschine.' => [
        'en' => 'Upload the release package and set it up in the browser – no build machine.',
        'fr' => 'Téléverser le paquet de release et configurer dans le navigateur – sans machine de build.',
        'es' => 'Subir el paquete de release y configurarlo en el navegador, sin máquina de build.',
    ],
    'Admin-UI (CMS)' => ['en' => 'Admin UI (CMS)', 'fr' => "Interface d'administration (CMS)", 'es' => 'Interfaz de administración (CMS)'],
    'Bedienung dieser Admin-Oberfläche (Tabs, Overrides, Importer).' => [
        'en' => 'How to use this admin interface (tabs, overrides, importer).',
        'fr' => "Utilisation de cette interface d'administration (onglets, overrides, importeur).",
        'es' => 'Manejo de esta interfaz de administración (pestañas, overrides, importador).',
    ],
    'Daten pflegen & anbinden' => [
        'en' => 'Maintaining & connecting data',
        'fr' => 'Gérer & connecter les données',
        'es' => 'Mantener y conectar datos',
    ],
    'Inhalte ersetzen, Joomla/WordPress-Anbindung, Felder & Icons.' => [
        'en' => 'Replacing content, Joomla/WordPress connection, fields & icons.',
        'fr' => 'Remplacer les contenus, connexion Joomla/WordPress, champs & icônes.',
        'es' => 'Sustituir contenidos, conexión Joomla/WordPress, campos e iconos.',
    ],
    'Web-Push einrichten' => ['en' => 'Setting up web push', 'fr' => 'Configurer le web push', 'es' => 'Configurar el web push'],
    'VAPID, MySQL, Cron, Push-Kategorien, „Mein Plan"-Erinnerungen.' => [
        'en' => 'VAPID, MySQL, cron, push categories, "My plan" reminders.',
        'fr' => 'VAPID, MySQL, cron, catégories de push, rappels « Mon planning ».',
        'es' => 'VAPID, MySQL, cron, categorías de push, recordatorios de «Mi plan».',
    ],
    'Telegram-Live-News' => ['en' => 'Telegram live news', 'fr' => 'Actus live Telegram', 'es' => 'Noticias en vivo por Telegram'],
    'Bot einrichten, Tags, Befehle, Gruppen.' => [
        'en' => 'Setting up the bot, tags, commands, groups.',
        'fr' => 'Configurer le bot, tags, commandes, groupes.',
        'es' => 'Configurar el bot, etiquetas, comandos, grupos.',
    ],
    'Technisches Konzept' => ['en' => 'Technical concept', 'fr' => 'Concept technique', 'es' => 'Concepto técnico'],
    'Architektur, Datenmodell, Caching, Roadmap.' => [
        'en' => 'Architecture, data model, caching, roadmap.',
        'fr' => 'Architecture, modèle de données, cache, feuille de route.',
        'es' => 'Arquitectura, modelo de datos, caché, hoja de ruta.',
    ],
    'Noch keine Handbücher auf dem Server – sie kommen mit dem nächsten App-Deployment („deploy-data.bat full", Ordner /docs).' => [
        'en' => 'No manuals on the server yet – they arrive with the next app deployment ("deploy-data.bat full", folder /docs).',
        'fr' => "Pas encore de manuels sur le serveur – ils arrivent avec le prochain déploiement de l'app (« deploy-data.bat full », dossier /docs).",
        'es' => 'Aún no hay manuales en el servidor; llegan con el próximo despliegue de la app («deploy-data.bat full», carpeta /docs).',
    ],

    // --- Generische Speicher-Meldungen --------------------------------------
    'Gespeichert. Die App übernimmt es binnen ~2 Minuten (oder beim Neuladen).' => [
        'en' => 'Saved. The app picks it up within ~2 minutes (or on reload).',
        'fr' => "Enregistré. L'app le reprend sous ~2 minutes (ou au rechargement).",
        'es' => 'Guardado. La app lo aplica en ~2 minutos (o al recargar).',
    ],
    'Speichern fehlgeschlagen – Schreibrechte des data-Ordners prüfen.' => [
        'en' => 'Saving failed – check write permissions of the data folder.',
        'fr' => "Échec de l'enregistrement – vérifier les droits d'écriture du dossier data.",
        'es' => 'Error al guardar; revisa los permisos de escritura de la carpeta data.',
    ],
    'Infos gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Info pages saved. The app picks it up within ~2 minutes.',
        'fr' => "Infos enregistrées. L'app les reprend sous ~2 minutes.",
        'es' => 'Información guardada. La app la aplica en ~2 minutos.',
    ],
    'Gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Saved. The app picks it up within ~2 minutes.',
        'fr' => "Enregistré. L'app le reprend sous ~2 minutes.",
        'es' => 'Guardado. La app lo aplica en ~2 minutos.',
    ],
    'Timetable gespeichert. Übernahme in der App binnen ~2 Minuten.' => [
        'en' => 'Timetable saved. The app picks it up within ~2 minutes.',
        'fr' => "Programme enregistré. L'app le reprend sous ~2 minutes.",
        'es' => 'Horarios guardados. La app los aplica en ~2 minutos.',
    ],
    'Override entfernt – die App nutzt wieder den Build-Stand.' => [
        'en' => 'Override removed – the app uses the build state again.',
        'fr' => "Override retiré – l'app utilise à nouveau l'état du build.",
        'es' => 'Override eliminado; la app vuelve a usar el estado del build.',
    ],
    'Unbekannte Domäne.' => ['en' => 'Unknown domain.', 'fr' => 'Domaine inconnu.', 'es' => 'Dominio desconocido.'],
    'Ungültiges JSON: %s' => ['en' => 'Invalid JSON: %s', 'fr' => 'JSON invalide : %s', 'es' => 'JSON no válido: %s'],
    'Erwartet wird eine Liste [ … ].' => [
        'en' => 'A list [ … ] is expected.',
        'fr' => 'Une liste [ … ] est attendue.',
        'es' => 'Se espera una lista [ … ].',
    ],
    'Erwartet wird ein Objekt { … }.' => [
        'en' => 'An object { … } is expected.',
        'fr' => 'Un objet { … } est attendu.',
        'es' => 'Se espera un objeto { … }.',
    ],

    // --- Importer (Report-Texte) --------------------------------------------
    '⚠️ 0 Datensätze – nichts geschrieben.' => [
        'en' => '⚠️ 0 records – nothing written.',
        'fr' => '⚠️ 0 enregistrements – rien écrit.',
        'es' => '⚠️ 0 registros; no se escribió nada.',
    ],
    '✅ %d importiert.' => ['en' => '✅ %d imported.', 'fr' => '✅ %d importés.', 'es' => '✅ %d importados.'],
    '❌ Schreiben fehlgeschlagen (Schreibrechte?).' => [
        'en' => '❌ Writing failed (write permissions?).',
        'fr' => "❌ Échec d'écriture (droits d'écriture ?).",
        'es' => '❌ Falló la escritura (¿permisos de escritura?).',
    ],
    '✅ aus %s importiert.' => ['en' => '✅ imported from %s.', 'fr' => '✅ importé depuis %s.', 'es' => '✅ importado de %s.'],
    '⚠️ aus %s geholt, aber Text war leer (Titel übernommen).' => [
        'en' => '⚠️ fetched from %s, but the text was empty (title applied).',
        'fr' => '⚠️ récupéré depuis %s, mais le texte était vide (titre repris).',
        'es' => '⚠️ obtenido de %s, pero el texto estaba vacío (título aplicado).',
    ],
    'Locator (Artikel-ID/Slug) fehlt.' => [
        'en' => 'Locator (article ID/slug) is missing.',
        'fr' => "Le locator (ID d'article/slug) manque.",
        'es' => 'Falta el locator (ID de artículo/slug).',
    ],
];
