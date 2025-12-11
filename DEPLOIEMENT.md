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
ssh user@votre-serveur.com

# Aller dans le dossier du projet
cd /var/www/regiedeseaux  # (adapter le chemin)

# Récupérer les modifications (code + assets)
git pull

# Si nécessaire: mettre à jour les dépendances
composer install --no-dev --optimize-autoloader

# Si nécessaire: migrer la DB
php artisan migrate --force

# Vider les caches Laravel
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Première Installation sur un Nouveau Serveur

⚠️ **IMPORTANT:** Ces étapes sont nécessaires lors de la première installation ou après une mise à jour de Livewire/Filament.

```bash
# 1. Cloner le projet
git clone https://github.com/mGuerino/regiedeseaux.git
cd regiedeseaux

# 2. Installer les dépendances PHP
composer install --no-dev --optimize-autoloader

# 3. Configurer l'environnement
cp .env.example .env
nano .env  # Configurer DB, APP_URL, etc.
php artisan key:generate

# 4. CRITIQUE: Publier les assets Livewire et Filament
php artisan livewire:publish --assets
php artisan filament:assets

# 5. CRITIQUE: Créer le lien symbolique pour storage
php artisan storage:link

# 6. Configurer les permissions
chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache public/storage

# 7. Migrer la base de données
php artisan migrate --force

# 8. Optimiser pour la production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Vérifications finales
ls -la public/storage           # Doit être un lien symbolique
ls -lh public/vendor/livewire/  # Doit contenir livewire.js
curl -I http://votre-url/storage/  # Doit retourner 200 ou 403 (pas 404)
```

## Checklist de Déploiement

- [ ] `npm run build` exécuté en local si CSS/JS modifiés
- [ ] Code commité avec les assets buildés
- [ ] Push vers GitHub effectué
- [ ] `git pull` sur le serveur
- [ ] Caches Laravel vidés
- [ ] Application testée en production

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

### Erreur 403 sur les fichiers storage (ex: /storage/documents/fichier.docx)

**Symptôme:** `http://votre-serveur/storage/...` retourne 403 Forbidden

**Cause:** Lien symbolique `public/storage` manquant ou permissions incorrectes

**Solution:**
```bash
# 1. Créer le lien symbolique
php artisan storage:link

# 2. Vérifier que le lien existe
ls -la public/storage
# Devrait afficher: public/storage -> ../storage/app/public

# 3. Si le lien existe mais erreur 403, vérifier les permissions
chmod -R 775 storage
sudo chown -R www-data:www-data storage public/storage

# 4. Vérifier que le fichier existe
ls -lh storage/app/public/2025.12/  # Exemple de chemin

# 5. Tester l'accès
curl -I http://votre-url/storage/test.txt
```

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

- **Node.js local:** v25.x (ou v18.20+)
- **Node.js serveur:** ❌ Pas requis
- **Taille assets:** ~80KB (négligeable vs 3.8MB Filament déjà commités)
- **Déploiements:** Manuels via git pull
