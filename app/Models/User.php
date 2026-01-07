<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'email',
        'password',
        'is_admin',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Détermine si l'utilisateur peut accéder au panel Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Retourne le nom complet de l'utilisateur pour Filament.
     */
    public function getFilamentName(): string
    {
        // Si first_name existe, afficher "Prénom Nom", sinon juste "Nom"
        return $this->first_name
            ? trim("{$this->first_name} {$this->name}")
            : $this->name;
    }

    /**
     * Retourne l'URL de l'avatar de l'utilisateur pour Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        // Si l'utilisateur a un avatar personnalisé, le retourner
        if ($this->profile_photo_path) {
            return Storage::url($this->profile_photo_path);
        }

        // Sinon, Filament utilisera ui-avatars.com par défaut
        return null;
    }
}
