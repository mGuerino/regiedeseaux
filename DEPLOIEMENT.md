# Guide de Déploiement Rapide

## Workflow Quotidien

### 1️⃣ Développement en local

```bash
# Démarrer le serveur de dev
php artisan serve

# Dans un autre terminal - Mode développement avec hot reload
npm run dev
```

### 2️⃣ Avant de commit

**⚠️ IMPORTANT:** Si vous avez modifié des fichiers CSS ou JS :

```bash
# Compiler les assets pour la production
npm run build

# Vérifier que les assets sont buildés
ls -lh public/build/assets/
```

### 3️⃣ Commit et Push

```bash
# Ajouter tous les fichiers (code + assets compilés)
git add .

# Commit
git commit -m "Votre message descriptif"

# Push vers GitHub
git push
```

### 4️⃣ Déploiement sur le serveur

```bash
# Se connecter au serveur
ssh administrateur@votre-serveur.com

# Aller dans le dossier du projet
cd /var/www/regiedeseaux

# Récupérer les modifications (code + assets)
git pull

# Si nécessaire: mettre à jour les dépendances
composer install --no-dev --optimize-autoloader

# Si nécessaire: migrer la DB
sudo -u www-data php artisan migrate --force

# ⚠️ IMPORTANT: Vider les caches AVEC les bonnes permissions
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear

# Optionnel: Optimiser pour la production
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Si tu as modifié des assets (CSS/JS) via npm run build
sudo chown -R www-data:www-data public/build/
```

**💡 Pourquoi `sudo -u www-data` ?**
- Tu te connectes en tant qu'`administrateur`
- Nginx tourne sous l'utilisateur `www-data`
- Les caches/fichiers doivent appartenir à `www-data` pour éviter les erreurs 404/403
- Sans `sudo -u www-data`, les fichiers créés appartiendront à `administrateur` → problèmes de permissions

## Première Installation sur un Nouveau Serveur

⚠️ **IMPORTANT:** Ces étapes sont nécessaires lors de la première installation ou après une mise à jour de Livewire/Filament.

```bash
# 1. Cloner le projet
git clone https://github.com/mGuerino/regiedeseaux.git /var/www/regiedeseaux
cd /var/www/regiedeseaux

# 2. Installer les dépendances PHP
composer install --no-dev --optimize-autoloader

# 3. Configurer l'environnement
cp .env.example .env
nano .env  # Configurer DB, APP_URL, etc.
php artisan key:generate

# 4. CRITIQUE: Créer le lien symbolique pour storage AVEC www-data
sudo -u www-data php artisan storage:link

# 5. CRITIQUE: Publier les assets Livewire et Filament
sudo -u www-data php artisan livewire:publish --assets
sudo -u www-data php artisan filament:assets

# 6. CRITIQUE: Configurer les permissions (www-data = utilisateur Nginx)
sudo chown -R www-data:www-data storage/ bootstrap/cache/ public/storage public/build/
chmod -R 775 storage/ bootstrap/cache/

# 7. Migrer la base de données
sudo -u www-data php artisan migrate --force

# 8. Optimiser pour la production
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 9. Vérifications finales
ls -la public/storage           # Doit être: lrwxrwxrwx 1 www-data www-data ... -> /var/www/regiedeseaux/storage/app/public
ls -lh public/vendor/livewire/  # Doit contenir livewire.js
curl -I https://votre-domaine.test/storage/  # Doit retourner 200 ou 403 (pas 404)
```

**💡 Notes Importantes:**
- Remplace `votre-domaine.test` par ton URL réelle (ex: `regiedeseaux.com`)
- L'utilisateur `www-data` est l'utilisateur par défaut de Nginx/Apache sur Debian/Ubuntu
- Si tu es sur CentOS/RHEL, l'utilisateur peut être `nginx` ou `apache`

---

## LibreOffice — Requis pour la validation des attestations

La validation d'une attestation par un superviseur affiche le document en PDF dans le
navigateur. La conversion Word → PDF est faite par LibreOffice en mode headless : **sans
lui, l'envoi en validation échoue avec un message d'erreur explicite.**

Aucun démon n'est lancé, aucun port réseau n'est ouvert : le binaire est appelé à la
demande par PHP via `App\Services\DocxToPdfConverter`.

