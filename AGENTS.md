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
