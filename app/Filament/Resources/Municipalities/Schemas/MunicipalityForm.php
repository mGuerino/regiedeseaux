<?php

namespace App\Filament\Resources\Municipalities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MunicipalityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(10)
                                    ->disabled(fn ($record) => $record !== null)
                                    ->columnSpan(1),

                                TextInput::make('name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('display_name')
                                    ->label('Nom d\'affichage')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('postal_code')
                                    ->label('Code postal')
                                    ->required()
                                    ->maxLength(10)
                                    ->columnSpan(1),

                                TextInput::make('code_with_division')
                                    ->label('Code avec division')
                                    ->required()
                                    ->maxLength(10)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Gestion')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('road_management_mode')
                                    ->label('Mode de gestion des rues')
                                    ->options([
                                        'AUTO' => 'Automatique',
                                        'MANUAL' => 'Manuel',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('AUTO')
                                    ->columnSpan(1),

                                Select::make('park_management_mode')
                                    ->label('Mode de gestion des parcs')
                                    ->options([
                                        'AUTO' => 'Automatique',
                                        'MANUAL' => 'Manuel',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('AUTO')
                                    ->columnSpan(1),

                                TextInput::make('last_road_number')
                                    ->label('Dernier numéro de rue')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),

                                TextInput::make('park_format')
                                    ->label('Format des parcs')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                            ]),
                    ]),
            ]);
    }
}
