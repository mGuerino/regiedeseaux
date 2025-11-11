<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
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

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->columnSpan(1),

                                TextInput::make('password')
                                    ->label('Mot de passe')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn ($record) => $record === null)
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('password_confirmation')
                                    ->label('Confirmer le mot de passe')
                                    ->password()
                                    ->dehydrated(false)
                                    ->same('password')
                                    ->required(fn ($record) => $record === null)
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }
}
