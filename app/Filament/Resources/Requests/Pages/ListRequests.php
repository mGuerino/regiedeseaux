<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Widgets\RequestsByMunicipalityStats;
use App\Models\Municipality;
use App\Models\Request;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

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
            RequestsByMunicipalityStats::class,
        ];
    }

    public function getTabs(): array
    {
        $awaitingValidationCount = Request::awaitingValidation()->count();

        $tabs = [
            'all' => Tab::make('Toutes')
                ->badge(fn () => Request::count()),
        ];

        // Onglet de suivi des attestations en attente, masqué lorsqu'il est vide
        if ($awaitingValidationCount > 0) {
            $tabs['awaiting_validation'] = Tab::make('À valider')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn ($query) => $query->awaitingValidation())
                ->badge($awaitingValidationCount)
                ->badgeColor('warning');
        }

        $municipalities = Municipality::query()
            ->withCount('requests')
            ->having('requests_count', '>', 0)
            ->orderBy('name')
            ->get();

        foreach ($municipalities as $municipality) {
            $tabs[$municipality->code] = Tab::make($municipality->name)
                ->modifyQueryUsing(fn ($query) => $query->where('municipality_code', $municipality->code))
                ->badge($municipality->requests_count);
        }

        return $tabs;
    }
}