> Procédure validée le 14/09/2026 sur le serveur de test (`10.100.20.13`) :
> Ubuntu 24.04.3 LTS, PHP 8.3.6, LibreOffice 24.2.7.2.

### 1. Installation du paquet

```bash
sudo apt update
sudo apt install --no-install-recommends libreoffice-writer fonts-liberation
```

Environ 400 Mo. Le `--no-install-recommends` évite d'embarquer Calc, Impress et Base,
inutiles ici.

```bash
# Vérifier le chemin réel du binaire (peut varier selon le paquet)
which soffice
```

### 2. Répertoires inscriptibles par www-data

C'est l'étape qui fait échouer la plupart des installations. PHP-FPM tourne sous
`www-data`, dont le home (`/var/www`) n'est pas inscriptible.

```bash
# Profil LibreOffice — DOIT exister et appartenir à www-data.
# www-data ne peut pas le créer lui-même : /var/lib appartient à root.
sudo mkdir -p /var/lib/attestations/libreoffice
sudo chown www-data:www-data /var/lib/attestations/libreoffice

# Répertoires du home de www-data (supprime les avertissements dconf et
# permet de lancer `artisan tinker` en tant que www-data pour diagnostiquer)
sudo mkdir -p /var/www/.config /var/www/.cache /var/www/.local
sudo chown -R www-data:www-data /var/www/.config /var/www/.cache /var/www/.local

# Contrôle
ls -ld /var/lib/attestations /var/lib/attestations/libreoffice
sudo -u www-data soffice --headless \
  -env:UserInstallation=file:///var/lib/attestations/libreoffice --version
```

La dernière commande doit afficher `LibreOffice 24.2.x.x`. Attention : `--version`
n'écrit pas dans le profil, **ce test ne prouve donc pas que la conversion fonctionnera**.
Seul le test de l'étape 4 fait foi.

### 3. Configuration `.env`

```dotenv
LIBREOFFICE_PATH=/usr/bin/soffice
LIBREOFFICE_PROFILE_PATH=/var/lib/attestations/libreoffice
LIBREOFFICE_TIMEOUT=120
```

Laisser `LIBREOFFICE_PATH` vide active la détection automatique
(`/usr/bin/soffice`, `/usr/local/bin/soffice`, `/opt/homebrew/bin/soffice`).
`LIBREOFFICE_PROFILE_PATH` vide retombe sur `storage/app/libreoffice-profile`.

```bash
# OBLIGATOIRE : sans ça Laravel continue de lire l'ancien cache de config
sudo -u www-data php artisan config:cache

# Contrôle : l'application voit-elle la valeur ?
sudo -u www-data env HOME=/tmp/wwwdata php artisan tinker \
  --execute="echo config('services.libreoffice.path');"
```

### 4. Vérification de bout en bout

Ce test appelle **exactement le code que le site exécutera** lors d'une validation —
binaire, profil, permissions et configuration sont éprouvés d'un seul coup.

```bash
cd /var/www/regiedeseaux

sudo -u www-data env HOME=/tmp/wwwdata php artisan tinker --execute="\$src = collect(glob(storage_path('app/templates/*.docx')))->first(); \$pdf = app(\App\Services\DocxToPdfConverter::class)->convert(\$src, storage_path('app/tmp-pdf')); echo 'PDF genere : '.\$pdf.' ('.filesize(\$pdf).' octets)', PHP_EOL;"
```

Sortie attendue :

```
PDF genere : /var/www/regiedeseaux/storage/app/tmp-pdf/template_1_attestation-standard.pdf (68441 octets)
```

Le `env HOME=/tmp/wwwdata` ne vaut que pour cette commande : il donne à psysh (le moteur
de `tinker`) un répertoire inscriptible. Il est inutile si l'étape 2 a été faite.

Récupérer le PDF pour un contrôle visuel, **depuis le poste de travail** :

```bash
scp administrateur@10.100.20.13:/var/www/regiedeseaux/storage/app/tmp-pdf/*.pdf ~/Desktop/
```

Puis nettoyer :

```bash
sudo rm -rf /var/www/regiedeseaux/storage/app/tmp-pdf
```

