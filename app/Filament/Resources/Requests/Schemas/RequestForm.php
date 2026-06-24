<?php

namespace App\Filament\Resources\Requests\Schemas;

use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Contact;
use App\Models\Parcel;
use App\Models\Road;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RequestForm
{
    protected static function makeAgentSelect(string $field, string $label, string $type): Select
    {
        return Select::make($field)
            ->label($label)
            ->options(function (callable $get) use ($type, $field) {
                // Récupérer l'ID de l'agent actuellement assigné
                $currentAgentId = $get($field);

                // Agents actifs du type demandé
                $agents = Agent::where('type', $type)
                    ->where('is_active', true)
                    ->get();

                // Si un agent est assigné mais inactif, l'ajouter aux options
                if ($currentAgentId) {
                    $currentAgent = Agent::find($currentAgentId);
                    if ($currentAgent && ! $currentAgent->is_active) {
                        $agents->push($currentAgent);
                    }
                }

                return $agents->mapWithKeys(function ($agent) {
                    $label = $agent->name;
                    if (! $agent->is_active) {
                        $label .= ' (Inactif)';
                    }

                    return [$agent->id => $label];
                });
            })
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
                                    ->afterStateUpdated(fn (callable $set) => $set('roads', []))
                                    ->native(false)
                                    ->columnSpan(1),

                                TextInput::make('reference')
                                    ->label('Référence')
                                    ->required()
                                    ->maxLength(255)
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
                                    ->columnSpan(2),

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

                                Select::make('followed_by_user_id')
                                    ->label('Demande suivie par')
                                    ->options(fn () => User::query()
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn ($user) => [
                                            $user->id => $user->first_name
                                                ? "{$user->first_name} {$user->name}"
                                                : $user->name,
                                        ])
                                    )
                                    ->default(Auth::id())
                                    ->required()
                                    ->searchable()
                                    ->native(false)
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
                    ->description('Sélectionnez une ou plusieurs sections puis une ou plusieurs parcelles')
                    ->schema([
                        Select::make('section')
                            ->label('Section(s)')
                            ->multiple()
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
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                $selectedParcels = (array) $get('parcels');

                                if (empty($selectedParcels)) {
                                    return;
                                }

                                $sections = (array) $state;

                                $keptParcels = array_values(array_filter(
                                    $selectedParcels,
                                    fn (string $ident): bool => in_array(substr($ident, 0, -4), $sections, true),
                                ));

                                $set('parcels', $keptParcels);
                            }),

                        Select::make('parcels')
                            ->label('Parcelles')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (callable $get) {
                                $municipalityCode = $get('municipality_code');
                                $sections = $get('section');

                                if (! $municipalityCode) {
                                    return [];
                                }

                                $municipality = \App\Models\Municipality::where('code', $municipalityCode)->first();

                                if (! $municipality) {
                                    return [];
                                }

                                $query = Parcel::where('codcomm', $municipality->code_with_division);

                                if (! empty($sections)) {
                                    $query->whereIn('ccosec', (array) $sections);
                                }

                                return $query->orderBy('ident')
                                    ->pluck('ident', 'ident');
                            })
                            ->native(false)
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('municipality_code'))
                            ->helperText(fn (callable $get) => ! empty($get('section'))
                                ? 'Parcelles des sections sélectionnées'
                                : 'Sélectionnez une ou plusieurs sections pour filtrer les parcelles')
                            ->createOptionForm(function ($livewire) {
                                $municipalityCode = data_get($livewire, 'data.municipality_code');
                                $selectedSections = array_values(array_filter((array) data_get($livewire, 'data.section', [])));
                                $defaultSection = count($selectedSections) === 1 ? $selectedSections[0] : null;

                                return [
                                    Select::make('section')
                                        ->label('Section')
                                        ->options(array_combine($selectedSections, $selectedSections))
                                        ->default($defaultSection)
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                            $formatted = str_pad((string) ($get('dnupla') ?: 1), 4, '0', STR_PAD_LEFT);
                                            $set('parcel_preview', ($state ?: '??').' '.$formatted);
                                        }),

                                    TextInput::make('dnupla')
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
                                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                            if ($state) {
                                                $formatted = str_pad($state, 4, '0', STR_PAD_LEFT);
                                                $set('parcel_preview', ($get('section') ?: '??').' '.$formatted);
                                            }
                                        })
                                        ->rules([
                                            function (callable $get) use ($municipalityCode) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get, $municipalityCode) {
                                                    $section = $get('section');

                                                    if (! $municipalityCode || ! $section) {
                                                        return;
                                                    }

                                                    $municipality = \App\Models\Municipality::find($municipalityCode);
                                                    if (! $municipality) {
                                                        return;
                                                    }

                                                    $dnupla = str_pad($value, 4, '0', STR_PAD_LEFT);
                                                    $ident = $section.$dnupla;
                                                    $codcomm = $municipality->code_with_division;

                                                    $exists = Parcel::where('ident', $ident)
                                                        ->where('codcomm', $codcomm)
                                                        ->exists();

                                                    if ($exists) {
                                                        $fail("La parcelle {$ident} existe déjà pour cette commune.");
                                                    }
                                                };
                                            },
                                        ]),

                                    TextInput::make('parcel_preview')
                                        ->label('Aperçu de la parcelle')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->default(($defaultSection ?? '??').' 0001')
                                        ->hint('Identifiant final de la parcelle')
                                        ->extraAttributes(['class' => 'font-mono text-lg font-bold text-primary-600']),
                                ];
                            })
                            ->createOptionUsing(function (array $data, callable $get) {
                                $municipalityCode = $get('municipality_code');
                                $section = $data['section'] ?? null;

                                if (! $municipalityCode || ! $section) {
                                    throw new \Exception('Veuillez sélectionner une commune et une section avant de créer une parcelle.');
                                }

                                $municipality = \App\Models\Municipality::find($municipalityCode);

                                if (! $municipality) {
                                    throw new \Exception('Commune introuvable.');
                                }

                                return Parcel::createFromCadastre($municipality, $section, (int) $data['dnupla'])->ident;
                            }),
                    ]),

                Section::make('Rues')
                    ->description('Sélectionnez une ou plusieurs rues')
                    ->schema([
                        Select::make('roads')
                            ->label('Rues')
                            ->multiple()
                            ->relationship(
                                name: 'roads',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, callable $get): Builder => $query->when(
                                    $get('municipality_code'),
                                    fn (Builder $query, $municipalityCode): Builder => $query->where('municipality_code', $municipalityCode),
                                    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                                ),
                            )
                            ->searchable()
                            ->preload()
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
                                    ->label('Raccordable AEP')
                                    ->inline(false)
                                    ->columnSpan(1),

                                Toggle::make('wastewater_status')
                                    ->label('Raccordable EU')
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
                            ->downloadable()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->disk('public')
                            ->directory(fn () => now()->format('Y.m'))
                            ->visibility('public')
                            ->maxSize(10240)
                            ->helperText('Formats acceptés: PDF, JPG, PNG, XLSX, XLS, DOC, DOCX (max 10 MB)'),
                    ])
                    ->collapsible(),
            ]);
    }
}
