<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'document_name',
        'file_name',
        'observations',
        'created_by',
        'created_date',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
