<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Widgets\TemplateStatsWidget;
use App\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class ManageTemplates extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $title = "Gestion des Templates d'Attestation";

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    public function getView(): string
    {
        return 'filament.pages.manage-templates';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TemplateStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DocumentTemplate::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->is_default ? 'Par défaut' : ($record->is_active ? 'Actif' : 'Inactif'))
                    ->color(fn ($record) => $record->is_default ? 'success' : ($record->is_active ? 'info' : 'gray')),

                TextColumn::make('variables_status')
                    ->label('Variables')
                    ->formatStateUsing(function ($record) {
                        $stats = $record->getVariableStats();

                        return view('filament.components.template-variables-badges', array_merge($stats, ['record' => $record]));
                    })
                    ->html(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->form(fn () => $this->getFormSchema())
                    ->modalWidth(Width::FourExtraLarge)
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Gérer le fichier si uploadé
                        if (isset($data['file']) && $data['file'] !== $record->file_path) {
                            $slug = Str::slug($data['name']);
                            $fileName = "template_{$record->id}_{$slug}.docx";

                            // Supprimer l'ancien fichier
                            if ($record->file_path && DocumentTemplate::disk()->exists($record->file_path)) {
                                DocumentTemplate::disk()->delete($record->file_path);
                            }

                            // Renommer le nouveau fichier
                            DocumentTemplate::disk()->move($data['file'], $fileName);
                            $data['file_path'] = $fileName;

                            // Réextraire les variables
                            $fullPath = DocumentTemplate::disk()->path($fileName);
                            try {
                                $templateProcessor = new TemplateProcessor($fullPath);
                                $data['variables'] = $templateProcessor->getVariables();
                            } catch (\Exception) {
                                $data['variables'] = [];
                            }
                        } else {
                            // Pas de nouveau fichier — ré-extraire les variables depuis le fichier existant
                            $fullPath = $record->getFullPath();
                            if ($fullPath && file_exists($fullPath)) {
                                try {
                                    $templateProcessor = new TemplateProcessor($fullPath);
                                    $data['variables'] = $templateProcessor->getVariables();
                                } catch (\Exception) {
                                    // Conserver les variables existantes en cas d'erreur
                                }
                            }
                        }

                        unset($data['file']);

                        return $data;
                    })
                    ->after(function ($record, array $data) {
                        if (isset($data['is_default']) && $data['is_default']) {
                            $record->setAsDefault();
                        }
                    }),

                Action::make('view_variables')
                    ->label('Variables')
                    ->icon(Heroicon::Eye)
                    ->color('info')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn ($record) => "Variables - {$record->name}")
                    ->fillForm(fn ($record) => [
                        'mappings' => collect($record->getUnmappedVariables())->map(fn ($var) => [
                            'variable' => $var,
                            'field' => $record->variable_mappings[$var] ?? null,
                            'fixed_value' => null,
                        ])->toArray(),
                    ])
                    ->form(function ($record) {
                        $form = [
                            Placeholder::make('variables_summary')
                                ->label('')
                                ->content(fn () => view('filament.components.variables-summary', ['template' => $record]))
                                ->columnSpanFull(),
                        ];

                        // Si des variables non mappées existent, ajouter le repeater pour les mapper
                        if ($record->hasUnmappedVariables()) {
                            $form[] = Placeholder::make('divider')
                                ->label('')
                                ->content('<hr class="my-6 border-gray-200 dark:border-gray-700">')
                                ->columnSpanFull();

                            $form[] = Placeholder::make('mapping_info')
                                ->label('')
                                ->content('<h3 class="text-base font-semibold text-gray-900 dark:text-white mb-2">🔧 Mapper les variables non reconnues</h3><p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Associez chaque variable à un champ disponible ou définissez une valeur fixe.</p>')
                                ->columnSpanFull();

                            $form[] = Repeater::make('mappings')
                                ->label('')
                                ->schema([
                                    TextInput::make('variable')
                                        ->label('Variable dans le Word')
                                        ->disabled()
                                        ->dehydrated()
                                        ->prefix('${')
                                        ->suffix('}'),

                                    Grid::make(2)
                                        ->schema([
                                            Select::make('field')
                                                ->label('Champ à mapper')
                                                ->options(DocumentTemplate::getAvailableFieldsFlat())
                                                ->searchable()
                                                ->placeholder('Sélectionner un champ...')
                                                ->reactive()
                                                ->afterStateUpdated(fn ($set) => $set('fixed_value', null)),

                                            TextInput::make('fixed_value')
                                                ->label('OU valeur fixe')
                                                ->placeholder('Ex: Texte fixe...')
                                                ->reactive()
                                                ->afterStateUpdated(fn ($set) => $set('field', null)),
                                        ]),
                                ])
                                ->columns(1)
                                ->defaultItems(0)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columnSpanFull();
                        }

                        return $form;
                    })
                    ->action(function ($record, array $data) {
                        // Si pas de variables non mappées, pas d'action
                        if (! $record->hasUnmappedVariables()) {
                            return;
                        }

                        $newMappings = $record->variable_mappings ?? [];

                        foreach ($data['mappings'] ?? [] as $mapping) {
                            $variable = $mapping['variable'];

                            if (! empty($mapping['fixed_value'])) {
                                $newMappings[$variable] = "__FIXED__:{$mapping['fixed_value']}";
                            } elseif (! empty($mapping['field'])) {
                                $newMappings[$variable] = $mapping['field'];
                            }
                        }

                        $record->update(['variable_mappings' => $newMappings]);

                        Notification::make()
                            ->title('Mapping enregistré')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel(fn ($record) => $record->hasUnmappedVariables() ? 'Enregistrer le mapping' : 'Fermer')
                    ->modalCancelAction(fn ($record) => ! $record->hasUnmappedVariables() ? false : null),

                Action::make('download')
                    ->label('Télécharger')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('info')
                    ->url(fn ($record) => route('templates.download', $record))
                    ->openUrlInNewTab(),

                Action::make('set_default')
                    ->label('Définir par défaut')
                    ->icon(Heroicon::Star)
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('Définir comme template par défaut')
                    ->modalDescription('Ce template sera utilisé par défaut pour générer les attestations.')
                    ->action(function ($record) {
                        $record->setAsDefault();

                        Notification::make()
                            ->title('Template défini par défaut')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer le template')
                    ->modalDescription('Êtes-vous sûr de vouloir supprimer ce template ? Cette action est irréversible.')
                    ->before(function ($record) {
                        // Supprimer le fichier physique
                        if ($record->fileExists()) {
                            DocumentTemplate::disk()->delete($record->file_path);
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->before(function ($records) {
                            // Supprimer tous les fichiers physiques
                            foreach ($records as $record) {
                                if ($record->fileExists()) {
                                    DocumentTemplate::disk()->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->paginated(false);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nom du template')
                ->required()
                ->maxLength(255)
                ->placeholder('Ex: Attestation Standard'),

            Textarea::make('description')
                ->label('Description')
                ->rows(3)
                ->placeholder('Description optionnelle du template'),

            FileUpload::make('file')
                ->label('Fichier Word (.docx ou .doc)')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                    'application/msword', // .doc
                ])
                ->maxSize(5120)
                ->disk(DocumentTemplate::DISK)
                ->visibility('private')
                ->helperText('Uploadez un nouveau fichier pour remplacer l\'ancien'),

            Checkbox::make('is_active')
                ->label('Template actif')
                ->default(true),

            Checkbox::make('is_default')
                ->label('Définir comme template par défaut')
                ->default(false),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Créer un template')
                ->icon(Heroicon::Plus)
                ->color('success')
                ->fillForm([])
                ->mountUsing(function ($form) {
                    $form->fill([
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                })
                ->form([
                    TextInput::make('name')
                        ->label('Nom du template')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Attestation Standard'),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Description optionnelle du template'),

                    FileUpload::make('file')
                        ->label('Fichier Word (.docx ou .doc)')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                            'application/msword', // .doc
                        ])
                        ->maxSize(5120)
                        ->disk(DocumentTemplate::DISK)
                        ->visibility('private')
                        ->rules([
                            fn () => function ($attribute, $value, $fail) {
                                if (! $value) {
                                    return;
                                }

                                try {
                                    // Gérer le fichier temporaire pendant l'upload ou le chemin sur disque
                                    if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                        $path = $value->getRealPath();
                                    } else {
                                        $path = DocumentTemplate::disk()->path($value);
                                    }

                                    // Vérifier que le fichier existe
                                    if (! file_exists($path)) {
                                        $fail('Le fichier est introuvable.');

                                        return;
                                    }

                                    // Tenter d'ouvrir avec PhpWord pour valider la structure
                                    $processor = new TemplateProcessor($path);

                                    // Extraire les variables (optionnel, pas d'échec si vide)
                                    $processor->getVariables();

                                } catch (\Exception $e) {
                                    // Message convivial pour tout problème
                                    $fail('Le fichier Word n\'est pas valide ou est corrompu. Assurez-vous qu\'il s\'agit d\'un fichier .docx ou .doc valide.');
                                }
                            },
                        ]),

                    Checkbox::make('is_active')
                        ->label('Template actif')
                        ->default(true),

                    Checkbox::make('is_default')
                        ->label('Définir comme template par défaut')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    // Générer un nom de fichier unique
                    $nextId = DocumentTemplate::max('id') + 1;
                    $slug = Str::slug($data['name']);
                    $fileName = "template_{$nextId}_{$slug}.docx";

                    // Le fichier est déjà uploadé sur le disque templates par Filament
                    // On le renomme juste avec le nom approprié
                    $uploadedPath = $data['file'];
                    DocumentTemplate::disk()->move($uploadedPath, $fileName);

                    // Détecter les variables
                    $fullPath = DocumentTemplate::disk()->path($fileName);
                    $variables = [];
                    try {
                        $templateProcessor = new TemplateProcessor($fullPath);
                        $variables = $templateProcessor->getVariables();
                    } catch (\Exception) {
                        // Ignorer les erreurs
                    }

                    // Créer le template
                    $template = DocumentTemplate::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'file_path' => $fileName,
                        'is_active' => $data['is_active'],
                        'is_default' => false,
                        'variables' => $variables,
                        'variable_mappings' => [],
                    ]);

                    // Définir comme défaut si demandé
                    if ($data['is_default']) {
                        $template->setAsDefault();
                    }

                    Notification::make()
                        ->title('Template créé')
                        ->body(count($variables).' variable(s) détectée(s).')
                        ->success()
                        ->send();

                    // Notifications contextuelles basées sur les variables
                    if (count($variables) === 0) {
                        Notification::make()
                            ->title('Aucune variable détectée')
                            ->body('Ce template ne contient aucune variable ${...}. Les documents générés seront identiques pour toutes les demandes.')
                            ->info()
                            ->send();
                    } elseif (count($template->getUnmappedVariables()) > 0) {
                        Notification::make()
                            ->title('Variables non mappées détectées')
                            ->body('Certaines variables ne sont pas reconnues. Utilisez le bouton "Variables" pour les configurer.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
