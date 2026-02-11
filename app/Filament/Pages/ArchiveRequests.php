<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Request;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ArchiveRequests extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Archivage';

    protected static ?string $title = 'Gestion de l\'archivage';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 99;

    public ?array $dataArchive = [];

    public ?array $dataUnarchive = [];

    public ?int $previewCountArchive = null;

    public ?array $previewReferencesArchive = null;

    public ?int $previewCountUnarchive = null;

    public ?array $previewReferencesUnarchive = null;

    public string $activeTab = 'archive';

    // TEMPORAIREMENT DÉSACTIVÉ - 11/02/2026
    // Réactiver en passant shouldRegisterNavigation() et canAccess() à true
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public function getView(): string
    {
        return 'filament.pages.archive-requests';
    }

    public function mount(): void
    {
        $this->formArchive->fill([
            'date_type' => 'response_date',
            'before_date' => now()->subYear()->format('Y-m-d'),
            'request_status' => 2, // Terminée par défaut
        ]);

        $this->formUnarchive->fill([
            'after_date' => now()->subMonths(3)->format('Y-m-d'),
        ]);
    }

    public function formArchive(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Critères d\'archivage')
                    ->description('Sélectionnez les critères pour identifier les demandes à archiver')
                    ->schema([
                        Radio::make('date_type')
                            ->label('Type de date')
                            ->options([
                                'response_date' => 'Date de réponse',
                                'request_date' => 'Date de demande',
                            ])
                            ->default('response_date')
                            ->required()
                            ->inline()
                            ->columnSpanFull(),

                        DatePicker::make('before_date')
                            ->label('Archiver les demandes avant le')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Les demandes avec une date antérieure à celle-ci seront archivées')
                            ->columnSpanFull(),

                        Select::make('request_status')
                            ->label('Statut de la demande')
                            ->options([
                                'all' => 'Tous les statuts',
                                1 => 'En cours',
                                2 => 'Terminée',
                                3 => 'Annulée',
                            ])
                            ->default(2)
                            ->required()
                            ->native(false)
                            ->helperText('Quel statut de demande archiver ?')
                            ->columnSpanFull(),

                        Select::make('municipality_code')
                            ->label('Commune (optionnel)')
                            ->options(fn () => \App\Models\Municipality::pluck('name', 'code')->toArray())
                            ->searchable()
                            ->native(false)
                            ->helperText('Laisser vide pour archiver toutes les communes')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Aperçu')
                    ->description('Cliquez sur "Aperçu" pour voir combien de demandes seront archivées')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_result')
                            ->label('')
                            ->content(fn () => $this->getPreviewContentArchive())
                            ->visible(fn () => $this->previewCountArchive !== null),
                    ])
                    ->visible(fn () => $this->previewCountArchive !== null)
                    ->columns(1),
            ])
            ->statePath('dataArchive');
    }

    public function formUnarchive(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Critères de désarchivage')
                    ->description('Sélectionnez les critères pour identifier les demandes à désarchiver')
                    ->schema([
                        DatePicker::make('after_date')
                            ->label('Désarchiver les demandes archivées après le')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Les demandes archivées après cette date seront désarchivées')
                            ->columnSpanFull(),

                        Select::make('municipality_code')
                            ->label('Commune (optionnel)')
                            ->options(fn () => \App\Models\Municipality::pluck('name', 'code')->toArray())
                            ->searchable()
                            ->native(false)
                            ->helperText('Laisser vide pour désarchiver toutes les communes')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Aperçu')
                    ->description('Cliquez sur "Aperçu" pour voir combien de demandes seront désarchivées')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('preview_result')
                            ->label('')
                            ->content(fn () => $this->getPreviewContentUnarchive())
                            ->visible(fn () => $this->previewCountUnarchive !== null),
                    ])
                    ->visible(fn () => $this->previewCountUnarchive !== null)
                    ->columns(1),
            ])
            ->statePath('dataUnarchive');
    }

    protected function getPreviewContentArchive(): string
    {
        if ($this->previewCountArchive === 0) {
            return '✅ Aucune demande ne correspond aux critères sélectionnés.';
        }

        $content = "📊 **{$this->previewCountArchive} demande(s)** seront archivées.\n\n";
        $content .= "**Références concernées :**\n";

        foreach ($this->previewReferencesArchive ?? [] as $ref) {
            $content .= "- {$ref}\n";
        }

        return $content;
    }

    protected function getPreviewContentUnarchive(): string
    {
        if ($this->previewCountUnarchive === 0) {
            return '✅ Aucune demande ne correspond aux critères sélectionnés.';
        }

        $content = "📊 **{$this->previewCountUnarchive} demande(s)** seront désarchivées.\n\n";
        $content .= "**Références concernées :**\n";

        foreach ($this->previewReferencesUnarchive ?? [] as $ref) {
            $content .= "- {$ref}\n";
        }

        return $content;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions d'archivage
            Action::make('previewArchive')
                ->label('Aperçu')
                ->icon(Heroicon::OutlinedEye)
                ->color('info')
                ->visible(fn () => $this->activeTab === 'archive')
                ->action(function () {
                    $data = $this->formArchive->getState();
                    $query = $this->buildArchiveQuery($data);

                    $this->previewCountArchive = $query->count();
                    $this->previewReferencesArchive = $query->pluck('reference')->take(50)->toArray();

                    if ($this->previewCountArchive > 50) {
                        Notification::make()
                            ->title('Aperçu limité')
                            ->body('Plus de 50 demandes trouvées. Seules les 50 premières références sont affichées.')
                            ->info()
                            ->send();
                    }
                }),

            Action::make('archive')
                ->label('Archiver')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('danger')
                ->visible(fn () => $this->activeTab === 'archive')
                ->requiresConfirmation()
                ->modalHeading('Confirmer l\'archivage en lot')
                ->modalDescription(fn () => $this->previewCountArchive
                    ? "Vous êtes sur le point d'archiver {$this->previewCountArchive} demande(s). Cette action peut être annulée manuellement pour chaque demande."
                    : "Veuillez d'abord cliquer sur 'Aperçu' pour voir combien de demandes seront archivées."
                )
                ->disabled(fn () => $this->previewCountArchive === null || $this->previewCountArchive === 0)
                ->action(function () {
                    $data = $this->formArchive->getState();
                    $query = $this->buildArchiveQuery($data);

                    $count = $query->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                        'archived_by' => Auth::user()->name,
                    ]);

                    Notification::make()
                        ->title('Archivage terminé')
                        ->body("{$count} demande(s) ont été archivées avec succès.")
                        ->success()
                        ->send();

                    // Réinitialiser l'aperçu
                    $this->previewCountArchive = null;
                    $this->previewReferencesArchive = null;
                }),

            // Actions de désarchivage
            Action::make('previewUnarchive')
                ->label('Aperçu')
                ->icon(Heroicon::OutlinedEye)
                ->color('info')
                ->visible(fn () => $this->activeTab === 'unarchive')
                ->action(function () {
                    $data = $this->formUnarchive->getState();
                    $query = $this->buildUnarchiveQuery($data);

                    $this->previewCountUnarchive = $query->count();
                    $this->previewReferencesUnarchive = $query->pluck('reference')->take(50)->toArray();

                    if ($this->previewCountUnarchive > 50) {
                        Notification::make()
                            ->title('Aperçu limité')
                            ->body('Plus de 50 demandes trouvées. Seules les 50 premières références sont affichées.')
                            ->info()
                            ->send();
                    }
                }),

            Action::make('unarchive')
                ->label('Désarchiver')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('success')
                ->visible(fn () => $this->activeTab === 'unarchive')
                ->requiresConfirmation()
                ->modalHeading('Confirmer le désarchivage en lot')
                ->modalDescription(fn () => $this->previewCountUnarchive
                    ? "Vous êtes sur le point de désarchiver {$this->previewCountUnarchive} demande(s). Ces demandes redeviendront visibles dans la liste principale."
                    : "Veuillez d'abord cliquer sur 'Aperçu' pour voir combien de demandes seront désarchivées."
                )
                ->disabled(fn () => $this->previewCountUnarchive === null || $this->previewCountUnarchive === 0)
                ->action(function () {
                    $data = $this->formUnarchive->getState();
                    $query = $this->buildUnarchiveQuery($data);

                    $count = $query->update([
                        'is_archived' => false,
                        'archived_at' => null,
                        'archived_by' => null,
                    ]);

                    Notification::make()
                        ->title('Désarchivage terminé')
                        ->body("{$count} demande(s) ont été désarchivées avec succès.")
                        ->success()
                        ->send();

                    // Réinitialiser l'aperçu
                    $this->previewCountUnarchive = null;
                    $this->previewReferencesUnarchive = null;
                }),
        ];
    }

    protected function buildArchiveQuery(array $data): \Illuminate\Database\Eloquent\Builder
    {
        $query = Request::withArchived()
            ->where('is_archived', false); // Seulement les demandes non archivées

        // Filtre par date
        $dateColumn = $data['date_type'] ?? 'response_date';
        $beforeDate = $data['before_date'] ?? null;

        if ($beforeDate) {
            $query->where($dateColumn, '<', $beforeDate)
                ->whereNotNull($dateColumn); // Exclure les demandes sans date
        }

        // Filtre par statut
        if (isset($data['request_status']) && $data['request_status'] !== 'all') {
            $query->where('request_status', $data['request_status']);
        }

        // Filtre par commune (optionnel)
        if (! empty($data['municipality_code'])) {
            $query->where('municipality_code', $data['municipality_code']);
        }

        return $query;
    }

    protected function buildUnarchiveQuery(array $data): \Illuminate\Database\Eloquent\Builder
    {
        $query = Request::onlyArchived(); // Seulement les demandes archivées

        // Filtre par date d'archivage
        $afterDate = $data['after_date'] ?? null;

        if ($afterDate) {
            $query->where('archived_at', '>=', $afterDate)
                ->whereNotNull('archived_at'); // Exclure les demandes sans date d'archivage
        }

        // Filtre par commune (optionnel)
        if (! empty($data['municipality_code'])) {
            $query->where('municipality_code', $data['municipality_code']);
        }

        return $query;
    }
}
