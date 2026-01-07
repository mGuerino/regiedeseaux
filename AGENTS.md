# Instructions OpenCode - Régie des Eaux

## Framework & Environment
- **Laravel 12 + Filament 4 + Livewire 3**
- **PHP 8.3.27** | **PHPUnit 11**
- **Langue**: Français (tous labels, messages, textes utilisateurs)
- **Served by**: Laravel Herd (https://regiedeseaux.test)

## Build, Lint & Test Commands

### Development
```bash
composer run dev          # Start all services (server, queue, logs, vite)
npm run dev               # Vite dev server (frontend assets)
npm run build             # Build production assets
php artisan serve         # Start Laravel dev server
php artisan queue:listen  # Start queue worker
php artisan pail          # Live log viewer
```

### Testing
```bash
php artisan test                              # Run all tests
php artisan test tests/Feature/ExampleTest.php  # Run specific test file
php artisan test --filter=testMethodName     # Run single test method
composer run test                             # Clear config + run all tests
```

### Code Quality
```bash
vendor/bin/pint --dirty   # Format changed files (REQUIRED before commit)
vendor/bin/pint           # Format all files
php artisan config:clear  # Clear config cache (before tests)
```

### Artisan Commands
```bash
php artisan make:filament-resource ModelName  # Create Filament resource
php artisan make:livewire ComponentName       # Create Livewire component
php artisan make:test FeatureName             # Create feature test
php artisan make:test FeatureName --unit      # Create unit test
php artisan make:model ModelName -mfs         # Model + migration + factory + seeder
php artisan tinker                            # Interactive PHP console
php artisan documents:clean-duplicates        # Clean duplicate documents (custom)
```

## Code Style Guidelines

### PHP Formatting
- **Indentation**: 4 spaces (no tabs)
- **Line endings**: LF (Unix style)
- **Charset**: UTF-8
- **Final newline**: Required in all files
- **Trailing whitespace**: Remove (except Markdown)

### Imports & Namespaces
- **Order**: Alphabetical, grouped by vendor
- **One import per line**
- **Use statements**: Always use fully qualified class names
- **Example**:
  ```php
  use App\Models\Request;
  use Filament\Actions\Action;
  use Filament\Support\Enums\Width;
  use Illuminate\Database\Eloquent\Model;
  ```

### Type Declarations
- **REQUIRED**: Explicit return types on ALL methods/functions
- **REQUIRED**: Type hints for method parameters
- **Use**: Nullable types (`?string`), union types, array shapes in PHPDoc
- **Example**:
  ```php
  protected function isAccessible(User $user, ?string $path = null): bool
  {
      return $user->can('access', $path);
  }
  ```

### Naming Conventions
- **Classes**: PascalCase (`RequestsTable`, `GenerateWordAction`)
- **Methods/Functions**: camelCase (`mutateFormDataBeforeFill`, `getFileExtension`)
- **Properties**: camelCase (`$applicantId`, `$requestDate`)
- **Constants**: SCREAMING_SNAKE_CASE (`MAX_ATTEMPTS`)
- **Database columns**: snake_case (`request_date`, `municipality_code`)
- **Descriptive names**: `isRegisteredForDiscounts()` NOT `discount()`

### PHP 8.3 Features
- **Constructor property promotion**: Use in `__construct()`
  ```php
  public function __construct(public GitHub $github) { }
  ```
- **Model casts**: Use `casts()` method (not `$casts` property)
  ```php
  protected function casts(): array {
      return ['request_date' => 'date'];
  }
  ```

### Control Structures
- **ALWAYS use curly braces** (even for one-liners)
- **No empty `__construct()`** methods with zero parameters

### Comments & Documentation
- **Prefer**: PHPDoc blocks over inline comments
- **Avoid**: Comments within code (unless very complex logic)
- **Use**: Array shape type definitions in PHPDoc when appropriate

### Error Handling
- **Logging**: Use `Log::error()` with context for debugging
- **Validation**: Use Form Request classes (not inline validation)
- **Null checks**: Always check for null before operations (e.g., `pathinfo()`)
- **Example**:
  ```php
  if (!$extension = pathinfo($this->file_path, PATHINFO_EXTENSION)) {
      Log::error("Failed to extract extension", ['path' => $this->file_path]);
      return '';
  }
  ```

### Laravel Conventions
- **Eloquent**: Prefer relationships over raw queries or joins
- **Eager loading**: Prevent N+1 queries with `with()`
- **Route names**: Use `route('name')` not hardcoded URLs
- **Config**: Use `config('app.name')` NEVER `env('APP_NAME')` outside config files
- **Queues**: Use `ShouldQueue` interface for time-consuming operations

## Règles Critiques Filament v4

### Navigation (Icons, Groups, Items)
**RÈGLE CRITIQUE**: Ne JAMAIS implémenter `HasIcon` sur les enums `NavigationGroup` si les resources du groupe ont déjà des `navigationIcon`. Filament v4 interdit d'avoir des icônes à la fois sur le groupe ET sur ses items.

**Choix**: 
- Soit groupe avec icône (items sans)
- Soit items avec icônes (groupe sans)
- **TOUJOURS préférer les icônes sur les items individuels**

### Enums NavigationGroup
- Implémenter UNIQUEMENT `HasLabel`, PAS `HasIcon` si les resources ont des icônes
- Utiliser `NavigationGroup::make($enum->getLabel())` et NON passer l'enum directement au constructeur
- Couleurs de badge autorisées: `danger`, `gray`, `info`, `primary`, `success`, `warning`

### Enums Filament v4
- **Width**: Utiliser `Filament\Support\Enums\Width` (ExtraSmall, Small, Medium, Large, ExtraLarge, TwoExtraLarge, ThreeExtraLarge, FourExtraLarge, FiveExtraLarge, SixExtraLarge, SevenExtraLarge, Full, MinContent, MaxContent, FitContent, Prose, Screen*)
- **Heroicons**: Utiliser `Filament\Support\Icons\Heroicon`. Format: `Heroicon::Star` (solid), `Heroicon::OutlinedStar` (outline). Filament choisit automatiquement la taille (16px, 20px, 24px)
- **Alignment**: Utiliser `Filament\Support\Enums\Alignment` (Start, Center, End)

### Navigation Groups
- Créer enum qui implémente `HasLabel` pour labels/ordre centralisés
- Optionnel: `HasIcon` SI items n'ont PAS d'icônes
- Activer avec `->collapsibleNavigationGroups(true)` dans panel config
- Utiliser `->collapsed()` sur NavigationGroup pour fermer par défaut

### Form Data Mutation (Hooks de Formulaire)
**RÈGLE CRITIQUE**: Les méthodes `mutateFormDataBeforeFill()`, `mutateFormDataBeforeCreate()`, et `mutateFormDataBeforeSave()` s'utilisent UNIQUEMENT dans les **Page classes** (CreateRecord, EditRecord, ViewRecord), JAMAIS sur les objets `Schema`.

**❌ INCORRECT** - Provoque BadMethodCallException:
```php
// Dans MunicipalityForm.php (Schema class)
return $schema
    ->components([...])
    ->mutateFormDataBeforeFill(function (array $data): array {
        // ❌ ERREUR: Method does not exist on Schema
        return $data;
    });
```

**✅ CORRECT** - Deux approches valides:

**Approche 1: Hook au niveau du champ** (recommandé pour champs individuels):
```php
// Dans le Schema class (ex: MunicipalityForm.php)
TextInput::make('division')
    ->afterStateHydrated(function (TextInput $component, ?string $state, callable $get) {
        // Pré-remplir à partir d'un autre champ lors de l'édition
        $codeWithDivision = $get('code_with_division');
        if ($codeWithDivision && strlen($codeWithDivision) >= 3) {
            $division = substr($codeWithDivision, 2, 1);
            $component->state($division);
        }
    })
    ->dehydrated(false); // Champ virtuel non sauvegardé
```

**Approche 2: Hook au niveau de la page** (pour mutations multiples):
```php
// Dans EditMunicipality.php (Page class)
protected function mutateFormDataBeforeFill(array $data): array
{
    // Pré-remplir plusieurs champs virtuels
    $data['division'] = substr($data['code_with_division'], 2, 1);
    $data['attachments'] = $this->record->documents->pluck('file_name')->toArray();
    return $data;
}
```

**Quand utiliser chaque approche:**
- **`afterStateHydrated()`**: Pour un seul champ virtuel calculé à partir d'un autre champ
- **`mutateFormDataBeforeFill()`**: Pour pré-remplir plusieurs champs ou charger des relations complexes

**Hooks disponibles dans les Page classes:**
- `mutateFormDataBeforeFill(array $data): array` - Avant de remplir le formulaire (édition/vue)
- `mutateFormDataBeforeCreate(array $data): array` - Avant de créer l'enregistrement
- `mutateFormDataBeforeSave(array $data): array` - Avant de sauvegarder (édition)

**Hooks disponibles sur les champs:**
- `afterStateHydrated()` - Après hydratation initiale du champ
- `afterStateUpdated()` - Après modification par l'utilisateur (requiert `->live()`)
- `dehydrateStateUsing()` - Transformer la valeur avant sauvegarde

**Champs virtuels (non sauvegardés en BD):**
- Utiliser `->dehydrated(false)` pour exclure le champ de la sauvegarde
- Utiliser `afterStateHydrated()` pour pré-remplir à partir d'autres champs
- Utiliser `afterStateUpdated()` pour calculer d'autres champs de façon réactive

## Conventions du Projet

### Langue
Tous les labels, messages, et textes utilisateurs DOIVENT être en français.

### Navigation
Préférer les icônes sur les items de menu plutôt que sur les groupes pour plus de clarté.

### Models
Les demandes (Request) sont le cœur de l'application - toujours les mettre en évidence.

### Status Field
Le champ `request_status` contient des valeurs comme 'En cours', 'Terminé', etc.

## Structure du Projet

### Navigation Groups
- **Emplacement enum**: `app/Enums/NavigationGroup.php`

#### Groupes configurés:
1. **Référentiels** (non collapsed)
   - Applicants
   - Contacts
   - Municipalities
   - Parcels
   - Roads

2. **Administration** (collapsed par défaut)
   - Agents
   - Users

### Resource Principale
**Request (Demandes)** - hors groupe, toujours en première position avec badge du nombre de demandes 'En cours'