### Pièges rencontrés en conditions réelles

| Symptôme | Cause | Correctif |
|---|---|---|
| `LibreOffice user installation could not be processed due to missing access rights` | Le répertoire de profil n'existe pas, ou n'appartient pas à `www-data` | Étape 2 |
| `La conversion en PDF n'a produit aucun fichier` | Le **répertoire de sortie** n'est pas inscriptible par `www-data` — typiquement créé par un test lancé sans `sudo -u www-data` | `sudo rm -rf storage/app/tmp-pdf` puis relancer en `www-data`, qui le recrée |
| `Writing to directory /var/www/.config/psysh is not allowed` | `tinker` lancé en `www-data` sans home inscriptible | `env HOME=/tmp/wwwdata`, ou étape 2 |
| `dconf-CRITICAL ... unable to create directory '/var/www/.cache/dconf'` | Home de `www-data` non inscriptible | Cosmétique, sans effet sur la conversion. Étape 2 le supprime |
| `Warning: failed to launch javaldx - java may not function correctly` | Java absent | Cosmétique : la conversion Writer → PDF n'en a pas besoin |

**À retenir : LibreOffice sort en code 0 même lorsqu'il ne parvient pas à écrire le PDF.**
C'est pourquoi `DocxToPdfConverter` vérifie l'existence du fichier produit, et pourquoi le
message « aucun fichier produit » désigne le plus souvent un problème de droits sur le
répertoire de sortie, et non un défaut du document source.

### Polices

Le modèle d'attestation déclare **Gandhi Sans** (corps du texte, 27 occurrences) et
**Arial** (5 occurrences).

Constat du 14/09/2026 : **Gandhi Sans n'est installée ni sur le serveur, ni sur les postes
de travail.** Les attestations Word produites jusqu'ici sont donc déjà rendues avec une
police de substitution choisie par Word. Installer Gandhi Sans sur le seul serveur rendrait
le PDF *différent* de ce qui est affiché dans Word — l'inverse du but recherché.

Deux options cohérentes :

1. **Recommandé — normaliser le modèle sur Arial.** `fonts-liberation` fournit Liberation
   Sans, dont les métriques sont identiques à Arial : le PDF devient rigoureusement
   conforme au rendu Word, sans rien installer. La modification se fait dans Word, sur le
   `.docx`, pas dans le code.
2. **Conserver Gandhi Sans** — il faut alors l'installer *partout* : serveur et postes
   éditant le modèle. Police téléchargeable gratuitement (Font Squirrel, CTAN), utilisable
   en usage commercial ; sa licence interdit la modification et la revente.

```bash
# Option 2 uniquement — installation côté serveur
sudo mkdir -p /usr/local/share/fonts/attestations
sudo cp GandhiSans*.ttf /usr/local/share/fonts/attestations/
sudo chmod 644 /usr/local/share/fonts/attestations/*
sudo fc-cache -f

# Vérifier ce que le serveur a réellement
fc-list | grep -ci gandhi      # 0 = absente
fc-list | grep -ci liberation  # > 0 grâce à fonts-liberation
```

Tahoma et Corbel (usage marginal : un style et le thème) restent substituées dans tous les
cas.

### Configuration applicative

Ces trois points ne se font pas en ligne de commande mais dans l'interface d'administration.
**Le workflow ne fonctionne pas sans eux.**

1. **Désigner les superviseurs** : Administration → Utilisateurs → cocher « Superviseur ».
   Sans superviseur désigné, l'envoi en validation bascule bien la demande en « en attente »,
   mais **personne n'est notifié** : un simple avertissement part dans les logs et la demande
   reste bloquée.
2. **Ajouter la signature des signataires** : Référentiels → Agents → champ « Image de
   signature » (PNG à fond transparent recommandé).
3. **Ajouter la variable `${signature}` dans le modèle Word**, à l'emplacement où la
   signature doit apparaître, puis resynchroniser les variables du modèle depuis la page
   Templates. Sans cette variable, la validation fonctionne et le PDF est généré, mais
   aucune signature n'est apposée.

Contrôle rapide du point 1 :

