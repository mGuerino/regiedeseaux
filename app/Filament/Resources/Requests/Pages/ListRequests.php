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
use Illuminate\Support\Facades\Auth;

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
        $tabs = [
            'all' => Tab::make('Toutes')
                ->badge(fn () => Request::count()),
        ];

        // Onglet de suivi réservé aux superviseurs. Il reste affiché même à zéro :
        // le faire disparaître après la dernière validation laisserait l'onglet
        // actif sans correspondance, et donc la liste complète non filtrée.
        if (Auth::user()?->canValidateAttestations()) {
            $tabs['awaiting_validation'] = Tab::make('À valider')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn ($query) => $query->awaitingValidation())
                ->badge(fn () => Request::awaitingValidation()->count())
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
