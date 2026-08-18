# Configurer les actus live via Telegram

**🇩🇪 [Deutsch](TELEGRAM.de.md) · 🇬🇧 [English](TELEGRAM.md) · 🇪🇸 [Español](TELEGRAM.es.md)**

Envoie un message à ton bot Telegram → il apparaît **en ~2 minutes** dans
l'app, **sans déploiement**. Non modéré, uniquement pour les **expéditeurs
autorisés** (toi).

## Architecture (en bref)
- Les actus live atterrissent dans **`data/live-news.json`** sur le serveur –
  séparées du `news.json` éditorial.
- L'app lit **les deux** fichiers et les mélange dans le fil d'actus.
- Le build/déploiement local (`deploy-data.bat`) ne touche **jamais**
  `live-news.json` (il est dans `.gitignore` et n'est pas construit
  localement). → pas d'écrasement.

## Mise en place

### 1. Créer le bot
Dans Telegram **@BotFather** → `/newbot` → noter le **token du bot**.

### 2. Trouver son propre ID utilisateur
Écris à **@userinfobot** → il te donne ton `id` numérique (p. ex. `123456789`).

### 3. Remplir `push/config.php`
```php
'telegram' => [
    'botToken'       => '123456:ABC...',          // de BotFather
    'webhookSecret'  => 'un-long-mot-aleatoire',  // au choix
    'allowedUserIds' => [123456789, 987654321],   // plusieurs, séparés par des virgules
    'allowedChatIds' => [-1001234567890],         // optionnel : groupe(s) autorisé(s)
    'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
    'tz'             => 'Europe/Vienna',
    'maxItems'       => 200,
],
```
- **Autoriser plusieurs personnes :** ajouter simplement d'autres IDs, séparés par des virgules, dans `allowedUserIds`.
- **Autoriser un groupe** (chaque message du groupe devient une actu) : mettre l'**ID du groupe** dans `allowedChatIds`.

### 4. Téléverser `push/telegram-hook.php`
Déjà dans le dépôt sous `push/`. Par FTP vers le serveur (avec `push/`).

### 5. Enregistrer le webhook auprès de Telegram (une fois)
Ouvrir dans le navigateur (insérer token + secret) :
```
https://api.telegram.org/bot<BOTTOKEN>/setWebhook?url=https://app.rockimdorf.at/push/telegram-hook.php&secret_token=<WEBHOOKSECRET>
```
Réponse `{"ok":true,...}` = c'est bon. Telegram transmet désormais chaque message immédiatement au hook.

## Utilisation
Envoyer un message au bot :
- **Première ligne = titre**, lignes suivantes = texte.
- **Tags** (retirés du texte) pour piloter les options :
  - `#safety` / `#info` / `#lineup` / `#general` → catégorie (défaut : general)
  - `#pin` → épinglé (en haut du fil)
  - `#2h` / `#30m` → expire automatiquement 2 heures / 30 minutes **après publication**
  - `@HH:mm` → **publication programmée** aujourd'hui à HH:mm (si l'heure est passée → demain). Sans `@`, publication immédiate.
  - `#push` → envoyer en plus comme **web push** sur l'écran de verrouillage. **`#safety`** pousse **automatiquement** (même sans `#push`). Push uniquement en publication **immédiate** (pas pour les programmées `@HH:mm`) et seulement si le web push est configuré (`docs/PUSH.fr.md`). Pilotable via `pushAutoCategories` dans `config.php`.
- **Commandes :**
  - `/list` → liste les actus live actives, numérotées (avec heure d'expiration si définie).
  - `/del <n°>` → **révoque** une actu live (numéro de `/list`).
  - `/clear` → supprime **toutes** les actus live.

**Exemple (immédiat)**
```
Attention orage
Merci de sécuriser les tentes et de vous mettre à l'abri. #safety #pin #3h
```
→ actu sécurité épinglée, disparaît après 3 heures. Le bot confirme « ✅ Publié : … ».

**Exemple (programmé)**
```
Pause soundcheck
Courte interruption sur la Main Stage. @18:00 #info #1h
```
→ apparaît **à 18:00** et disparaît **à 19:00** (l'expiration compte à partir
de 18:00). Le bot confirme « ⏰ Programmé pour le 01.08. 18:00 : … ». Le
message atterrit immédiatement dans `live-news.json` ; l'app ne l'affiche qu'à
partir de 18:00 grâce à `publishAt`.

## Trouver les IDs
- **Son propre ID :** écrire à **@userinfobot** (ou envoyer `/chatid` à son propre bot – il répond avec l'ID utilisateur et l'ID de chat).
- **ID de groupe :** ajouter le bot **au groupe**, puis envoyer **`/chatid`** dans le groupe – le bot répond avec le `chat ID` (nombre négatif, p. ex. `-1001234567890`). Le mettre dans `allowedChatIds`.
- **Important pour les groupes :** pour que le bot lise **tous** les messages du groupe (pas seulement les commandes), aller chez **@BotFather** → `/setprivacy` → choisir **Disable**. Sinon seules les `/commandes` arrivent.
- ⚠️ Un groupe autorisé signifie : **chaque membre** peut envoyer des actus à tous les visiteurs.

## Droits d'écriture (pas de FTP nécessaire)
Les actus live n'ont **pas besoin de FTP** – le hook PHP écrit directement dans
`data/live-news.json` sur le même serveur. Prérequis : le **dossier `data/` est
inscriptible pour PHP** (généralement le cas en hébergement mutualisé). Sinon :
donner les droits d'écriture sur `data/`.

## Remarques
- `live-news.json` est créé par le serveur à la première entrée ; avant cela,
  l'app ne sert simplement aucune actu live (pas d'erreur).
- Livraison avec un cache court (`max-age=120`), l'app interroge toutes les
  2 minutes → mises à jour visibles en ≤ 2 min.
- **Sécurité :** sans `webhookSecret` valide (en-tête de Telegram) et sans
  `allowedUserIds` autorisés, **rien** n'est traité. Garde
  `botToken`/`webhookSecret` secrets.
- Extension possible : envoyer en plus les actus live validées comme **push**
  (l'infrastructure existe, voir `push/sender.php`).
