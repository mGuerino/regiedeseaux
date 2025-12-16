<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'subject',
        'message',
        'recipients',
        'recipient_keys',
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
            'recipient_keys' => 'array',
            'document_ids' => 'array',
            'success' => 'boolean',
        ];
    }
}
