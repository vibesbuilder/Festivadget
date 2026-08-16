# Interface d'administration (CMS)

**🇩🇪 [Deutsch](ADMIN.md) · 🇬🇧 [English](ADMIN.en.md) · 🇪🇸 [Español](ADMIN.es.md)**

Interface web protégée par mot de passe, hébergée sur le même espace web, pour
piloter l'app **sans redéploiement**. Située sous `push/cms/`, elle réutilise
l'authentification existante de `push/`.

## Architecture

```
push/cms/index.php  ──(écrit)──►  data/app-config.json   ──(l'app lit en direct, poll 2 min)──►  React
   │                              data/app-info.json   (phase 2)
   └─ login : adminPasswordHash   data/live-news.json  (phase 4, déjà présent)
      depuis push/config.php
```

- **Auth :** session + `password_verify` contre `adminPasswordHash` de
  `push/config.php` (même mot de passe que `push/admin.php`). Jeton CSRF à
  chaque enregistrement.
- **Persistance :** fichiers JSON propres au serveur dans le dossier `data/`
  (= `dataDir` de `push/config.php`). L'app les lit en direct, comme les actus
  live Telegram. Ces fichiers appartiennent au serveur et ne sont **jamais**
  construits/commités localement (ils sont dans `.gitignore`).
- **Client :** `src/data/useAppConfig.ts` charge `data/app-config.json`
  (poll de 2 min, repli = valeurs par défaut si le fichier manque).

## Accès

`https://app.rockimdorf.at/push/cms/` → se connecter avec le mot de passe admin.
Après la connexion, l'onglet **« Réglages »** s'ouvre (onglet de départ).

## Langue de l'interface

Le CMS est disponible en **allemand, anglais, français et espagnol**. La langue
se change dans l'onglet **« Réglages »** → « Langue du CMS » et s'applique côté
serveur pour tous les admins (stockée dans `push/cms-settings.json`, bloquée
par `.htaccess`, jamais dans le dépôt). L'allemand est la langue source ; la
table de traduction se trouve dans `push/cms/i18n.php` (fonction `cms_t()`),
les clés manquantes retombent sur l'allemand. La **langue de l'app** est
choisie indépendamment par chaque visiteur dans l'app elle-même
(allemand/anglais/français/espagnol).

## Onglet Aide

L'onglet **« Aide »** relie tous les manuels (ADMIN, DATEN, PUSH, TELEGRAM,
IMPLEMENTATION) en fichiers Markdown dans les quatre langues. Les fichiers sont
copiés vers `dist/docs/` au build de l'app et téléversés avec
`deploy-data.bat full` (accessibles sous `/docs/<nom>.md`) ; l'onglet masque
les fichiers manquants. Image de fond personnalisée : sélectionnable parmi les
uploads sous **Réglages** → « Image de fond » (`backgroundImage` dans
`app-config.json`).

## Déploiement

