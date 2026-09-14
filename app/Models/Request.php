<?php

namespace App\Models;

use App\Enums\ValidationStatus;
use App\Models\Scopes\ExcludeArchivedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new ExcludeArchivedScope);
    }

    protected $fillable = [
        'applicant_id',
        'contact_id',
        'followed_by_user_id',
        'is_archived',
        'archived_at',
        'archived_by',
        'reference',
        'request_date',
        'response_date',
        'request_status',
        'validation_status',
        'validation_requested_at',
        'validation_requested_by',
        'validated_at',
        'validated_by',
        'rejection_reason',
        'water_status',
        'wastewater_status',
        'observations',
        'signatory_id',
        'map_url',
        'certifier_id',
        'contact_person_id',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
        'municipality_code',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'response_date' => 'date',
            'created_date' => 'date',
            'updated_date' => 'date',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'validation_status' => ValidationStatus::class,
            'validation_requested_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'signatory_id');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'certifier_id');
    }

    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'contact_person_id');
    }

    public function followedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_by_user_id');
    }

    public function validationRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_requested_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function parcels(): BelongsToMany
    {
        return $this->belongsToMany(Parcel::class, 'parcel_request', 'request_id', 'parcel_id', 'id', 'ident')
            ->using(ParcelRequest::class)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('requests')
                    ->join('municipalities', 'municipalities.code', '=', 'requests.municipality_code')
                    ->whereColumn('municipalities.code_with_division', 'parcels.codcomm')
                    ->whereColumn('requests.id', 'parcel_request.request_id');
            })
            ->withTimestamps();
    }

    public function roads(): BelongsToMany
    {
        return $this->belongsToMany(Road::class, 'request_road', 'request_id', 'road_code')
            ->using(RequestRoad::class)
            ->withPivot('road_name')
            ->withTimestamps();
    }

    /**
     * Inclure les demandes archivées dans la requête.
     */
    public function scopeWithArchived($query)
    {
        return $query->withoutGlobalScope(ExcludeArchivedScope::class);
    }

    /**
     * Récupérer uniquement les demandes archivées.
     */
    public function scopeOnlyArchived($query)
    {
        return $query->withoutGlobalScope(ExcludeArchivedScope::class)
            ->where('is_archived', true);
    }

    /**
     * Les demandes en attente de validation par un superviseur.
     */
    public function scopeAwaitingValidation($query)
    {
        return $query->where('validation_status', ValidationStatus::Pending);
    }

    /**
     * L'attestation attend la validation d'un superviseur.
     */
    public function isAwaitingValidation(): bool
    {
        return $this->validation_status === ValidationStatus::Pending;
    }

    /**
     * L'attestation a été validée par un superviseur.
     */
    public function isValidated(): bool
    {
        return $this->validation_status === ValidationStatus::Approved;
    }

    /**
     * L'attestation a été refusée par un superviseur.
     */
    public function isValidationRejected(): bool
    {
        return $this->validation_status === ValidationStatus::Rejected;
    }

    /**
     * L'attestation est en attente de validation ou déjà validée.
     */
    public function hasActiveValidation(): bool
    {
        return $this->isAwaitingValidation() || $this->isValidated();
    }

    /**
     * L'attestation peut être envoyée en validation : elle ne doit pas déjà
     * être en attente, ni avoir été validée.
     */
    public function canBeSentForValidation(): bool
    {
        return ! $this->hasActiveValidation();
    }

    /**
     * Annuler la validation en cours ou accordée : l'attestation devra être
     * renvoyée en validation avant de pouvoir être signée.
     */
    public function resetValidation(): void
    {
        $this->update([
            'validation_status' => null,
            'validation_requested_at' => null,
            'validation_requested_by' => null,
            'validated_at' => null,
            'validated_by' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Récupérer le dernier document généré (attestation) au format demandé.
     */
    public function latestGeneratedDocument(string $extension = 'docx'): ?Document
    {
        return $this->documents()
            ->where('document_type', 'generated')
            ->where('file_name', 'like', '%.'.$extension)
            ->latest('id')
            ->first();
    }
}
