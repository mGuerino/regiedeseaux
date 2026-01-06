<?php

namespace App\Filament\Resources\Municipalities\Schemas;

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
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(10)
                                    ->disabled(fn ($record) => $record !== null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateCodeWithDivision($state, $get('division'), $set))
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

                                TextInput::make('division')
                                    ->label('Division')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(9)
                                    ->maxLength(1)
                                    ->default('2')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateCodeWithDivision($get('code'), $state, $set))
                                    ->afterStateHydrated(function (TextInput $component, ?string $state, callable $get) {
                                        // Extract division from code_with_division when editing
                                        $codeWithDivision = $get('code_with_division');
                                        if ($codeWithDivision && strlen($codeWithDivision) >= 3) {
                                            $division = substr($codeWithDivision, 2, 1);
                                            $component->state($division);
                                        }
                                    })
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                 TextInput::make('code_with_division')
                                    ->label('Code avec division')
                                    ->required()
                                    ->maxLength(10)
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    protected static function updateCodeWithDivision(?string $code, ?string $division, callable $set): void
    {
        if (!$code || !$division) {
            return;
        }

        // Extract département (first 2 digits) and commune number (rest)
        if (strlen($code) < 3) {
            return;
        }

        $dept = substr($code, 0, 2);
        $num = substr($code, 2);

        // Calculate code_with_division: dept + division + num
        $codeWithDivision = $dept . $division . $num;

        $set('code_with_division', $codeWithDivision);
    }
}
