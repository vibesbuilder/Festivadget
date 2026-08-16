# Configurer le web push (phase 5)

**🇩🇪 [Deutsch](PUSH.md) · 🇬🇧 [English](PUSH.en.md) · 🇪🇸 [Español](PUSH.es.md)**

Le web push permet des notifications sur l'écran de verrouillage, **même quand
l'app est fermée** – pour les annonces de sécurité, les changements de dernière
minute et les alertes « bientôt en live ».

Le backend est volontairement minimal : quelques **fichiers PHP** dans le
dossier [`push/`](../push/) sur le même **espace web** (pas de VPS nécessaire).
Prérequis : **PHP 8.1+** avec `openssl`, `mbstring` et `gmp` **ou** `bcmath`,
plus **MySQL** et **cron**.

## Vue d'ensemble

| Fichier | Rôle |
|---|---|
| `push/subscribe.php` | reçoit les abonnements du navigateur, les stocke dans MySQL |
| `push/admin.php` | page protégée par mot de passe pour l'envoi **immédiat** (+ bouton statistiques) |
| `push/cron-send.php` | via cron : digest « joue dans l'heure qui vient » |
| `push/vapid-keys.php` | génère une fois la paire de clés VAPID |
| `push/sender.php`, `db.php` | logique commune (envoi, BDD, schéma) |
| `push/weather.php`, `weather-providers.php` | endpoint météo (GeoSphere/OpenWeather/WeatherAPI.com/MET Norway, cache fichier ; réglages dans l'onglet CMS « Météo ») |
| `push/track.php`, `stats-db.php` | compteur d'usage anonyme (app → MySQL) |
| `push/stats.php` | page de statistiques (même mot de passe que admin.php) |

> **Raccourci d'upload :** `deploy-data.bat push` téléverse tous les
> `push\*.php` – sauf `config.php`/`config.example.php`/`vapid-keys.php`
> (et sans `vendor\`).

Le côté client (service worker + interrupteur « Activer les notifications »
sous **Plus**) est déjà inclus dans l'app.

## Deux machines – qui fait quoi ?

**Deux** endroits sont impliqués. Chaque étape ci-dessous est marquée :

- 💻 **PC local** (ta machine Windows avec Node/npm + le projet) : construire
  l'app, générer les clés, préparer `config.php`, tout téléverser via
  `deploy-data.bat`/FTP.
- 🌐 **Espace web** (p. ex. World4You) : c'est là que tournent les **fichiers
  PHP** + **MySQL** + **cron**. Les commandes s'y saisissent **par SSH** (si
  ton offre l'inclut) **ou** dans l'**espace client** (cron, base de données),
  voire pas du tout directement – alors tu fais tout sur le PC et tu
  téléverses seulement des fichiers.

> Règle simple : **toutes les commandes `npm …` = 💻 PC.** **PHP/cron/BDD = 🌐
> espace web.** Si ton espace web n'a **pas de SSH**, tu n'y as besoin
> d'**aucune ligne de commande** – tout se fait sur le PC, upload par FTP
> (voir les notes par étape).

## Pas à pas

### 1. 💻 Générer les clés VAPID (sur le PC, le plus simple via Node)
Dans le dossier du projet :
```bash
npx web-push generate-vapid-keys
```
Affiche la **clé publique** et la **clé privée**.
- **Clé publique** → plus tard dans le `.env` de l'app (`VITE_VAPID_PUBLIC_KEY`) **et** dans `config.php`.
- **Clé privée** → uniquement dans `config.php`. **Ne jamais commiter.**

*(Alternative sans Node : `push/vapid-keys.php` – nécessite toutefois PHP sur
le serveur/SSH. Si utilisé, supprimer ensuite le fichier du serveur.)*

### 2. 💻/🌐 Dépendance Composer (`push/vendor/`)
L'envoi utilise `minishlink/web-push` → le dossier `push/vendor/` doit être sur le serveur.
- **Avec SSH sur l'espace web :** y faire `cd push && composer install`.
- **Sans SSH (cas normal) :** exécuter `composer install` dans `push/` sur le
  **PC** et téléverser le dossier **`push/vendor/` par FTP**. (Composer requis
  sur le PC : `getcomposer.org`.)

### 3. 🌐 Créer la base MySQL
Créer une base dans l'**espace client** de l'hébergeur (noter nom/utilisateur/
mot de passe). Les tables sont créées **automatiquement** au premier accès
(`push_subscriptions`, `push_log`).

### 4. 💻→🌐 Créer `push/config.php`
Copier et remplir sur le **PC**, puis téléverser **par FTP** comme `push/config.php` :
```bash
copy push\config.example.php push\config.php   :: Windows
```
Renseigner : accès BDD (étape 3), `vapid.publicKey`/`privateKey` (étape 1),
`adminPasswordHash` (à générer sur le PC : `php -r "echo password_hash('TON_MOT_DE_PASSE', PASSWORD_DEFAULT);"`),
un `cronSecret` (chaîne aléatoire). `config.php` est gitignored.

### 5. 💻 Construire & déployer l'app avec la clé publique
Dans le `.env` de l'app (PC) :
```ini
VITE_VAPID_PUBLIC_KEY=<clé publique de l'étape 1>
```
Puis **`deploy-data.bat full`**. L'interrupteur « Notifications » sous **Plus**
n'apparaît que si cette clé est définie.

### 6. 🌐 Configurer le cron (seulement pour le digest de début de concert)
Dans l'**espace client** de l'hébergeur → tâches cron, toutes les heures :
```
0 * * * *  php /chemin/vers/push/cron-send.php
```
Seul un cron HTTP est possible ? Pointer un pinger externe (p. ex.
cron-job.org) sur
`https://app.rockimdorf.at/push/cron-send.php?key=<cronSecret>`.
*(AUCUN cron n'est nécessaire pour les notifications Telegram `#push` – elles
partent immédiatement.)*

**Fréquence du cron & latence des actus :** les push automatiques d'actus ne
partent qu'au prochain passage du cron – l'intervalle détermine donc la
**latence**. Plusieurs entrées cron (décalées), p. ex. toutes les 10–15 min, la
réduisent d'autant. Mais alors réduire aussi le **délai d'annonce du digest**
(CMS → Réglages → `upcomingWindowMin`) en conséquence (p. ex. 15–20 min), sinon
« Bientôt en live » annonce des artistes jusqu'à 60 min trop tôt. Ne **pas**
mettre plusieurs crons sur la même minute (sinon envoi théoriquement double
avant que `push_log` n'agisse) – les décaler de quelques minutes.

**Plusieurs entrées cron chez le même hébergeur :** si l'hébergeur n'accepte
pas plusieurs fois le même chemin de fichier en cron, utiliser les wrappers
fournis `push/cron-send-1.php` … `cron-send-5.php` (chacun n'inclut que
`cron-send.php` – la logique reste à un seul endroit). On crée ainsi p. ex. 6
entrées cron décalées à `:00, :10, :20, :30, :40, :50` → push toutes les
~10 min.

> Ne **pas** retarder via `sleep()` : les processus PHP longs sont peu fiables
> en hébergement mutualisé (timeouts cron HTTP ~30 s, `max_execution_time`,
> workers bloqués). Décaler plutôt via les **horaires cron** (champ minute) ou
> un pinger externe (p. ex. cron-job.org) qui appelle `cron-send.php?key=…`
> toutes les N minutes – alors **un seul** fichier suffit.

**Push immédiat (sans attendre le cron) :** dans l'onglet CMS « Actus », chaque
entrée a la case **« Pousser immédiatement »** – à l'enregistrement, l'entrée
(déjà publiée) part immédiatement (selon la catégorie, une seule fois via
`push_log`).

## Rappels « Mon planning »

Les visiteurs peuvent activer **« Mon planning »** dans le popover de
notifications (cloche dans l'en-tête) et reçoivent alors un rappel **avant le
début** de leurs artistes **favoris**. Techniquement :
- Les IDs des créneaux favoris sont stockés (IDs seulement, anonymes) dans
  l'abonnement (`push_subscriptions.plan`) et synchronisés automatiquement au
  backend à chaque changement de favoris.
- Le cron (`cron-send.php`, bloc a2) envoie **un push par artiste favori** dans
  le délai d'annonce (`upcomingWindowMin`), chaque créneau par appareil **une
  seule fois** (dédup via `push_log`, ref `plan:<hash>:<slotId>`).
- Les abonnés « Mon planning » reçoivent **toujours** le digest général
  « Bientôt en live » (= line-up), mais **sans leurs artistes favoris** – ceux-ci
  arrivent en rappel individuel personnel. Aucun artiste n'apparaît donc en
  double. (Bloc a1 = digest aux abonnés sans plan avec line-up ; bloc a2 =
  abonnés plan : push individuels pour les favoris + digest personnalisé des
  autres artistes, ce dernier seulement si « Line-up » est abonné. Dédup par
  appareil+créneau via `push_log`.)

Pour que « Mon planning » fonctionne, `autoPushUpcoming` doit être actif et le
cron doit tourner (voir plus haut).

## Statistiques d'abonnements (anonymes)

À chaque passage (au plus ~toutes les heures), le cron écrit un instantané des
**chiffres d'abonnements** dans la table `push_stats` – **uniquement des
compteurs**, aucune donnée personnelle : abonnements au total et par catégorie
(infos/line-up/général ; sécurité = tous les abonnements). Affichage (actuel +
historique) dans l'**onglet CMS « Push »**. Sans cron, pas d'historique ; les
chiffres actuels restent visibles en direct.

## Tester

1. Ouvrir l'app en **HTTPS** (le push exige HTTPS ; iOS seulement en PWA installée, iOS 16.4+).
2. Sous **Plus → Notifications → Activer**, créer l'abonnement (le navigateur demande la permission).
3. Ouvrir `push/admin.php`, se connecter, envoyer un message test → la notification apparaît.
4. Tester le cron : `php push/cron-send.php` (CLI) ou appeler l'URL avec `?key=` – le rapport JSON montre `candidates`/`sent`.

## Catégories de push (qui reçoit quoi)

Les push automatiques d'actus (cron) sont filtrés **deux fois** :

1. L'**admin** définit quelles catégories poussent automatiquement – dans le
   CMS sous **Réglages → « Push auto : catégories »** (infos / line-up /
   général). Stocké dans `data/app-config.json` (`pushNewsCategories`), lu en
   direct par le cron ; repli sur `pushNewsCategories` de `config.php`.
   **Sécurité est toujours incluse.**
2. **Chaque visiteur** choisit dans l'app sous **Notifications** lesquelles de
   ces catégories il veut recevoir. Le choix est stocké dans l'abonnement
   (colonne `categories` de `push_subscriptions` ; vide = sécurité seulement,
   NULL = tout pour l'existant). **La sécurité arrive toujours** et ne peut pas
   être désélectionnée.

Une actu n'est donc effectivement poussée que si la catégorie est **active côté
admin** (ou l'entrée `pinned`) **et** que le visiteur a choisi cette catégorie
(sauf sécurité – toujours). Les push manuels depuis `push/admin.php` partent
toujours vers **tous** les abonnements.

> La source des actus pour le push auto est `data/admin-news.json` (l'onglet
> « Actus » du CMS), sinon l'état du build `news.json`.

## Sécurité

- `push/config.php` et `push/vendor/` sont dans `.gitignore` et protégés en
  plus de l'accès direct via `.htaccess`.
- La clé VAPID privée et le mot de passe admin restent exclusivement sur le serveur.
- `cron-send.php` est protégé des appels étrangers via `cronSecret`.

## Extensions (optionnel)

- **Pousser les actus programmées :** dans `cron-send.php`, lire en plus
  `news.json` et envoyer en push les entrées avec `category="safety"`/`pinned`
  dont le `publishAt` a été atteint depuis le dernier passage (idempotence
  comme pour les créneaux via `push_log`, ref = `news:<id>`).
- **Rappels à la minute près :** utiliser un cron externe à 1 minute et
  resserrer la fenêtre dans `cron-send.php` de 60 min à p. ex. 15 min.
