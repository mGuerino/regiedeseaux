<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

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
}
