<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RequestRoad extends Pivot
{
    use HasFactory;

    protected $table = 'request_road';

    protected $fillable = [
        'request_id',
        'road_code',
        'road_name',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class, 'road_code', 'CDRURU');
    }
}
