<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use HasFactory;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'road_management_mode',
        'park_management_mode',
        'last_road_number',
        'postal_code',
        'display_name',
        'park_format',
        'code_with_division',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'municipality_code', 'code');
    }

    public function roads(): HasMany
    {
        return $this->hasMany(Road::class, 'municipality_code', 'code');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'codcomm', 'code_with_division');
    }

    /**
     * Get all distinct cadastral sections for this municipality.
     *
     * @return \Illuminate\Support\Collection
     */
    public function sections()
    {
        return $this->parcels()
            ->distinct()
            ->orderBy('ccosec')
            ->pluck('ccosec');
    }
}
