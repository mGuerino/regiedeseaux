<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'applicant_id',
        'contact_id',
        'followed_by_user_id',
        'is_archived',
        'reference',
        'request_date',
        'response_date',
        'request_status',
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
}
