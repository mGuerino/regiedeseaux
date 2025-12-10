<?php

namespace App\Filament\Resources\Parcels\Schemas;

use App\Models\Municipality;
use App\Models\Parcel;
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
                                Select::make('codcomm')
                                    ->label('Commune')
                                    ->relationship('municipality', 'name', function ($query) {
                                        $query->orderBy('name');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('ccosec', null))
                                    ->columnSpan(2),

                                Select::make('ccosec')
                                    ->label('Section cadastrale')
                                    ->required()
                                    ->searchable()
                                    ->options(function (callable $get) {
                                        $codcomm = $get('codcomm');
                                        
                                        if (!$codcomm) {
                                            return [];
                                        }

                                        $municipality = Municipality::where('code_with_division', $codcomm)->first();
                                        
                                        if (!$municipality) {
                                            return [];
                                        }

                                        return $municipality->sections()
                                            ->filter()
                                            ->mapWithKeys(fn ($section) => [$section => $section]);
                                    })
                                    ->native(false)
                                    ->reactive()
                                    ->disabled(fn (callable $get) => !$get('codcomm'))
                                    ->helperText('Sélectionnez d\'abord une commune')
                                    ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                        // Mettre à jour sect_cad
                                        $set('sect_cad', $state);
                                        
                                        // Mettre à jour l'aperçu si le numéro est déjà saisi
                                        $parcelle = $get('parcelle');
                                        if ($parcelle && $state) {
                                            $formatted = str_pad($parcelle, 4, '0', STR_PAD_LEFT);
                                            $set('parcel_preview', $state . ' ' . $formatted);
                                            $set('ident', $state . $formatted);
                                            $set('dnupla', $formatted);
                                        }
                                    })
                                    ->columnSpan(1),

                                TextInput::make('parcelle')
                                    ->label('Numéro de parcelle')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(9999)
                                    ->default(1)
                                    ->step(1)
                                    ->extraInputAttributes(['type' => 'number'])
                                    ->helperText('Utilisez les flèches pour incrémenter/décrémenter')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                        $section = $get('ccosec');
                                        if ($state && $section) {
                                            $formatted = str_pad($state, 4, '0', STR_PAD_LEFT);
                                            $ident = $section . $formatted;
                                            $set('ident', $ident);
                                            $set('dnupla', $formatted);
                                            $set('parcel_preview', $section . ' ' . $formatted);
                                        }
                                    })
                                    ->rules([
                                        function (callable $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $codcomm = $get('codcomm');
                                                $section = $get('ccosec');
                                                
                                                if (!$codcomm || !$section) {
                                                    return;
                                                }
                                                
                                                $dnupla = str_pad($value, 4, '0', STR_PAD_LEFT);
                                                $ident = $section . $dnupla;
                                                
                                                $exists = Parcel::where('ident', $ident)
                                                    ->where('codcomm', $codcomm)
                                                    ->exists();
                                                
                                                if ($exists) {
                                                    $fail("La parcelle {$ident} existe déjà pour cette commune.");
                                                }
                                            };
                                        },
                                    ])
                                    ->columnSpan(1),

                                TextInput::make('parcel_preview')
                                    ->label('Aperçu de la parcelle')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default('-- ----')
                                    ->hint('Identifiant final de la parcelle')
                                    ->extraAttributes(['class' => 'font-mono text-lg font-bold text-primary-600'])
                                    ->columnSpan(2),

                                // Champs cachés générés automatiquement
                                TextInput::make('ident')
                                    ->label('Identifiant')
                                    ->required()
                                    ->maxLength(255)
                                    ->readOnly()
                                    ->dehydrated()
                                    ->hidden()
                                    ->columnSpan(1),

                                TextInput::make('dnupla')
                                    ->label('Plan')
                                    ->maxLength(255)
                                    ->readOnly()
                                    ->dehydrated()
                                    ->hidden()
                                    ->columnSpan(1),

                                TextInput::make('sect_cad')
                                    ->label('Section')
                                    ->maxLength(255)
                                    ->readOnly()
                                    ->dehydrated()
                                    ->hidden()
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }
}