```bash
sudo -u www-data env HOME=/tmp/wwwdata php artisan tinker \
  --execute="echo App\Models\User::where('is_supervisor', true)->count().' superviseur(s)';"
```

---

## Pièges de déploiement récurrents

### Permissions de fichiers signalées par git

Après un `chmod -R 775 storage/ bootstrap/cache/`, `git status` affiche une dizaine de
`.gitignore` modifiés — ce sont uniquement des changements de bits de permission
(644 → 755), sans modification de contenu. Ils risquent de bloquer un futur `git pull`.

```bash
cd /var/www/regiedeseaux
git config core.fileMode false
```

Réglage local à ce dépôt, sur ce serveur. Ne modifie aucun fichier ni aucune permission
réelle.

### `composer install` republie les assets Filament

`composer install` déclenche `filament:upgrade`, qui réécrit `public/js/`, `public/css/` et
`public/fonts/`, **et vide les caches config/route/view**. Lancé en tant
qu'`administrateur`, ces fichiers ne sont plus accessibles à Nginx.

```bash
composer install --no-dev --optimize-autoloader

# Réparer derrière
sudo chown -R www-data:www-data storage/ bootstrap/cache/ public/js/ public/css/ public/fonts/
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

Les avertissements `Could not scan for classes inside ...` pendant la désinstallation des
paquets de dev sont normaux : le classmap se régénère pendant que les dossiers
disparaissent. Seule compte la ligne `Generating optimized autoload files`.

### Cache de composants Filament obsolète — erreur 500 sur toutes les actions

Constaté le 14/09/2026. Si `bootstrap/cache/filament/panels/admin.php` existe, il fige la liste
des composants Livewire du panel. **Toute page Filament livrée après la création de ce cache
s'affiche, mais lève `Livewire\Exceptions\ComponentNotFoundException` au premier clic** sur
l'un de ses boutons — le symptôme est trompeur : la page semble fonctionner.

```bash
cd /var/www/regiedeseaux
sudo -u www-data php artisan filament:clear-cached-components

# Si tu re-caches pour la performance, fais-le APRÈS chaque livraison, jamais avant
sudo -u www-data php artisan filament:cache-components
```

À exécuter à chaque déploiement ajoutant une page, une ressource ou un widget Filament.

### Assets non recompilés — mise en page cassée sans erreur

Constaté le 14/09/2026 sur la page de validation. Les classes Tailwind utilisées par une vue
Blade personnalisée n'existent dans le CSS que si `npm run build` a été relancé **après**
l'écriture de la vue. Sinon la page s'affiche sans erreur, mais sans mise en page : l'aperçu
PDF de la validation apparaissait en vignette de 300×150 px.

Les assets étant commités au dépôt, le défaut se propage tel quel en production. Le contrôle :

```bash
# En local, après toute modification d'une vue Blade sous resources/views/filament/
npm run build

# Vérifier qu'une classe caractéristique de la nouvelle vue est bien présente
grep -c "80vh" public/build/assets/theme-*.css   # doit être > 0
```

Attention aux greps : les classes à valeur arbitraire sont échappées dans le CSS
(`.h-\[80vh\]`). Chercher la valeur seule (`80vh`) évite les faux négatifs.

### `--optimize-autoloader` et les nouvelles classes

Le projet est déployé avec `--optimize-autoloader`, donc le classmap est figé. **Toute
livraison ajoutant des classes exige un `composer install --no-dev --optimize-autoloader`**,
même si aucune dépendance n'a changé. Sans lui, les nouvelles classes restent introuvables
et le site renvoie une erreur 500. Les migrations, elles, passent sans problème — ce qui
peut donner l'illusion que le déploiement est complet.

## Checklist de Déploiement

- [ ] `npm run build` exécuté en local si CSS/JS modifiés
- [ ] Code commité avec les assets buildés
- [ ] Push vers GitHub effectué
- [ ] `git pull` sur le serveur
- [ ] `composer install --no-dev --optimize-autoloader` si de nouvelles classes ont été livrées
- [ ] `sudo -u www-data php artisan filament:clear-cached-components` si une page/ressource Filament a été livrée
- [ ] `sudo -u www-data php artisan migrate --force` si nouvelles migrations
- [ ] Caches Laravel vidés **AVEC** `sudo -u www-data`
- [ ] Permissions corrigées si nécessaire (`sudo chown -R www-data:www-data storage/ bootstrap/cache/ public/build/ public/js/ public/css/ public/fonts/`)
- [ ] Application testée en production

### Spécifique au workflow de validation des attestations

- [ ] LibreOffice installé et lançable par `www-data`
- [ ] Profil `/var/lib/attestations/libreoffice` créé et possédé par `www-data`
- [ ] Variables `LIBREOFFICE_*` présentes dans le `.env`, suivies d'un `config:cache`
- [ ] Test de conversion de bout en bout concluant (étape 4 de la section LibreOffice)
- [ ] Au moins un utilisateur coché « Superviseur »
- [ ] Image de signature renseignée sur les agents signataires
- [ ] Variable `${signature}` présente dans le modèle Word et variables resynchronisées

## Aide-Mémoire pour la Production

### Alias Bash (Optionnel mais Recommandé)

Tu peux ajouter ces alias dans ton `~/.bashrc` pour simplifier les déploiements :

```bash
# Éditer le fichier
nano ~/.bashrc

