# Guide Rapide - Nouvelles Fonctionnalités Templates

## 🎯 Vue d'ensemble

La page **Gestion des Templates** (`/manage-templates`) a été complètement refactorisée avec :
- 4 cartes de statistiques en haut
- Table enrichie avec 6 actions par ligne
- Mapping interactif des variables
- Validation robuste

---

## 📊 Widget de Statistiques (En haut de page)

```
┌─────────────────────┬─────────────────────┬─────────────────────┬─────────────────────┐
│  Total Templates    │  Templates Actifs   │  Template par Défaut│ Variables Non Mappées│
│       3             │        2            │  Attestation Standard│         1            │
└─────────────────────┴─────────────────────┴─────────────────────┴─────────────────────┘
```

---

## 📋 Table des Templates

### Colonnes affichées :

| Nom | Description | Statut | Variables | Créé le | Modifié le |
|-----|-------------|--------|-----------|---------|------------|
| Attestation Standard | Template principal | **Par défaut** ✅ | ✓ Auto: 15 🔧 Manuel: 2 ⚠️ Non mappées: 0 | 03/01/26 14:30 | il y a 2h |

---

## ⚙️ Actions disponibles

### 1. **Modifier** (Icône crayon)
- Ouvre modal 4XL
- Modifie : nom, description, fichier, statut
- Réextrait automatiquement les variables si nouveau fichier

### 2. **Mapper** (Icône engrenage - Orange)
- **Visible uniquement** si variables non mappées
- Modal 5XL avec liste de toutes les variables non reconnues
- Pour chaque variable :
  ```
  Variable dans le Word : ${ma_variable}
  
  Champ à mapper : [Select searchable]  OU  Valeur fixe : [Input texte]
  ```

### 3. **Télécharger** (Icône flèche bas - Bleu)
- Télécharge le fichier .docx
- Ouvre dans un nouvel onglet

### 4. **Définir par défaut** (Icône étoile - Vert)
- **Visible uniquement** si pas déjà par défaut
- Demande confirmation
- Badge "Par défaut" s'affiche dans la colonne Statut

### 5. **Supprimer** (Icône poubelle - Rouge)
- Demande confirmation
- Supprime le fichier physique + entrée BDD

### 6. **Sélection multiple** (Checkboxes)
- Permet de supprimer plusieurs templates en une fois

---

## 🎨 Détails Visuels

### Badge "Variables" (Colonne)

**Exemple avec toutes les catégories :**
```
[✓ Auto: 12] [🔧 Manuel: 3] [⚠️ Non mappées: 2] [Total: 17]
  (vert)       (bleu)          (orange)          (gris)
```

**Si aucune variable :**
```
[Aucune variable]
  (gris)
```

### Badge "Statut" (Colonne)

- **Par défaut** : Badge vert
- **Actif** : Badge bleu
- **Inactif** : Badge gris

---

## 🔧 Workflow de Mapping

### Scénario : Vous créez un template Word avec la variable `${client.adresse}`

1. **Création** : Upload du fichier Word
   ```
   ✅ Template créé
   17 variable(s) détectée(s).
   
   ⚠️ Variables non mappées détectées
   Certaines variables ne sont pas reconnues. 
   Utilisez le bouton "Mapper" pour les configurer.
   ```

2. **Mapping** : Clic sur le bouton "Mapper" (orange)
   ```
   Modal : Mapper les variables - Mon Template
   
   ┌────────────────────────────────────────────────────┐
   │ Variable dans le Word : ${client.adresse}          │
   │                                                    │
   │ Champ à mapper :     [Demandeur] Adresse complète │
   │ OU                                                 │
   │ Valeur fixe :        [                           ] │
   └────────────────────────────────────────────────────┘
   ```

3. **Résultat** : Badge mis à jour
   ```
   [✓ Auto: 12] [🔧 Manuel: 4] [⚠️ Non mappées: 1] [Total: 17]
   ```

