<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'subject',
        'message',
        'recipients',
        'document_ids',
        'sent_by',
        'recipients_count',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'document_ids' => 'array',
            'success' => 'boolean',
        ];
    }
}