# Ajouter ces lignes à la fin
alias artisan-prod='sudo -u www-data php artisan'
alias deploy-clear='sudo -u www-data php artisan config:clear && sudo -u www-data php artisan route:clear && sudo -u www-data php artisan cache:clear && sudo -u www-data php artisan view:clear'
alias deploy-optimize='sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache && sudo -u www-data php artisan view:cache'
alias deploy-fix-perms='sudo chown -R www-data:www-data storage/ bootstrap/cache/ public/storage public/build/'

# Sauvegarder et recharger
source ~/.bashrc
```

**Utilisation après `git pull` :**
```bash
cd /var/www/regiedeseaux
git pull
deploy-clear     # Vide tous les caches
deploy-optimize  # Optionnel: optimise pour la production
deploy-fix-perms # Si tu as ajouté des fichiers manuellement
```

## Commandes Utiles

### Vérifier que les assets sont à jour

```bash
# En local - voir la date du dernier build
ls -l public/build/assets/

# Sur le serveur - même commande
ls -l public/build/assets/
# Les dates doivent correspondre après un git pull
```

### Rollback en cas de problème

```bash
# Sur le serveur
git log --oneline -5           # Voir les derniers commits
git checkout <commit-hash>      # Revenir à un commit précédent
php artisan cache:clear         # Vider les caches
```

### Rebuild complet des assets

```bash
# En local
rm -rf node_modules package-lock.json
npm install
npm run build
git add public/build/
git commit -m "chore: Rebuild assets"
git push
```

## Dépannage

### Erreur 403 ou 404 sur les fichiers storage (ex: téléchargement de documents)

**Symptôme:** Les téléchargements de documents génèrent une erreur 404 ou 403

**Cause la plus fréquente:** Problème de permissions sur le lien symbolique `public/storage`

**Solution complète:**
```bash
cd /var/www/regiedeseaux

# 1. Vérifier que le lien symbolique existe
ls -la public/storage
# Doit afficher: public/storage -> /var/www/regiedeseaux/storage/app/public

# 2. Si le lien n'existe pas, le créer
sudo -u www-data php artisan storage:link

# 3. CRITIQUE: Vérifier le propriétaire du lien symbolique
ls -la public/storage
# Doit afficher: lrwxrwxrwx 1 www-data www-data ...

# 4. Si le propriétaire est 'administrateur', CORRIGER:
sudo chown -h www-data:www-data public/storage

# 5. Vérifier les permissions des fichiers de destination
ls -la storage/app/public/
ls -la storage/app/public/2026.01/  # Exemple avec documents récents

# 6. Si les permissions sont incorrectes, corriger
sudo chown -R www-data:www-data storage/app/public/

# 7. Vider les caches Laravel (IMPORTANT!)
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan cache:clear

