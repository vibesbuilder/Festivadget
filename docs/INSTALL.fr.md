# Installer Festivadget (sans machine de build)

*Langues : [Deutsch](INSTALL.md) · [English](INSTALL.en.md) · [Español](INSTALL.es.md)*

Festivadget s'installe comme Joomla/WordPress : **téléverser le paquet de
release, ouvrir l'installeur dans le navigateur, terminé.** Aucune machine de
build locale (Node/pnpm) n'est nécessaire – un simple hébergement web suffit.

## Prérequis

- Hébergement avec **PHP 8.1+** et accès FTP (mutualisé suffisant).
- Optionnel pour le **web push** : une base MySQL et une tâche cron.
- Optionnel pour le **branding CMS** (icônes PWA) : extension PHP `gd`.

L'installeur vérifie lui-même tous les prérequis et affiche ce qui manque.

## Installation

1. Décompresser le paquet (`festivadget-vX.Y.Z.zip`) et téléverser son
   contenu par FTP dans la **racine web** du (sous-)domaine. Important :
   **installer immédiatement** – tant qu'aucune `push/config.php` n'existe,
   l'installeur est accessible à tous.
2. Ouvrir `https://votre-domaine/install/` dans le navigateur (DE/EN).
3. Remplir l'assistant :
   - **Mot de passe admin du CMS** (obligatoire) – pour se connecter sur
     `/push/cms/`.
   - **Accès MySQL** (optionnel) – active le web push ; les clés VAPID sont
     générées automatiquement. Laisser vide pour installer sans push (à
     ajouter plus tard dans `push/config.php`, voir [PUSH.fr.md](PUSH.fr.md)).
4. Après le message de succès, **supprimer le dossier `install/`** (bouton de
   la page finale ou via FTP).
5. Terminé : app sur `/`, CMS sur `/push/cms/`. Contenus, branding et image
   de fond se gèrent entièrement dans le CMS (voir [ADMIN.fr.md](ADMIN.fr.md)).
   Avec le web push, ajouter aussi la tâche cron ([PUSH.fr.md](PUSH.fr.md), étape 6).

## Mises à jour

Les mises à jour utilisent le **paquet de mise à jour** dédié
`festivadget-update-vX.Y.Z.zip` (comme la release, mais **sans `data/` ni
`install/`**). Les contenus du client restent intacts dans les deux
variantes – `data/` (contenus, uploads, branding), `push/config.php` et les
réglages CMS/météo ne sont jamais écrasés.

- **Confort (un clic) :** dans le CMS, ouvrir **Mise à jour** et téléverser
  le paquet – terminé. Le CMS valide le paquet (les paquets de release
  complets sont refusés), n'applique que les fichiers non protégés et
  affiche la version installée (fichier `VERSION`). Nécessite l'extension
  PHP `zip` (ou `phar`).
- **Minimal (FTP) :** décompresser le paquet et le copier par FTP par-dessus
  l'installation (écraser). Comme `data/` et `install/` ne sont pas dans le
  paquet, rien d'autre à surveiller.

## Construire soi-même le paquet (mainteneur)

Exécuter une fois `composer install` dans `push/` (pour `push/vendor/`), puis :

```bash
powershell -File tools/build-release.ps1
```

Construit l'app de façon **neutre** (sans valeurs d'instance intégrées) et
crée `release/festivadget-v<version>.zip` avec le build de l'app, `push/`
(sans secrets) et `install/`. Remarque : `data/` dans le paquet correspond à
l'état de build de `public/data/` – utiliser des données d'exemple pour les
releases publiques.
