<?php

namespace App\Filament\Resources\Roads\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de la rue')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('CDRURU')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn ($record) => $record !== null)
                                    ->columnSpan(1),

                                Select::make('municipality_code')
                                    ->label('Commune')
                                    ->relationship('municipality', 'name', function ($query) {
                                        $query->orderBy('name');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),

                                TextInput::make('name')
                                    ->label('Nom de la rue')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ]),
            ]);
    }
}
