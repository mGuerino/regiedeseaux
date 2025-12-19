<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Request;
use Filament\Actions\Action;
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

class ArchiveRequests extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Archivage';

    protected static ?string $title = 'Archivage des demandes';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public ?int $previewCount = null;

    public ?array $previewReferences = null;

    public function getView(): string
    {
        return 'filament.pages.archive-requests';
    }

    public function mount(): void
    {
        $this->form->fill([
            'date_type' => 'response_date',
            'before_date' => now()->subYear()->format('Y-m-d'),
            'request_status' => 2, // Terminée par défaut
        ]);
    }

    public function form(Schema $schema): Schema
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
                            ->relationship('municipality', 'name')
                            ->searchable()
                            ->preload()
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
                            ->content(fn () => $this->getPreviewContent())
                            ->visible(fn () => $this->previewCount !== null),
                    ])
                    ->visible(fn () => $this->previewCount !== null)
                    ->columns(1),
            ]);
    }

    protected function getPreviewContent(): string
    {
        if ($this->previewCount === 0) {
            return '✅ Aucune demande ne correspond aux critères sélectionnés.';
        }

        $content = "📊 **{$this->previewCount} demande(s)** seront archivées.\n\n";
        $content .= "**Références concernées :**\n";

        foreach ($this->previewReferences ?? [] as $ref) {
            $content .= "- {$ref}\n";
        }

        return $content;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Aperçu')
                ->icon(Heroicon::OutlinedEye)
                ->color('info')
                ->action(function () {
                    $data = $this->form->getState();
                    $query = $this->buildQuery($data);

                    $this->previewCount = $query->count();
                    $this->previewReferences = $query->pluck('reference')->take(50)->toArray();

                    if ($this->previewCount > 50) {
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
                ->requiresConfirmation()
                ->modalHeading('Confirmer l\'archivage en lot')
                ->modalDescription(fn () => $this->previewCount 
                    ? "Vous êtes sur le point d'archiver {$this->previewCount} demande(s). Cette action peut être annulée manuellement pour chaque demande."
                    : "Veuillez d'abord cliquer sur 'Aperçu' pour voir combien de demandes seront archivées."
                )
                ->disabled(fn () => $this->previewCount === null || $this->previewCount === 0)
                ->action(function () {
                    $data = $this->form->getState();
                    $query = $this->buildQuery($data);

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
                    $this->previewCount = null;
                    $this->previewReferences = null;
                }),
        ];
    }

    protected function buildQuery(array $data): \Illuminate\Database\Eloquent\Builder
    {
        $query = Request::query()
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
        if (!empty($data['municipality_code'])) {
            $query->where('municipality_code', $data['municipality_code']);
        }

        return $query;
    }
}
