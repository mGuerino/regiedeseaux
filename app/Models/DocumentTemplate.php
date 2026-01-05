<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'file_path',
        'is_active',
        'is_default',
        'variables',
        'variable_mappings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'variables' => 'array',
        'variable_mappings' => 'array',
    ];

    /**
     * Récupérer le template par défaut actif
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Définir ce template comme défaut (désactive les autres)
     */
    public function setAsDefault(): void
    {
        self::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    /**
     * Obtenir le chemin absolu du fichier template
     */
    public function getFullPath(): string
    {
        return storage_path("app/{$this->file_path}");
    }

    /**
     * Extraire automatiquement les variables du template Word
     */
    public function extractVariables(): array
    {
        if (!file_exists($this->getFullPath())) {
            return [];
        }

        try {
            $templateProcessor = new TemplateProcessor($this->getFullPath());
            return $templateProcessor->getVariables();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Liste complète des champs disponibles pour le mapping
     */
    public static function getAvailableFields(): array
    {
        return [
            'Demande' => [
                'reference' => 'Référence de la demande',
                'request_date' => 'Date de demande',
                'response_date' => 'Date de réponse',
                'request_status_text' => "Statut (texte: 'En cours', 'Terminée', 'Annulée')",
                'water_status_text' => 'Statut eau potable (texte)',
                'wastewater_status_text' => 'Statut assainissement (texte)',
                'observations' => 'Observations',
                'map_url' => 'URL de la carte',
            ],
            'Demandeur' => [
                'applicant.last_name' => 'Nom',
                'applicant.first_name' => 'Prénom',
                'applicant.full_name' => 'Nom complet (Prénom Nom)',
                'applicant.address' => 'Adresse ligne 1',
                'applicant.address2' => 'Adresse ligne 2',
                'applicant.postal_code' => 'Code postal',
                'applicant.city' => 'Ville',
                'applicant.full_address' => 'Adresse complète',
                'applicant.email' => 'Email',
                'applicant.phone1' => 'Téléphone 1',
                'applicant.phone2' => 'Téléphone 2',
            ],
            'Contact' => [
                'contact.first_name' => 'Prénom',
                'contact.last_name' => 'Nom',
                'contact.full_name' => 'Nom complet',
                'contact.email' => 'Email',
                'contact.phone' => 'Téléphone',
            ],
            'Commune' => [
                'municipality.code' => 'Code',
                'municipality.name' => 'Nom',
                'municipality.postal_code' => 'Code postal',
                'municipality.display_name' => "Nom d'affichage",
            ],
            'Signataire' => [
                'signatory.name' => 'Nom',
                'signatory.title' => 'Fonction',
                'signatory.phone' => 'Téléphone',
                'signatory.email' => 'Email',
            ],
            'Certificateur' => [
                'certifier.name' => 'Nom',
                'certifier.title' => 'Fonction',
                'certifier.phone' => 'Téléphone',
                'certifier.email' => 'Email',
            ],
            'Interlocuteur' => [
                'contactPerson.name' => 'Nom',
                'contactPerson.title' => 'Fonction',
                'contactPerson.phone' => 'Téléphone',
                'contactPerson.email' => 'Email',
            ],
            'Utilisateur' => [
                'followedByUser.name' => 'Nom',
                'followedByUser.first_name' => 'Prénom',
                'followedByUser.full_name' => 'Nom complet',
                'followedByUser.email' => 'Email',
            ],
            'Parcelles et Rues' => [
                'parcelles' => 'Liste des parcelles (séparées par virgule)',
                'demande.adresse' => 'Liste des rues (séparées par retour à la ligne)',
            ],
        ];
    }

    /**
     * Obtenir la liste complète des champs sous forme de tableau plat
     */
    public static function getAvailableFieldsFlat(): array
    {
        $flat = [];
        foreach (self::getAvailableFields() as $group => $fields) {
            foreach ($fields as $key => $label) {
                $flat[$key] = "[$group] $label";
            }
        }
        return $flat;
    }

    /**
     * Obtenir le mapping complet (automatique + manuel)
     */
    public function getFullMapping(): array
    {
        // Mapping automatique (variables standards actuelles dans GenerateWordAction)
        $autoMapping = [
            'demandeur.nom' => 'applicant.last_name',
            'demandeur.prenom' => 'applicant.first_name',
            'demandeur.contact' => 'contact.full_name',
            'demandeur.adresse' => 'applicant.full_address',
            'reference' => 'reference',
            'commune.nom' => 'municipality.name',
            'demande.date' => 'request_date',
            'interlocuteur.nom' => 'contactPerson.name',
            'interlocuteur.tel' => 'contactPerson.phone',
            'statut.adduction' => 'wastewater_status_text',
            'statut.reseauPublic' => 'water_status_text',
            'signataire.nom' => 'signatory.name',
            'signataire.fonction' => 'signatory.title',
            'certifier.nom' => 'certifier.name',
            'certifier.fonction' => 'certifier.title',
            'observations' => 'observations',
            'utilisateur.nom' => 'followedByUser.full_name',
            'parcelles' => 'parcelles',
            'demande.adresse' => 'demande.adresse',
        ];

        // Fusionner avec le mapping manuel
        return array_merge($autoMapping, $this->variable_mappings ?? []);
    }

    /**
     * Identifier les variables non mappées
     */
    public function getUnmappedVariables(): array
    {
        $allMappings = $this->getFullMapping();
        $unmapped = [];

        foreach ($this->variables ?? [] as $variable) {
            if (!isset($allMappings[$variable])) {
                $unmapped[] = $variable;
            }
        }

        return $unmapped;
    }

    /**
     * Obtenir les variables mappées automatiquement
     */
    public function getAutoMappedVariables(): array
    {
        $autoMapping = [
            'demandeur.nom', 'demandeur.prenom', 'demandeur.contact', 'demandeur.adresse',
            'reference', 'commune.nom', 'demande.date',
            'interlocuteur.nom', 'interlocuteur.tel',
            'statut.adduction', 'statut.reseauPublic',
            'signataire.nom', 'signataire.fonction',
            'certifier.nom', 'certifier.fonction',
            'observations', 'utilisateur.nom',
            'parcelles', 'demande.adresse',
        ];

        return array_intersect($this->variables ?? [], $autoMapping);
    }

    /**
     * Obtenir les variables mappées manuellement
     */
    public function getManuallyMappedVariables(): array
    {
        return array_keys($this->variable_mappings ?? []);
    }
}
