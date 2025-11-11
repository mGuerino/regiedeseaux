<?php

namespace App\Filament\Resources\Parcels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParcelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ident')
                                    ->label('Identifiant')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn ($record) => $record !== null)
                                    ->columnSpan(1),

                                Select::make('codcomm')
                                    ->label('Commune')
                                    ->relationship('municipality', 'name', function ($query) {
                                        $query->orderBy('name');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),

                                TextInput::make('ccosec')
                                    ->label('Section cadastrale')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('sect_cad')
                                    ->label('Section')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('parcelle')
                                    ->label('Numéro de parcelle')
                                    ->required()
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('dnupla')
                                    ->label('Plan')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Codes administratifs')
                    ->description('Informations cadastrales complémentaires')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('ccocomm')
                                    ->label('Code commune')
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('ccodep')
                                    ->label('Code département')
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('ccodir')
                                    ->label('Code direction')
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('ccoifp')
                                    ->label('Code IFP')
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('ccopre')
                                    ->label('Code préfixe')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('ccovoi')
                                    ->label('Code voie')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('codeident')
                                    ->label('Code identifiant')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('cprsecr')
                                    ->label('Code secret')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
