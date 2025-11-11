<?php

namespace App\Filament\Resources\Agents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'SIGNATAIRE' => 'Signataire',
                                        'ATTESTANT' => 'Attestant',
                                        'INTERLOCUTEUR' => 'Interlocuteur',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('SIGNATAIRE')
                                    ->columnSpan(1),

                                TextInput::make('name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('title')
                                    ->label('Titre')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('secondary_title')
                                    ->label('Titre secondaire')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Coordonnées')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('fax')
                                    ->label('Fax')
                                    ->tel()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Paramètres')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Actif')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(1),

                                Toggle::make('is_default')
                                    ->label('Par défaut')
                                    ->default(false)
                                    ->inline(false)
                                    ->helperText('Cet agent sera sélectionné par défaut dans les formulaires')
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }
}
