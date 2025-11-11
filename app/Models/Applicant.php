<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'last_name',
        'first_name',
        'address',
        'address2',
        'postal_code',
        'city',
        'email',
        'phone1',
        'phone2',
        'observations',
        'created_by',
        'created_date',
        'updated_by',
        'updated_date',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date',
            'updated_date' => 'date',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }
}
