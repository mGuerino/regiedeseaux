<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'title',
        'secondary_title',
        'phone',
        'email',
        'is_active',
        'fax',
        'is_default',
        'signature_path',
    ];

    /**
     * Nom du disque de stockage des images de signature.
     */
    public const SIGNATURE_DISK = 'public';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function requestsAsSigner(): HasMany
    {
        return $this->hasMany(Request::class, 'signatory_id');
    }

    public function requestsAsCertifier(): HasMany
    {
        return $this->hasMany(Request::class, 'certifier_id');
    }

    public function requestsAsContactPerson(): HasMany
    {
        return $this->hasMany(Request::class, 'contact_person_id');
    }

    /**
     * Vérifier que l'agent dispose d'une image de signature exploitable.
     */
    public function hasSignature(): bool
    {
        return filled($this->signature_path)
            && Storage::disk(self::SIGNATURE_DISK)->exists($this->signature_path);
    }

    /**
     * Chemin absolu de l'image de signature, ou null si absente.
     */
    public function getSignatureFullPath(): ?string
    {
        if (! $this->hasSignature()) {
            return null;
        }

        return Storage::disk(self::SIGNATURE_DISK)->path($this->signature_path);
    }
}