---

## 📝 Champs Disponibles pour le Mapping

### Demande
- reference
- request_date, response_date
- request_status_text
- water_status_text, wastewater_status_text
- observations
- map_url

### Demandeur (Applicant)
- applicant.last_name, applicant.first_name
- applicant.full_name
- applicant.address, applicant.address2
- applicant.postal_code, applicant.city
- applicant.full_address
- applicant.email, applicant.phone1, applicant.phone2

### Contact
- contact.first_name, contact.last_name
- contact.full_name
- contact.email, contact.phone

### Commune (Municipality)
- municipality.code, municipality.name
- municipality.postal_code
- municipality.display_name

### Signataire, Certificateur, Interlocuteur
- signatory.name, signatory.title, signatory.phone, signatory.email
- certifier.name, certifier.title, certifier.phone, certifier.email
- contactPerson.name, contactPerson.title, contactPerson.phone, contactPerson.email

### Utilisateur
- followedByUser.name, followedByUser.first_name
- followedByUser.full_name
- followedByUser.email

### Spécial
- parcelles (liste des parcelles)
- demande.adresse (liste des rues)

---

## ✅ Validation du Fichier Word

Lors de la création, le système vérifie automatiquement :

**Fichier invalide** :
```
❌ Le fichier Word ne contient aucune variable ${...}
```

**Fichier corrompu** :
```
❌ Fichier Word invalide : Cannot read document
```

**Fichier valide** :
```
✅ Template créé
17 variable(s) détectée(s).
```

---

## 🚀 Cas d'Usage Courants

### Créer un nouveau template

1. Clic sur **"Créer un template"** (bouton vert en haut)
2. Remplir le formulaire :
   - Nom : `Attestation Détaillée`
   - Description : `Template avec toutes les informations`
   - Fichier : Upload du .docx
   - ✓ Template actif
   - ✓ Définir comme par défaut
3. Valider
4. Si variables non mappées → Clic sur "Mapper"

### Modifier le template par défaut

1. Trouver le template avec badge "Par défaut" (vert)
2. Clic sur l'icône **crayon** (Modifier)
3. Modifier les champs souhaités
4. Valider

### Changer de template par défaut

1. Trouver le nouveau template souhaité
2. Clic sur l'icône **étoile** (Définir par défaut)
3. Confirmer
4. L'ancien template passe en "Actif", le nouveau en "Par défaut"

### Mapper une variable personnalisée

1. Clic sur **"Mapper"** (orange, visible si variables non mappées)
2. Pour chaque variable :
   - **Option 1** : Sélectionner un champ existant
   - **Option 2** : Définir une valeur fixe (ex: "Régie des Eaux")
3. Clic sur "Enregistrer"
4. Le badge passe de "⚠️ Non mappées" à "🔧 Manuel"

---

## 🔍 Trucs & Astuces

### Toggle des colonnes
- Les colonnes "Créé le" et "Modifié le" sont toggleables
- Clic sur l'icône ⚙️ en haut à droite de la table

### Recherche
- La colonne "Nom" est searchable
- Tapez dans le champ de recherche en haut

### Tri
- Cliquez sur les en-têtes "Nom", "Créé le", "Modifié le" pour trier

### Suppression en masse
- Cochez plusieurs templates
- Clic sur "Supprimer" dans le menu actions groupées
- Confirmer

---

## 📱 Responsive

La page s'adapte automatiquement aux petits écrans :
- Widget de statistiques : 4 colonnes → 2 colonnes → 1 colonne
- Table : Scroll horizontal si nécessaire
- Modals : Largeur adaptative

---

## 🎯 Résultat Final

**Avant** : Page basique avec table simple, aucune action
**Après** : Interface complète et professionnelle avec :
- Vue d'ensemble via statistiques
- Gestion complète (CRUD)
- Mapping interactif
- Validation robuste
- Actions contextuelles
- Expérience utilisateur optimale
