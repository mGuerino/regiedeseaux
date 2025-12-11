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
