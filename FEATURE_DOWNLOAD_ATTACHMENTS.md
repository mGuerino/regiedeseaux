# Feature: Téléchargement des Pièces Jointes en Mode Édition

## 📋 Contexte

**Date :** 7 janvier 2026  
**Demandeur :** Utilisateur final  
**Environnement :** Local et Production

### Besoin Exprimé

Lors de l'édition d'une demande dans Filament (`/requests/{id}/edit`), l'utilisateur souhaite pouvoir **télécharger les pièces jointes** directement depuis FilePond, en plus de pouvoir les supprimer.

**Avant la modification :**
- Seul le bouton `×` (supprimer) était disponible
- Impossible de télécharger un fichier déjà uploadé sans quitter le formulaire

**Après la modification :**
- Bouton `↓` (télécharger) ajouté à côté du bouton `×`
- Téléchargement direct possible pour tous les types de documents

---

## ✅ Solution Implémentée

### Modification Apportée

**Fichier :** `app/Filament/Resources/Requests/Schemas/RequestForm.php:456`

```php
FileUpload::make('attachments')
    ->label('Pièces jointes')
    ->multiple()
    ->downloadable()  // ← LIGNE AJOUTÉE
    ->acceptedFileTypes([...])
    ->disk('public')
    ->directory(fn () => now()->format('Y.m'))
    ->visibility('public')
    ->maxSize(10240)
    ->helperText('Formats acceptés: PDF, JPG, PNG, XLSX, XLS, DOC, DOCX (max 10 MB)'),
```

### Fonctionnement Technique

**Méthode Filament :** `downloadable()`

D'après la [documentation Filament v4](https://filamentphp.com/docs/4.x/forms/fields/file-upload#downloading-files), cette méthode :
- Ajoute automatiquement un bouton de téléchargement sur chaque fichier
- Gère la route de téléchargement via Livewire (aucune route manuelle nécessaire)
- Fonctionne avec n'importe quel disque de stockage (`public`, `local`, `s3`, etc.)
- Respecte les permissions et la visibilité des fichiers

**Architecture :**
1. FilePond affiche les fichiers existants (chargés via `mutateFormDataBeforeFill()`)
2. L'utilisateur clique sur le bouton de téléchargement
3. Livewire récupère le fichier depuis `Storage::disk('public')`
4. Le fichier est envoyé au navigateur avec le bon nom et type MIME

---

## 🎯 Portée de la Fonctionnalité

### Types de Documents Concernés

✅ **Pièces jointes uploadées** (PDF, images, Excel, Word)
- Fichiers ajoutés manuellement par l'utilisateur
- Stockés dans `storage/app/public/YYYY.MM/`
- `document_type = null` dans la table `documents`

✅ **Attestations générées** (documents Word)
- Générées via l'action "Générer Word"
- Stockées dans `storage/app/public/YYYY.MM/`
- `document_type = 'generated'` dans la table `documents`

### Modes d'Affichage

| Mode | Téléchargement | Suppression | Ajout |
|------|----------------|-------------|-------|
| **Édition** (`/requests/{id}/edit`) | ✅ OUI | ✅ OUI | ✅ OUI |
| **Visualisation** (table/list) | ✅ Déjà disponible via colonne | ❌ NON | ❌ NON |
| **Création** (`/requests/create`) | ❌ Non applicable (pas de fichiers existants) | ❌ NON | ✅ OUI |

**Note :** Le mode visualisation disposait déjà d'un système de téléchargement via la colonne de la table, donc cette modification concerne **uniquement le mode édition**.

---

## 🧪 Tests Effectués

### Test 1 : Syntaxe PHP
```bash
php -l app/Filament/Resources/Requests/Schemas/RequestForm.php
# ✅ Résultat : No syntax errors detected
```

### Test 2 : Vérification des Caches
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
# ✅ Résultat : Caches vidés avec succès
```

### Test 3 : Chargement des Documents
```php
$request = Request::with('documents')->first();
echo $request->documents->count(); // ✅ 1 document trouvé
echo $request->documents->first()->file_name; // ✅ 2016.06/sdirplu10316060108500_0002_1DY6O8.pdf
```

### Test 4 : Interface Utilisateur (À Confirmer par l'Utilisateur)

**Instructions de test en local :**
1. Se connecter à Filament : `http://regiedeseaux.test/admin`
2. Naviguer vers une demande avec des pièces jointes
3. Cliquer sur "Éditer"
4. Vérifier que chaque fichier dans "Pièces jointes" affiche deux boutons :
   - `↓` (télécharger) ← **NOUVEAU**
   - `×` (supprimer) ← Existant
5. Cliquer sur `↓` et vérifier que le fichier se télécharge correctement

