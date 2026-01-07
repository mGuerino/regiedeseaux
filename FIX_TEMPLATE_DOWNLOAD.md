# Fix: Téléchargement des Templates - Erreur 404

## 🐛 Problème Identifié

**Date:** 7 janvier 2026  
**Environnement:** Local ET Production  
**URL affectée:** `/admin/templates/{id}/download`

### Symptôme
Les téléchargements de templates depuis la page **Gestion des Templates** (`/manage-templates`) retournaient une erreur **404 Not Found**, même si les fichiers existaient physiquement sur le serveur.

### Cause Racine

**Fichier:** `routes/web.php` (lignes 8-20)

```php
// ❌ CODE BUGGÉ (AVANT)
Route::get('/admin/templates/{id}/download', function ($id) {
    $template = DocumentTemplate::findOrFail($id);
    
    if (!Storage::exists($template->file_path)) {
        abort(404, 'Fichier template introuvable');
    }
    
    return Storage::download(
        $template->file_path,
        basename($template->file_path)
    );
})->name('templates.download')->middleware(['auth']);
```

**Problème :**
- `Storage::exists()` et `Storage::download()` utilisent le **disque par défaut** (`local`), qui pointe vers `storage/app/private/`
- Les templates sont stockés sur le disque **`templates`**, qui pointe vers `storage/app/templates/`
- Laravel cherchait dans le mauvais répertoire → **404 Not Found**

**Pourquoi ça marchait pour les documents ?**
La route des documents (`documents.download`) spécifiait explicitement `Storage::disk('public')`, donc pas de problème.

---

## ✅ Solution Appliquée

### Modification du Code

**Fichier:** `routes/web.php` (lignes 8-22)

```php
// ✅ CODE CORRIGÉ (APRÈS)
Route::get('/admin/templates/{id}/download', function ($id) {
    $template = DocumentTemplate::findOrFail($id);
    
    // Utiliser le disque 'templates' et la méthode helper du modèle
    if (!$template->fileExists()) {
        abort(404, 'Fichier template introuvable');
    }
    
    // Télécharger avec un nom user-friendly
    return Storage::disk('templates')->download(
        $template->file_path,
        $template->name . '.docx'
    );
})->name('templates.download')->middleware(['auth']);
```

### Changements Apportés

1. **Utilisation explicite du disque `templates`** :
   ```php
   Storage::disk('templates')->download(...)
   ```

2. **Utilisation de la méthode helper du modèle** :
   ```php
   $template->fileExists()  // Au lieu de Storage::exists()
   ```

3. **Nom de fichier user-friendly** :
   ```php
   $template->name . '.docx'  // Ex: "Attestation Standard.docx"
   // Au lieu de: basename($template->file_path)  // Ex: "template_1_attestation-standard.docx"
   ```

---

## 🧪 Tests Effectués

### Test 1 : Vérification de l'existence des fichiers
```bash
ls -la storage/app/templates/
# ✅ Résultat: 4 fichiers .docx trouvés
```

### Test 2 : Vérification du modèle
```php
$template = DocumentTemplate::first();
echo $template->fileExists() ? 'OUI' : 'NON';
// ✅ Résultat: OUI
```

### Test 3 : Test de la méthode download
```php
$response = Storage::disk('templates')->download(
    $template->file_path,
    $template->name . '.docx'
);
// ✅ Résultat: Symfony\Component\HttpFoundation\StreamedResponse
```

### Test 4 : Test via navigateur
- URL testée : `http://regiedeseaux.test/admin/templates/1/download`
- ✅ Résultat : Téléchargement réussi avec le nom "Attestation Standard.docx"

---

## 📦 Déploiement en Production

### Étape 1 : Se connecter au serveur
```bash
ssh administrateur@votre-serveur.com
cd /var/www/regiedeseaux
```

### Étape 2 : Récupérer la correction
```bash
git pull
```

### Étape 3 : Vérifier que le répertoire templates existe
```bash
ls -la storage/app/templates/
# Devrait lister les fichiers .docx
```

### Étape 4 : Vérifier les permissions
```bash
# Les fichiers doivent être lisibles par www-data
ls -la storage/app/templates/
# Si nécessaire, corriger :
sudo chown -R www-data:www-data storage/app/templates/
chmod -R 755 storage/app/templates/
```

### Étape 5 : Vider les caches Laravel
```bash
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
```

### Étape 6 : Tester le téléchargement
1. Aller sur `https://arrp-test.bureau.eauxdupaysdaix.fr/manage-templates`
2. Cliquer sur le bouton "Télécharger" d'un template
3. ✅ Le fichier doit se télécharger avec le nom du template (ex: "Attestation Standard.docx")

---

## 🔍 Vérifications Supplémentaires

### Si le problème persiste en production

#### 1. Vérifier que les templates existent en base
```bash
php artisan tinker --execute="
\$count = App\Models\DocumentTemplate::count();
echo 'Nombre de templates: ' . \$count . PHP_EOL;
"
```

#### 2. Vérifier qu'un template spécifique existe
```bash
php artisan tinker --execute="
\$template = App\Models\DocumentTemplate::find(1);
if (\$template) {
    echo 'Template: ' . \$template->name . PHP_EOL;
    echo 'Fichier: ' . \$template->file_path . PHP_EOL;
    echo 'Existe: ' . (\$template->fileExists() ? 'OUI' : 'NON') . PHP_EOL;
} else {
    echo 'Template introuvable';
}
"
```

#### 3. Vérifier la configuration du disque templates
```bash
php artisan tinker --execute="
\$path = config('filesystems.disks.templates.root');
echo 'Chemin disque templates: ' . \$path . PHP_EOL;
echo 'Existe: ' . (is_dir(\$path) ? 'OUI' : 'NON') . PHP_EOL;
"
```

#### 4. Consulter les logs Laravel
```bash
tail -n 50 storage/logs/laravel.log
```

---

## 📊 Impact et Bénéfices

### Avant la Correction
- ❌ Téléchargement de templates impossible (404)
- ❌ Nécessité de récupérer les fichiers manuellement via SSH/SFTP
- ❌ Mauvaise expérience utilisateur

### Après la Correction
- ✅ Téléchargement de templates fonctionnel
- ✅ Nom de fichier user-friendly ("Attestation Standard.docx")
- ✅ Cohérence avec le téléchargement des documents générés
- ✅ Code plus maintenable (utilisation de `$template->fileExists()`)

---

## 🔗 Références

- **Fichiers modifiés :**
  - `routes/web.php` (lignes 8-22)

- **Modèles concernés :**
  - `App\Models\DocumentTemplate`

- **Configuration :**
  - `config/filesystems.php` (disque `templates`)

- **Documentation Laravel :**
  - [File Storage - Downloading Files](https://laravel.com/docs/12.x/filesystem#downloading-files)
  - [Disk Instances](https://laravel.com/docs/12.x/filesystem#obtaining-disk-instances)

---

## 📝 Notes

- Cette correction s'applique **uniquement aux templates**, pas aux documents générés (qui utilisent déjà le bon disque `public`)
- Le problème était présent en local ET en production car c'était une erreur de logique, pas de configuration
- Aucune modification de base de données nécessaire
- Aucun impact sur les templates existants (ils restent dans `storage/app/templates/`)

---

**✅ Fix testé et validé le 7 janvier 2026**
