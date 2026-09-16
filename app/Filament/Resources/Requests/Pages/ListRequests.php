<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Enums\ValidationStatus;
use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Widgets\RequestsWorkloadStats;
use App\Models\Municipality;
use App\Models\Request;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Livewire\Attributes\On;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RequestsWorkloadStats::class,
        ];
    }

    public function getTabs(): array
    {
        // Les onglets portent uniquement les communes : c'est l'accès rapide
        // dont l'équipe a l'habitude. L'état de travail (à valider, en retard)
        // vit dans les indicateurs, afin que les deux axes se combinent.
        $tabs = [
            'all' => Tab::make('Toutes')
                ->badge(fn () => Request::count()),
        ];

        // Un seul regroupement plutôt qu'une sous-requête corrélée par commune :
        // withCount() comptait les 16 700 demandes une fois par ligne de la table
        // des communes, à chaque affichage de la liste.
        $counts = Request::query()
            ->selectRaw('municipality_code, count(*) as total')
            ->whereNotNull('municipality_code')
            ->groupBy('municipality_code')
            ->pluck('total', 'municipality_code');

        $municipalities = Municipality::query()
            ->whereIn('code', $counts->keys())
            ->orderBy('name')
            ->get();

        foreach ($municipalities as $municipality) {
            $tabs[$municipality->code] = Tab::make($municipality->name)
                ->modifyQueryUsing(fn ($query) => $query->where('municipality_code', $municipality->code))
                ->badge($counts[$municipality->code]);
        }

        return $tabs;
    }

    /**
     * Appliquer le filtre d'un indicateur sans toucher à la commune choisie
     * dans les onglets.
     */
    #[On('apply-requests-filter')]
    public function applyRequestsFilter(string $filter): void
    {
        // Les valeurs sont celles que produisent les champs du panneau de
        // filtres — '1' et '0' pour un filtre ternaire, jamais des booléens :
        // une valeur d'un autre type reste accrochée et la croix du badge
        // n'arrive plus à la retirer.
        $filters = [
            'awaiting_validation' => ['validation_status', ValidationStatus::Pending->value],
            'validation_overdue' => ['validation_overdue', '1'],
            'without_response' => ['has_response', '0'],
        ];

        if (! isset($filters[$filter])) {
            return;
        }

        [$key, $value] = $filters[$filter];

        // Un second clic sur le même indicateur retire le filtre, pour revenir
        // à la liste sans avoir à ouvrir le panneau.
        if (($this->tableFilters[$key]['value'] ?? null) === $value) {
            $this->removeTableFilter($key);

            return;
        }

        $this->tableFilters[$key]['value'] = $value;

        // Passer par le point d'entrée de Filament plutôt que par resetPage() :
        // c'est lui qui recopie les filtres en session. Sans cela le filtre
        // s'appliquerait à l'écran puis disparaîtrait au rechargement suivant,
        // la session restituant la sélection d'avant le clic.
        $this->handleTableFilterUpdates();
    }
}
