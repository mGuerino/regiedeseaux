# Fonctionnalité d'envoi d'emails avec documents

## Vue d'ensemble

Cette fonctionnalité permet d'envoyer des emails avec des documents attachés depuis l'interface d'administration Filament.

## Accès à la fonctionnalité

- **URL**: `/admin/send-document-email`
- **Menu**: "Envoyer des emails" dans le panneau latéral
- **Icône**: Avion en papier (paper airplane)

## Fonctionnalités principales

### 1. Sélection des destinataires

**Mise à jour (16 décembre 2025)**: La sélection des destinataires a été améliorée pour inclure toutes les personnes présentes dans la régie.

Trois types de destinataires disponibles (avec groupement par catégorie):

- **Demandeurs (Applicants)**: Tous les demandeurs ayant une adresse email dans la base de données
- **Contacts**: Tous les contacts ayant une adresse email
- **Agents**: Tous les agents (non supprimés) ayant une adresse email

Les destinataires sont affichés sous forme de groupes dans le sélecteur:
```
Demandeurs
  - Jean Dupont (jean.dupont@example.com)
  - Marie Martin (marie.martin@example.com)
Contacts
  - Pierre Durand (pierre@example.com)
Agents
  - Agent Principal (agent@regie.fr)
```

Options supplémentaires:
- **Emails manuels**: Ajout d'adresses email supplémentaires (validation automatique)
- **Recherche**: Le champ est recherchable pour trouver rapidement une personne
- **Sélection multiple**: Possibilité de sélectionner plusieurs destinataires à la fois

### 2. Sélection des documents

- **Maximum**: 4 documents par email
- **Source**: Documents liés aux demandes (table `documents`)
- **Filtrage**: Recherche par nom de document ou référence de demande
- **Types**: Documents uploadés et générés

### 3. Composition du message

- **Sujet**: Champ texte obligatoire (max 255 caractères)
- **Message**: Zone de texte multiligne obligatoire
- **Format**: Texte brut avec préservation des sauts de ligne

### 4. Prévisualisation

Bouton "Prévisualiser" pour vérifier avant l'envoi:
- Sujet du message
- Contenu du message
- Liste des destinataires (avec nombre total)
- Liste des documents joints

### 5. Envoi

- **Mode**: Synchrone (l'utilisateur attend la fin de l'envoi)
- **Validation**: Vérification que tous les fichiers existent
- **Traçabilité**: Enregistrement dans la table `email_logs`
- **Notifications**: Confirmation de succès ou message d'erreur détaillé

## Historique des envois

Tous les envois sont enregistrés dans la table `email_logs` avec:

- Sujet et message
- Liste des destinataires (emails) - JSON
- Clés des destinataires (type + ID) - JSON - **Nouveau (16/12/2025)**
- IDs des documents envoyés (JSON)
- Nom de l'expéditeur
- Nombre de destinataires
- Statut de succès
- Messages d'erreur éventuels
- Horodatage

## Template d'email

Les emails envoyés utilisent un template HTML professionnel avec:

- En-tête gris foncé
- Message formaté (avec sauts de ligne préservés)
- Encadré récapitulatif des documents joints
- Pied de page avec mention "Email automatique"

## Configuration requise

### Mail Driver

Actuellement configuré sur `log` (les emails sont enregistrés dans les logs Laravel).

Pour activer l'envoi réel:
1. Modifier `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@example.com
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@example.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```

2. Redémarrer l'application

### Stockage des documents

Les documents doivent être stockés dans:
- `storage/app/private/` (par défaut)
- `storage/app/public/`

Le système vérifie automatiquement l'existence des fichiers avant l'envoi.

## Limitations

- **Maximum 4 documents** par email (limite technique)
- **Taille des pièces jointes**: Limitée par la configuration PHP et du serveur mail
  - `upload_max_filesize` dans `php.ini`
  - Limites du serveur SMTP
- **Envoi synchrone**: Pour de nombreux destinataires, envisager les queues Laravel

## Évolutions futures possibles

- [ ] Envoi asynchrone via queues Laravel
- [ ] Templates de messages prédéfinis
- [ ] Interface de consultation de l'historique des envois
- [ ] Export de l'historique en CSV/Excel
- [ ] Groupes de destinataires
- [ ] Pièces jointes multiples par type
- [ ] Planification d'envois différés
- [ ] Statistiques d'envoi

## Fichiers créés

### Migration
- `database/migrations/2025_12_10_174444_create_email_logs_table.php`

### Modèles
- `app/Models/EmailLog.php`

### Mailable
- `app/Mail/DocumentEmail.php`

### Pages Filament
- `app/Filament/Pages/SendDocumentEmail.php`

### Vues
- `resources/views/filament/pages/send-document-email.blade.php`
- `resources/views/filament/modals/email-preview.blade.php`
- `resources/views/emails/document.blade.php`

## Support

Pour toute question ou problème:
1. Vérifier les logs Laravel: `storage/logs/laravel.log`
2. Vérifier les emails dans les logs si `MAIL_MAILER=log`
3. Consulter la table `email_logs` pour l'historique

## Exemples d'utilisation

### Cas 1: Envoi à un contact unique
1. Sélectionner 1 contact
2. Sélectionner 1 ou plusieurs documents
3. Rédiger sujet et message
4. Prévisualiser
5. Envoyer

### Cas 2: Envoi à plusieurs contacts + emails manuels
1. Sélectionner plusieurs contacts
2. Ajouter des emails supplémentaires manuellement
3. Sélectionner jusqu'à 4 documents
4. Rédiger sujet et message
5. Prévisualiser
6. Confirmer et envoyer

### Cas 3: Vérification après envoi
1. Consulter la notification de succès
2. Vérifier la table `email_logs` pour les détails
3. Si `MAIL_MAILER=log`, consulter `storage/logs/laravel.log`
