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

## Checklist de Déploiement

- [ ] `npm run build` exécuté en local si CSS/JS modifiés
- [ ] Code commité avec les assets buildés
- [ ] Push vers GitHub effectué
- [ ] `git pull` sur le serveur
- [ ] Caches Laravel vidés **AVEC** `sudo -u www-data`
- [ ] Permissions corrigées si nécessaire (`sudo chown -R www-data:www-data storage/ public/build/`)
- [ ] Application testée en production

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
