# Guide de dépannage - Envoi d'emails

## Problème : Les boutons "Prévisualiser" et "Envoyer" ne font rien

### Solution appliquée

Les actions ont été déplacées de `getFormActions()` vers `getHeaderActions()` car dans Filament 4, pour une page personnalisée, les actions doivent être dans le header de la page.

### Vérifications

1. **Les actions sont dans le header de la page**
   - Fichier: `app/Filament/Pages/SendDocumentEmail.php`
   - Méthode: `getHeaderActions()` (et non `getFormActions()`)

2. **La vue contient le composant modals**
   - Fichier: `resources/views/filament/pages/send-document-email.blade.php`
   - Ligne: `<x-filament-actions::modals />`

3. **Les traits sont bien utilisés**
   - `InteractsWithActions` pour gérer les actions
   - `InteractsWithSchemas` pour gérer le formulaire

### Test en ligne de commande

```bash
# Vérifier que la classe se charge
php artisan tinker --execute="echo \App\Filament\Pages\SendDocumentEmail::class . ' - OK';"

# Vérifier les routes
php artisan route:list --name=send

# Clear cache si nécessaire
php artisan optimize:clear
```

### Accès à la page

- URL: `http://votre-domaine/admin/send-document-email`
- Vous devriez voir :
  - Le formulaire avec 3 sections
  - 2 boutons dans le header : "Prévisualiser" (gris) et "Envoyer" (vert)

### Si ça ne fonctionne toujours pas

1. **Vérifier la console du navigateur**
   - Ouvrir les DevTools (F12)
   - Onglet Console : chercher les erreurs JavaScript
   - Onglet Network : vérifier les requêtes Livewire

2. **Vérifier les logs Laravel**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Tester manuellement une action simple**
   ```php
   // Dans getHeaderActions()
   Action::make('test')
       ->label('Test')
       ->action(function () {
           Notification::make()
               ->title('Test réussi !')
               ->success()
               ->send();
       })
   ```

## Problème : Le formulaire ne se remplit pas

### Solution

Vérifier que `mount()` appelle bien `$this->form->fill()`:

```php
public function mount(): void
{
    $this->form->fill();
}
```

## Problème : Les documents ne s'affichent pas dans la sélection

### Causes possibles

1. **Pas de documents dans la base**
   ```sql
   SELECT COUNT(*) FROM documents;
   ```

2. **Problème de jointure**
   - Vérifier que la table `requests` existe
   - Vérifier la colonne `reference` dans `requests`

### Solution temporaire

Simplifier la requête dans `Select::make('document_ids')`:

```php
->options(function () {
    return Document::query()
        ->limit(100)
        ->pluck('document_name', 'id');
})
```

## Problème : Erreur lors de l'envoi

### Vérifications

1. **Les fichiers existent**
   ```bash
   ls -la storage/app/private/
   ls -la storage/app/public/
   ```

2. **Configuration mail**
   ```bash
   php artisan config:show mail
   ```

3. **Test d'envoi simple**
   ```bash
   php artisan tinker
   ```
   ```php
   Mail::raw('Test email', function($msg) {
       $msg->to('test@example.com')->subject('Test');
   });
   ```

## Problème : L'historique ne s'enregistre pas

### Vérification

```sql
SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 5;
```

### Solution

Vérifier que le modèle `EmailLog` est bien utilisé dans `sendEmail()`:

```php
EmailLog::create([...]);
```

## Notes de débogage Livewire

Pour activer le debug Livewire dans la console du navigateur:

```blade
<!-- Dans votre vue -->
@livewireScripts
<script>
    window.livewire.onError((error) => {
        console.error('Livewire Error:', error);
    });
</script>
```
