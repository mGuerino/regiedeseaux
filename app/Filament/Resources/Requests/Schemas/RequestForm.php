<?php

namespace App\Filament\Resources\Requests\Schemas;

use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Parcel;
use App\Models\Road;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('municipality_code')
                                    ->label('Commune')
                                    ->relationship('municipality', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->native(false)
                                    ->columnSpan(1),

                                Select::make('applicant_id')
                                    ->label('Demandeur')
                                    ->relationship('applicant', 'last_name')
                                    ->searchable(['last_name', 'first_name'])
                                    ->getOptionLabelFromRecordUsing(fn (Applicant $record) => "{$record->last_name} {$record->first_name}")
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),

                                TextInput::make('reference')
                                    ->label('Référence')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('contact')
                                    ->label('Contact')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                DatePicker::make('request_date')
                                    ->label('Date de la demande')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->columnSpan(1),

                                DatePicker::make('response_date')
                                    ->label('Date de la réponse')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Parcelles')
                    ->description('Sélectionnez une ou plusieurs parcelles')
                    ->schema([
                        Repeater::make('parcels')
                            ->label('Parcelles')
                            ->relationship()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('section_number')
                                            ->label('Section')
                                            ->options(function (callable $get) {
                                                $municipalityCode = $get('../../municipality_code');

                                                if (!$municipalityCode) {
                                                    return [];
                                                }

                                                $municipality = \App\Models\Municipality::where('code', $municipalityCode)->first();

                                                if (!$municipality) {
                                                    return [];
                                                }

                                                return $municipality->sections()
                                                    ->mapWithKeys(fn ($section) => [$section => $section])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->native(false)
                                            ->required()
                                            ->disabled(fn (callable $get) => !$get('../../municipality_code'))
                                            ->helperText('Veuillez d\'abord sélectionner une commune')
                                            ->reactive(),

                                        Select::make('parcel_id')
                                            ->label('Parcelle')
                                            ->options(function (callable $get) {
                                                $municipalityCode = $get('../../municipality_code');
                                                $sectionNumber = $get('section_number');

                                                if (!$municipalityCode || !$sectionNumber) {
                                                    return [];
                                                }

                                                $municipality = \App\Models\Municipality::where('code', $municipalityCode)->first();

                                                if (!$municipality) {
                                                    return [];
                                                }

                                                return Parcel::where('codcomm', $municipality->code_with_division)
                                                    ->where('ccosec', $sectionNumber)
                                                    ->orderBy('ident')
                                                    ->pluck('ident', 'ident');
                                            })
                                            ->searchable()
                                            ->native(false)
                                            ->required()
                                            ->disabled(fn (callable $get) => !$get('section_number'))
                                            ->helperText('Veuillez d\'abord sélectionner une section'),
                                    ]),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une parcelle')
                            ->required(),
                    ]),

                Section::make('Rues')
                    ->description('Sélectionnez une ou plusieurs rues')
                    ->schema([
                        Repeater::make('roads')
                            ->label('Rues')
                            ->relationship()
                            ->schema([
                                Select::make('CDRURU')
                                    ->label('Rue')
                                    ->options(function (callable $get) {
                                        $municipalityCode = $get('../../municipality_code');

                                        if (!$municipalityCode) {
                                            return [];
                                        }

                                        return Road::where('municipality_code', $municipalityCode)
                                            ->orderBy('name')
                                            ->pluck('name', 'CDRURU');
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (callable $get) => !$get('../../municipality_code'))
                                    ->helperText('Veuillez d\'abord sélectionner une commune'),

                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une rue')
                            ->required(),
                    ]),

                Section::make('Statuts et observations')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('request_status')
                                    ->label('Statut de la demande')
                                    ->options([
                                        1 => 'En cours',
                                        2 => 'Terminée',
                                        3 => 'Annulée',
                                    ])
                                    ->default(1)
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),

                                Toggle::make('water_status')
                                    ->label('Connectable AEP')
                                    ->inline(false)
                                    ->columnSpan(1),

                                Toggle::make('wastewater_status')
                                    ->label('Connectable EU')
                                    ->inline(false)
                                    ->columnSpan(1),
                            ]),

                        Textarea::make('observations')
                            ->label('Observations')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Agents')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('signatory_id')
                                    ->label('Signataire')
                                    ->options(
                                        Agent::where('type', 'SIGNATAIRE')
                                            ->where('is_active', true)
                                            ->pluck('name', 'id')
                                    )
                                    ->default(function () {
                                        return Agent::where('type', 'SIGNATAIRE')
                                            ->where('is_default', true)
                                            ->first()?->id;
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1),

                                Select::make('certifier_id')
                                    ->label('Attestant')
                                    ->options(
                                        Agent::where('type', 'ATTESTANT')
                                            ->where('is_active', true)
                                            ->pluck('name', 'id')
                                    )
                                    ->default(function () {
                                        return Agent::where('type', 'ATTESTANT')
                                            ->where('is_default', true)
                                            ->first()?->id;
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1),

                                Select::make('contact_person_id')
                                    ->label('Interlocuteur')
                                    ->options(
                                        Agent::where('type', 'INTERLOCUTEUR')
                                            ->where('is_active', true)
                                            ->pluck('name', 'id')
                                    )
                                    ->default(function () {
                                        return Agent::where('type', 'INTERLOCUTEUR')
                                            ->where('is_default', true)
                                            ->first()?->id;
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Documents')
                    ->schema([
                        FileUpload::make('attachments')
                            ->label('Pièces jointes')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->directory(fn () => now()->format('Y.m'))
                            ->visibility('public')
                            ->maxSize(10240)
                            ->helperText('Formats acceptés: PDF, JPG, PNG, XLSX, XLS, DOC, DOCX (max 10 MB)'),
                    ])
                    ->collapsible(),
            ]);
    }
}
