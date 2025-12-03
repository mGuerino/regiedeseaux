<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Widgets\RequestsByMunicipalityStats;
use App\Models\Municipality;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

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
                ->badge(fn () => \App\Models\Request::count()),
        ];

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
