<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingAttestation extends Model
{
    use HasFactory;

    protected $fillable = [
        'header_line_1',
        'header_line_2',
        'header_line_3',
        'header_line_4',
        'header_line_5',
        'header_line_6',
        'bottom_line_1',
        'bottom_line_2',
        'bottom_line_3',
        'bottom_line_4',
        'bottom_line_5',
        'bottom_line_6',
    ];
}
