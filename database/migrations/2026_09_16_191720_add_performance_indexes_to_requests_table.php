<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index de lecture pour la liste des demandes.
     *
     * Migration purement additive : aucune donnée, colonne ni index existant
     * n'est touché. Le `down()` ne retire que les index créés ici.
     *
     * - (is_archived, created_date) : le scope masque les archivées et la liste
     *   est triée sur created_date, sans qu'aucun index ne couvre ce tri. MySQL
     *   triait les 16 700 lignes à chaque page.
     * - (is_archived, request_status) : même filtre implicite, combiné au statut.
     * - (deleted_at, is_archived, municipality_code) : couvre à la fois les deux
     *   comptages de pagination et le regroupement par commune des badges
     *   d'onglets, que MySQL résolvait par une table temporaire.
     * - validation_requested_at : filtre « en attente depuis plus de 7 jours ».
     * - response_date : filtre « réponse envoyée ».
     * - signatory_id : filtre par signataire.
     *
     * @var array<string, list<string>>
     */
    private const INDEXES = [
        'requests_archived_created_index' => ['is_archived', 'created_date'],
        'requests_archived_status_index' => ['is_archived', 'request_status'],
        'requests_deleted_archived_municipality_index' => ['deleted_at', 'is_archived', 'municipality_code'],
        'requests_validation_requested_at_index' => ['validation_requested_at'],
        'requests_response_date_index' => ['response_date'],
        'requests_signatory_id_index' => ['signatory_id'],
    ];

    public function up(): void
    {
        $existing = $this->existingIndexNames();

        Schema::table('requests', function (Blueprint $table) use ($existing) {
            foreach (self::INDEXES as $name => $columns) {
                if (! in_array($name, $existing, true)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        $existing = $this->existingIndexNames();

        Schema::table('requests', function (Blueprint $table) use ($existing) {
            foreach (array_keys(self::INDEXES) as $name) {
                if (in_array($name, $existing, true)) {
                    $table->dropIndex($name);
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    private function existingIndexNames(): array
    {
        return collect(Schema::getIndexes('requests'))
            ->pluck('name')
            ->all();
    }
};
