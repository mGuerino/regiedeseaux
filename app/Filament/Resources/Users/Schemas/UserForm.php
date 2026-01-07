<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations utilisateur')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('first_name')
                                    ->label('Prénom')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(1),

                                FileUpload::make('profile_photo_path')
                                    ->label('Photo de profil')
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('avatars')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->helperText('Image carrée recommandée (max 2 Mo)')
                                    ->columnSpan(1),

                                TextInput::make('password')
                                    ->label('Mot de passe')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('password_confirmation')
                                    ->label('Confirmer le mot de passe')
                                    ->password()
                                    ->dehydrated(false)
                                    ->same('password')
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Permissions')
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Administrateur')
                            ->helperText('Les administrateurs ont accès au panel Filament.'),
                    ]),
            ]);
    }
}
