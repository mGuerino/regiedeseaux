<?php

namespace App\Filament\Resources\Requests\Schemas;

use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Contact;
use App\Models\Parcel;
use App\Models\Road;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequestForm
{
    protected static function makeAgentSelect(string $field, string $label, string $type): Select
    {
        return Select::make($field)
            ->label($label)
            ->options(
                Agent::where('type', $type)
                    ->where('is_active', true)
                    ->pluck('name', 'id')
            )
            ->default(function () use ($type) {
                return Agent::where('type', $type)
                    ->where('is_default', true)
                    ->first()?->id;
            })
            ->searchable()
            ->native(false);
    }

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
                                    ->createOptionForm([
                                        TextInput::make('last_name')
                                            ->label('Nom')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('first_name')
                                            ->label('Prénom')
                                            ->maxLength(255),
                                        TextInput::make('address')
                                            ->label('Adresse')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('postal_code')
                                            ->label('Code postal')
                                            ->required()
                                            ->maxLength(10),
                                        TextInput::make('city')
                                            ->label('Ville')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone1')
                                            ->label('Téléphone 1')
                                            ->tel()
                                            ->maxLength(255),
                                    ])
                                    ->columnSpan(1),

                                TextInput::make('reference')
                                    ->label('Référence')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                Select::make('contact_id')
                                    ->label('Contact')
                                    ->relationship('contact', 'last_name')
                                    ->searchable(['first_name', 'last_name', 'email'])
                                    ->getOptionLabelFromRecordUsing(fn (Contact $record) => "{$record->first_name} {$record->last_name}")
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->createOptionForm([
                                        TextInput::make('first_name')
                                            ->label('Prénom')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->label('Nom')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(255),
                                    ])
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
                    ->description('Sélectionnez une section puis une ou plusieurs parcelles')
                    ->schema([
                        Select::make('section')
                            ->label('Section')
                            ->searchable()
                            ->preload()
                            ->options(function (callable $get) {
                                $municipalityCode = $get('municipality_code');

                                if (! $municipalityCode) {
                                    return [];
                                }

                                $municipality = \App\Models\Municipality::find($municipalityCode);

                                if (! $municipality) {
                                    return [];
                                }

                                return $municipality->sections()
                                    ->filter()
                                    ->mapWithKeys(fn ($section) => [$section => $section]);
                            })
                            ->native(false)
                            ->reactive()
                            ->disabled(fn (callable $get) => ! $get('municipality_code'))
                            ->helperText('Veuillez d\'abord sélectionner une commune')
                            ->afterStateUpdated(fn (callable $set) => $set('parcels', null)),

                        Select::make('parcels')
                            ->label('Parcelles')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (callable $get) {
                                $municipalityCode = $get('municipality_code');
                                $section = $get('section');

                                if (! $municipalityCode) {
                                    return [];
                                }

                                $municipality = \App\Models\Municipality::where('code', $municipalityCode)->first();

                                if (! $municipality) {
                                    return [];
                                }

                                $query = Parcel::where('codcomm', $municipality->code_with_division);

                                if ($section) {
                                    $query->where('ccosec', $section);
                                }

                                return $query->orderBy('ident')
                                    ->pluck('ident', 'ident');
                            })
                            ->native(false)
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('municipality_code'))
                            ->helperText(fn (callable $get) => $get('section')
                                ? 'Parcelles de la section sélectionnée'
                                : 'Sélectionnez une section pour filtrer les parcelles'),
                    ]),

                Section::make('Rues')
                    ->description('Sélectionnez une ou plusieurs rues')
                    ->schema([
                        Select::make('roads')
                            ->label('Rues')
                            ->multiple()
                            ->relationship('roads', 'name')
                            ->searchable()
                            ->preload()
                            ->options(function (callable $get) {
                                $municipalityCode = $get('municipality_code');

                                if (! $municipalityCode) {
                                    return [];
                                }

                                return Road::where('municipality_code', $municipalityCode)
                                    ->orderBy('name')
                                    ->pluck('name', 'CDRURU');
                            })
                            ->native(false)
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('municipality_code'))
                            ->helperText('Veuillez d\'abord sélectionner une commune')
                            ->createOptionForm([
                                TextInput::make('CDRURU')
                                    ->label('Code rue')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Road::class, 'CDRURU')
                                    ->helperText('Code unique de la rue (ex: RUE001)'),
                                TextInput::make('name')
                                    ->label('Nom de la rue')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, callable $get) {
                                $municipalityCode = $get('municipality_code');
                                
                                $road = Road::create([
                                    'CDRURU' => $data['CDRURU'],
                                    'name' => $data['name'],
                                    'municipality_code' => $municipalityCode,
                                ]);

                                return $road->CDRURU;
                            }),
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
                                static::makeAgentSelect('signatory_id', 'Signataire', 'SIGNATAIRE')
                                    ->columnSpan(1),

                                static::makeAgentSelect('certifier_id', 'Attestant', 'ATTESTANT')
                                    ->columnSpan(1),

                                static::makeAgentSelect('contact_person_id', 'Interlocuteur', 'INTERLOCUTEUR')
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
                            ->visibility('private')
                            ->maxSize(10240)
                            ->helperText('Formats acceptés: PDF, JPG, PNG, XLSX, XLS, DOC, DOCX (max 10 MB)'),
                    ])
                    ->collapsible(),
            ]);
    }
}
