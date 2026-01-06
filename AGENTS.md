# Instructions OpenCode - Régie des Eaux

## Framework
- **Laravel 12 + Filament 4**
- **Langue**: Français

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
