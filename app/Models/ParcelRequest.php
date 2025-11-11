<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ParcelRequest extends Pivot
{
    use HasFactory;

    protected $table = 'parcel_request';

    protected $fillable = [
        'request_id',
        'parcel_id',
        'label_x',
        'label_y',
        'section_number',
        'parcel_name',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