# 8. Vérifier que les fichiers sont accessibles
curl -I https://votre-domaine.test/storage/test.txt
```

**⚠️ Leçon Apprise:** 
- Le lien symbolique `public/storage` DOIT appartenir à `www-data:www-data`
- Même si les fichiers de destination ont les bonnes permissions, un lien symbolique avec le mauvais propriétaire peut causer des 404
- Toujours utiliser `sudo -u www-data php artisan storage:link` lors de la création initiale

### Interface Filament cassée (champ password non masqué, pas d'interactions)

**Symptôme:** Le champ mot de passe n'est pas masqué, erreurs JavaScript dans la console

**Cause:** Assets Livewire non publiés

**Solution:**
```bash
# 1. Publier les assets Livewire
php artisan livewire:publish --assets

# 2. Publier les assets Filament
php artisan filament:assets

# 3. Vider les caches
php artisan cache:clear
php artisan view:clear

# 4. Vérifier que les assets sont là
ls -lh public/vendor/livewire/  # Doit contenir livewire.js

# 5. Vider le cache navigateur (Ctrl+Shift+R)
```

### Les assets ne se mettent pas à jour sur le serveur

```bash
# 1. Vérifier que git pull a bien récupéré les assets
git status
git log -1 --stat

# 2. Vider le cache du navigateur (Ctrl+Shift+R ou Cmd+Shift+R)

# 3. Vider tous les caches Laravel
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

### J'ai oublié de faire npm run build avant de commit

```bash
# Faire le build
npm run build

# Amender le commit précédent
git add public/build/
git commit --amend --no-edit

# Force push (attention: uniquement si vous êtes seul sur le projet)
git push --force
```

## Notes Importantes

1. **Node.js n'est requis QUE sur votre machine locale**, pas sur le serveur
2. **Les assets sont versionnés avec des hash** (ex: `app-CAiCLEjY.js`) donc pas de problème de cache navigateur
3. **Toujours faire `npm run build` en production mode** (pas `npm run dev`)
4. Les assets Vite sont automatiquement inclus dans les templates via `@vite()` directive

## Configuration Actuelle

- **Serveur:** Nginx sur Debian/Ubuntu
- **Utilisateur web:** `www-data` (Nginx)
- **Utilisateur SSH:** `administrateur`
- **Chemin application:** `/var/www/regiedeseaux`
- **Node.js local:** v25.x (ou v18.20+)
- **Node.js serveur:** ❌ Pas requis (assets compilés en local)
- **Taille assets:** ~80KB (négligeable vs 3.8MB Filament déjà commités)
- **Déploiements:** Manuels via git pull (rare)
- **Stratégie permissions:** Toujours utiliser `sudo -u www-data` pour les commandes Artisan en production

## FAQ Permissions

### Pourquoi dois-je utiliser `sudo -u www-data` ?

Quand tu te connectes en SSH en tant qu'`administrateur` et que tu exécutes `php artisan cache:clear`, les fichiers de cache créés appartiendront à `administrateur:administrateur`. Ensuite, quand Nginx (qui tourne sous `www-data`) essaie d'accéder à ces caches, il peut rencontrer des problèmes de permissions.

**Exemple du problème:**
```bash
# ❌ MAUVAIS: Cache créé par 'administrateur'
php artisan config:cache
ls -la bootstrap/cache/config.php
# -rw-r--r-- 1 administrateur administrateur ...

# Nginx (www-data) ne peut pas toujours lire ce fichier → erreur 500
```

**Solution:**
```bash
# ✅ BON: Cache créé par 'www-data'
sudo -u www-data php artisan config:cache
ls -la bootstrap/cache/config.php
# -rw-r--r-- 1 www-data www-data ...

# Nginx peut lire le fichier sans problème ✓
```

### Que faire si j'ai déjà créé des fichiers avec 'administrateur' ?

```bash
# Corriger les permissions de tous les répertoires critiques
cd /var/www/regiedeseaux
sudo chown -R www-data:www-data storage/ bootstrap/cache/ public/storage public/build/

# Puis vider les caches avec le bon utilisateur
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan cache:clear
```

### Les alias bash fonctionnent-ils avec `sudo -u www-data` ?

Oui ! Les alias définis dans ton `~/.bashrc` fonctionnent parfaitement :

```bash
# Au lieu de taper:
sudo -u www-data php artisan config:clear

# Tu peux utiliser:
artisan-prod config:clear

# Ou pour tout vider d'un coup:
deploy-clear
```
