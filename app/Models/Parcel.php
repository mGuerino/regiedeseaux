<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Parcel extends Model
{
    use HasFactory;

    protected $primaryKey = 'objectid';

    protected $fillable = [
        'objectid',
        'ccocomm',
        'ccodep',
        'ccodir',
        'ccoifp',
        'ccopre',
        'ccosec',
        'ccovoi',
        'codcomm',
        'codeident',
        'cprsecr',
        'dnupla',
        'ident',
        'parcelle',
        'sect_cad',
    ];

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(Request::class, 'parcel_request', 'parcel_id', 'request_id')
            ->using(ParcelRequest::class)
            ->withPivot(['label_x', 'label_y', 'section_number', 'parcel_name'])
            ->withTimestamps();
    }
}