**Tests en production (après déploiement) :**
- URL : `https://arrp-test.bureau.eauxdupaysdaix.fr/admin/requests/{id}/edit`
- Même procédure que ci-dessus

---

## 📦 Déploiement en Production

### Étapes de Déploiement

```bash
# 1. Se connecter au serveur
ssh administrateur@votre-serveur.com
cd /var/www/regiedeseaux

# 2. Récupérer les modifications
git pull

# 3. Vider les caches Laravel (avec bonnes permissions)
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan filament:cache-components

# 4. Tester l'application
# Naviguer vers https://arrp-test.bureau.eauxdupaysdaix.fr/admin/requests/{id}/edit
```

### Vérifications Post-Déploiement

- [ ] Le bouton de téléchargement apparaît sur chaque fichier
- [ ] Le téléchargement fonctionne pour les pièces jointes uploadées
- [ ] Le téléchargement fonctionne pour les attestations générées
- [ ] Aucune erreur dans `storage/logs/laravel.log`
- [ ] Le bouton de suppression fonctionne toujours correctement

---

## 🔍 Dépannage

### Problème : Le Bouton de Téléchargement N'Apparaît Pas

**Causes possibles :**
1. Cache Livewire/Filament non vidé
2. Cache navigateur (Ctrl+F5 pour forcer le rechargement)
3. Version de Filament incompatible (vérifier que v4.x est installée)

**Solutions :**
```bash
# Vider tous les caches
php artisan filament:cache-components
php artisan view:clear
php artisan livewire:discover

# Vider le cache du navigateur
# Ctrl+Shift+R (Windows/Linux) ou Cmd+Shift+R (Mac)
```

### Problème : Erreur 404 lors du Téléchargement

**Cause :** Fichier introuvable sur le disque

**Solution :**
```bash
# Vérifier que le fichier existe
ls -la storage/app/public/2026.01/

# Vérifier les permissions
ls -la storage/app/public/
# Devrait afficher : drwxr-xr-x www-data www-data

# Corriger si nécessaire
sudo chown -R www-data:www-data storage/app/public/
chmod -R 755 storage/app/public/
```

### Problème : Erreur 500 lors du Téléchargement

**Cause :** Problème de permissions ou configuration du disque

**Solution :**
```bash
# Vérifier la configuration du disque 'public'
php artisan tinker --execute="
echo config('filesystems.disks.public.root') . PHP_EOL;
echo config('filesystems.disks.public.visibility') . PHP_EOL;
"

# Consulter les logs
tail -n 50 storage/logs/laravel.log
```

---

## 📊 Impact et Bénéfices

### Avant la Modification
- ❌ Impossible de télécharger un fichier lors de l'édition
- ❌ Nécessité de quitter le formulaire pour télécharger via un autre moyen
- ❌ Perte de temps et mauvaise UX

### Après la Modification
- ✅ Téléchargement direct depuis le formulaire d'édition
- ✅ UX améliorée (cohérence avec FilePond standard)
- ✅ Gain de temps pour les utilisateurs
- ✅ Fonctionnalité native Filament (maintenance facile)

---

## 🔗 Références

- **Documentation Filament v4 - FileUpload** : https://filamentphp.com/docs/4.x/forms/fields/file-upload#downloading-files
- **Documentation FilePond** : https://pqina.nl/filepond/
- **Fichier modifié** : `app/Filament/Resources/Requests/Schemas/RequestForm.php`
- **Model concerné** : `App\Models\Document`
- **Relation Eloquent** : `App\Models\Request->documents()`

---

## 📝 Notes Techniques

### Pourquoi `->downloadable()` et Pas une Route Manuelle ?

**Filament v4 gère automatiquement le téléchargement via Livewire :**
1. Aucune route web manuelle nécessaire (comme `documents.download`)
2. Les permissions Livewire sont respectées (utilisateur authentifié)
3. Gestion automatique des types MIME et noms de fichiers
4. Fonctionne avec tous les disques configurés (local, s3, etc.)

**Si on devait créer une route manuelle, ce serait :**
```php
// PAS NÉCESSAIRE avec ->downloadable()
Route::get('/livewire/file-upload/download/{file}', ...)
```

Mais Filament gère déjà cela en interne ! 🎉

---

## ✅ Checklist de Validation

- [x] Code modifié (`->downloadable()` ajouté)
- [x] Syntaxe PHP validée (aucune erreur)
- [x] Tests de chargement des documents réussis
- [x] Documentation créée
- [ ] Test manuel en local (à faire par l'utilisateur)
- [ ] Commit et push vers GitHub
- [ ] Déploiement en production
- [ ] Test manuel en production (à faire par l'utilisateur)

---

**✅ Fonctionnalité prête à être testée et déployée !**