Téléverser `push/cms/` par FTP dans le dossier `push/` (comme le reste de
`push/`). Prérequis : `push/config.php` avec `adminPasswordHash` défini
(`php -r "echo password_hash('TON_MOT_DE_PASSE', PASSWORD_DEFAULT);"`) et un
dossier `data/` **inscriptible** (`live-news.json` s'y trouve déjà).

## Override live générique (fondation)

`useDataset` (couche de requêtes) charge en plus, pour **chaque** domaine,
`data/app-<domaine>.json` (poll 2 min). Si ce fichier existe, il **remplace**
l'état du build `data/<domaine>.json`. Les éditeurs admin comme l'importeur
serveur s'appuient dessus : les deux écrivent `app-<domaine>.json`. S'il
manque, l'état du build s'applique inchangé. (Actus : l'onglet « Actus » écrit
`admin-news.json` ; si le fichier existe, il **remplace** `news.json` dans le
fil – le `live-news.json` de Telegram continue d'être mélangé **en plus**.)

## Paliers fonctionnels

1. **Fondation + menu Plus** ✅ — login ; visibilité des éléments du menu Plus
   (`moreHidden[]` dans `app-config.json`).
2. **Infos** ✅ — activer/désactiver, renommer, ajouter/supprimer des entrées
   (texte, ordre, icône inclus) → `data/app-info.json`. Si le fichier existe,
   il **remplace** côté client l'état du build (`info.json`) ; l'éditeur est
   pré-rempli depuis `info.json` à la première ouverture (seed). Les entrées
   masquées (`hidden`) disparaissent du menu **et** de la recherche mais
   restent accessibles par lien direct.
   **Accordéon question/réponse :** case « Afficher comme accordéon
   question/réponse » (`faq: true`) par entrée. Chaque `## titre` du texte
   devient alors une question dépliable, le texte en dessous la réponse ; le
   texte **avant** la première `## question` apparaît comme bloc d'intro
   normal. Sans `## …` dans le texte, cela reste du Markdown normal. Utilisable
   pour n'importe quelle entrée (p. ex. « Cashless »).
   **Source par entrée :** champ `source` (`manual`/`joomla`/`wordpress`) +
   `sourceLocator` (ID d'article Joomla ou slug/ID WP). « Importer depuis
   Joomla/WordPress » ne tire le titre/texte de l'article **que** pour les
   entrées ainsi marquées ; la structure et les entrées manuelles restent.
   (D'abord enregistrer, puis importer.)
3. **Réglages globaux** ✅ — `lineupImageLimit` (artistes avec image),
   `background` (fond graphique on/off), `themeDefault` (`dark`/`light`, ne
   s'applique que tant que le visiteur n'a pas choisi lui-même). Dans
   `app-config.json`.
4. **Actus & push** ✅ — éditeur d'actus (titre, texte Markdown, catégorie,
   épinglage, publication/expiration, lien optionnel) → `data/admin-news.json`.
   **Seule** gestion des actus : pré-remplie depuis `news.json` à la première
   ouverture, elle **remplace** ensuite l'état du build dans le fil
   (`useNewsFeed`) ; le `live-news.json` de Telegram est mélangé en plus. Le
   cron pousse automatiquement les nouvelles entrées (filtre par catégorie,
   voir `docs/PUSH.fr.md`). Les **catégories de push auto** se choisissent sous
   « Réglages » (`pushNewsCategories`). L'onglet Push envoie immédiatement à
   tous les abonnements (`push_broadcast` de `sender.php`).
5. **Override live pour tous les domaines** ✅ — `useDataset` préfère
   `data/app-<domaine>.json` (voir plus haut). Fondation pour 6/7.
6. **Éditeurs de contenu par domaine**
   - 6a ✅ Onglet générique **« Contenus »** : chaque domaine (festival,
     stages, artists, slots, pois, map, sponsors, tickets, weather, info, news)
     comme éditeur JSON validé (pré-rempli depuis l'état courant, contrôle
     liste/objet, « Retirer l'override » → état du build) →
     `data/app-<domaine>.json`.
   - 6a-POI ✅ **Catégories de POI** comme domaine propre (« Contenus » →
     « Catégories de POI », `app-poi-categories.json`) :
     `id`/`label`/`icon`(emoji)/`color`/`order`/`hidden`. Créer ses propres
     catégories, renommer, **afficher/masquer** (`hidden` = interrupteur
     maître, disparition totale de la carte + du filtre). Dans le formulaire
     POI, `type` est une liste déroulante de ces catégories ; par POI, une
     **icône propre** optionnelle (`icon`, remplace l'icône de la catégorie).
     Les **icônes** peuvent être : un **emoji**, un **chemin d'image**
     (téléverser sa propre image via l'onglet « Images » → `/data/uploads/…`)
     ou un **nom d'icône Lucide** (p. ex. `ambulance`, `utensils`, `parking` ;
     liste complète dans `docs/DATEN.fr.md`) – valable pour les catégories et
     les POI individuels. Les classes Font Awesome (`<i class="fa-…">`) ne
     fonctionnent **pas** directement ; téléverser l'icône FA en SVG à la
     place.
   - 6b ✅ **Téléversement d'images** (onglet « Images ») → `data/uploads/`,
     servi sous `/data/uploads/<nom>` ; utiliser le chemin dans « Contenus »
     comme `image`/`logo`.
   - 6c ✅ **Formulaires de confort** (au lieu du JSON) pour `stages`,
     `sponsors`, `pois`, `artists` (pilotés par schéma) + **éditeur tabulaire
     des créneaux** (artiste/scène/jour en listes déroulantes, horaires en
     datetime-local). Dans l'onglet « Contenus », bascule entre formulaire et
     « Modifier en JSON » ; les autres domaines restent en JSON.
> **URL de l'API Joomla :** l'importeur utilise la forme SEF
> `…/api/v1/content/articles` (sans `index.php`). Sur certains serveurs (p. ex.
> World4You), le chemin après `index.php/` est avalé (PATH_INFO) → la forme
> `index.php` renvoie alors 404 à chaque appel. Prérequis : `.htaccess` SEF
> actif avec la ligne de transfert de l'en-tête Authorization
> (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`).

7. **Importeur serveur** ✅ — onglet « Sources » : par domaine
   `manual`/`joomla`/`wordpress` + locator (Joomla : ID de catégorie, WP : slug
   de catégorie) → `data/source-config.json`. « Importer maintenant »
   (`importer.php`) récupère via curl et écrit `app-<domaine>.json`.
   Connexion/token dans `push/config.php` → `sources`. **Mapping générique
   best-effort** (id/slug/title/name/body) – convient aux contenus texte ;
   retoucher les domaines structurés (artistes/créneaux/actus) dans l'onglet
   « Contenus » si besoin. Ne pilote que l'import serveur, **indépendant** de
   `content-sources.config.ts` (import du build). Le `body` conserve du **HTML
   assaini** : titres, images et iframes d'hôtes autorisés
   (YouTube/Spotify/Google Maps) ; les images `data:`, `script` et les iframes
   étrangères sont supprimés (`cms_clean_html`). L'app rend cela en toute
   sécurité (`rehype-raw`+`rehype-sanitize`, liste blanche d'hôtes iframe
   supplémentaire côté client).

## Obtenir un jeton Bearer de l'API Joomla (pour l'importeur serveur)

Le jeton est **généré dans Joomla** (par utilisateur), il ne se « trouve » pas :

1. **Activer les plugins** (Système → Plugins) : *Web Services - Content*
   (débloque `/v1/content/articles`) et *User - Joomla API Token* (génère +
   vérifie le jeton Bearer). *L'authentification basique* n'est **pas**
   nécessaire.
2. **Accorder le droit de connexion API** (Système → Configuration globale →
   Droits) : pour le groupe de l'utilisateur API, **« Connexion aux services
   web » (`core.login.api`) = Autorisé**. Par défaut, **seul** le Super User
   l'a → s'il manque, on obtient **403 « Forbidden »**. Recommandation : un
   groupe « API » dédié et minimal (parent Public), avec ce seul droit.
3. **Générer le jeton** (Utilisateurs → Gérer → modifier l'utilisateur API) :
   onglet **« Joomla API Token »** → afficher/régénérer → copier.
4. Le saisir dans `push/config.php` → `sources.joomla.token` (SEULEMENT le
   jeton, guillemets simples, SANS `Authorization: Bearer` ; secret, ne jamais
   commiter).
5. **Locator :** par entrée d'info l'**ID d'article** (Contenu → Articles,
   colonne « ID ») ; pour l'import par domaine l'**ID de catégorie** (Contenu →
   Catégories).
6. Test (URL **sans** `index.php`, sinon 404 possible) :
   `curl -g -H "Authorization: Bearer <TOKEN>" -H "Accept: application/vnd.api+json" "https://rockimdorf.at/api/v1/content/articles"` → JSON avec des articles = ok.

**Symptômes d'erreur :** 404 partout = URL `index.php` (PATH_INFO) ou
plugin/`.htaccess` (voir plus haut). 403 = groupe sans `core.login.api`. 401 =
jeton invalide/manquant.

## app-config.json – champs

| Champ              | Type                | Signification                                              |
|--------------------|---------------------|------------------------------------------------------------|
| `moreHidden`       | `string[]`          | Éléments du menu Plus masqués (clés, voir ci-dessous).     |
| `lineupImageLimit` | `number?`           | Artistes avec image dans le line-up (sinon 20).            |
| `background`       | `boolean?`          | Fond graphique on/off (défaut : on).                       |
| `backgroundImage`  | `string?`           | Image de fond personnalisée (`/data/uploads/…`, vide = visuel fourni). |
| `themeDefault`     | `"dark"\|"light"?`  | Thème par défaut tant que le visiteur n'a pas choisi.      |

Clés du menu Plus : `news`, `map`, `info`, `sponsors`, `tickets`, `contact`,
`impressum`, `theme`, `language` (doivent correspondre à `src/routes/More.tsx`).
