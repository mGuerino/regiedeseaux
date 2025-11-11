<?php

namespace App\Filament\Resources\Applicants\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('last_name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('first_name')
                                    ->label('Prénom')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Adresse')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('address')
                                    ->label('Adresse')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('address2')
                                    ->label('Complément d\'adresse')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('postal_code')
                                    ->label('Code postal')
                                    ->required()
                                    ->maxLength(10)
                                    ->columnSpan(1),

                                TextInput::make('city')
                                    ->label('Ville')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Contact')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('phone1')
                                    ->label('Téléphone 1')
                                    ->tel()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('phone2')
                                    ->label('Téléphone 2')
                                    ->tel()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Observations')
                    ->schema([
                        Textarea::make('observations')
                            ->label('Observations')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
