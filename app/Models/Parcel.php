<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public static function createFromCadastre(Municipality $municipality, string $section, int $parcelNumber): self
    {
        $dnupla = str_pad((string) $parcelNumber, 4, '0', STR_PAD_LEFT);
        $ident = $section.$dnupla;
        $codcomm = $municipality->code_with_division;

        return self::create([
            'dnupla' => $dnupla,
            'ccosec' => $section,
            'sect_cad' => $section,
            'codcomm' => $codcomm,
            'ident' => $ident,
            'codeident' => str_pad($codcomm, 9, ' ', STR_PAD_RIGHT).$ident,
            'parcelle' => $parcelNumber,
            'ccocomm' => 0,
            'ccodep' => (int) substr($codcomm, 0, 2),
            'ccodir' => 0,
            'ccoifp' => 0,
            'ccopre' => '',
            'ccovoi' => '',
            'cprsecr' => '',
        ]);
    }

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(Request::class, 'parcel_request', 'parcel_id', 'request_id', 'ident', 'id')
            ->using(ParcelRequest::class)
            ->withTimestamps();
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'codcomm', 'code_with_division');
    }
}
