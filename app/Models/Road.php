<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Road extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'CDRURU';

    public $timestamps = false;

    protected $fillable = [
        'CDRURU',
        'municipality_code',
        'name',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(Request::class, 'request_road', 'road_code', 'request_id')
            ->using(RequestRoad::class)
            ->withPivot('road_name')
            ->withTimestamps();
    }
}
