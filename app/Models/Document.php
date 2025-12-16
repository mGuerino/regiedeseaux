<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'document_type',
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
            'document_type' => 'string',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Obtenir l'extension du fichier
     */
    public function getFileExtension(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /**
     * Obtenir l'icône Heroicon selon le type de fichier
     */
    public function getFileIconHeroicon(): string
    {
        return match ($this->getFileExtension()) {
            'pdf' => 'heroicon-o-document-text',
            'png', 'jpg', 'jpeg', 'bmp', 'gif' => 'heroicon-o-photo',
            'docx', 'doc' => 'heroicon-o-document',
            'xlsx', 'xls' => 'heroicon-o-table-cells',
            'zip', 'rar' => 'heroicon-o-archive-box',
            default => 'heroicon-o-paper-clip',
        };
    }

    /**
     * Obtenir la couleur de l'icône selon le type de fichier
     */
    public function getFileIconColor(): string
    {
        return match ($this->getFileExtension()) {
            'pdf' => 'text-red-500',
            'png', 'jpg', 'jpeg', 'bmp', 'gif' => 'text-green-500',
            'docx', 'doc' => 'text-blue-500',
            'xlsx', 'xls' => 'text-emerald-500',
            'zip', 'rar' => 'text-purple-500',
            default => 'text-gray-500',
        };
    }

    /**
     * Obtenir la taille du fichier formatée
     */
    public function getFileSizeFormatted(): string
    {
        try {
            if (! Storage::exists($this->file_name)) {
                return 'N/A';
            }

            $bytes = Storage::size($this->file_name);
            $units = ['o', 'Ko', 'Mo', 'Go'];

            for ($i = 0; $bytes > 1024 && $i < 3; $i++) {
                $bytes /= 1024;
            }

            return round($bytes, 1).' '.$units[$i];
        } catch (\Exception) {
            return 'N/A';
        }
    }

    /**
     * Obtenir la taille du fichier en octets
     */
    public function getFileSizeBytes(): int
    {
        try {
            if (! Storage::exists($this->file_name)) {
                return 0;
            }

            return Storage::size($this->file_name);
        } catch (\Exception) {
            return 0;
        }
    }
}
