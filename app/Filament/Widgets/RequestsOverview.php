<?php

namespace App\Filament\Widgets;

use App\Models\Request;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RequestsOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.widgets.requests-overview';

    public ?string $filter = 'all';

    protected function getStats(): array
    {
        // Total des demandes
        $totalRequests = $this->applyStatusFilter(Request::query())->count();

        // Demandes ce mois-ci
        $thisMonthRequests = $this->applyStatusFilter(Request::query())
            ->whereMonth('request_date', now()->month)
            ->whereYear('request_date', now()->year)
            ->count();

        // Demandes mois dernier
        $lastMonthRequests = $this->applyStatusFilter(Request::query())
            ->whereMonth('request_date', now()->subMonth()->month)
            ->whereYear('request_date', now()->subMonth()->year)
            ->count();

        // Évolution par rapport au mois dernier
        $monthDifference = $thisMonthRequests - $lastMonthRequests;
        $monthTrend = $monthDifference >= 0 ? 'increase' : 'decrease';
        $monthDescription = $monthDifference >= 0
            ? "+{$monthDifference} par rapport au mois dernier"
            : "{$monthDifference} par rapport au mois dernier";

        // Demandes cette année
        $thisYearRequests = $this->applyStatusFilter(Request::query())
            ->whereYear('request_date', now()->year)
            ->count();

        // Commune avec le plus de demandes ce mois-ci
        $topMunicipality = $this->applyStatusFilter(Request::query())
            ->select('municipality_code', DB::raw('count(*) as total'))
            ->with('municipality')
            ->whereMonth('request_date', now()->month)
            ->whereYear('request_date', now()->year)
            ->groupBy('municipality_code')
            ->orderByDesc('total')
            ->first();

        $topMunicipalityName = $topMunicipality?->municipality?->name ?? 'Aucune';
        $topMunicipalityCount = $topMunicipality?->total ?? 0;

        return [
            Stat::make('Total des demandes', $totalRequests)
                ->description('Toutes les demandes enregistrées')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Demandes ce mois', $thisMonthRequests)
                ->description($monthDescription)
                ->descriptionIcon($monthTrend === 'increase' ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($monthTrend === 'increase' ? 'success' : 'danger')
                ->chart($this->getMonthlyChartData()),

            Stat::make('Demandes cette année', $thisYearRequests)
                ->description(now()->year)
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),

            Stat::make('Commune la plus active', $topMunicipalityName)
                ->description("{$topMunicipalityCount} demandes ce mois")
                ->descriptionIcon('heroicon-o-map-pin')
                ->color('warning'),
        ];
    }

    /**
     * Récupère les données pour le graphique mensuel (6 derniers mois)
     */
    protected function getMonthlyChartData(): array
    {
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = $this->applyStatusFilter(Request::query())
                ->whereMonth('request_date', $date->month)
                ->whereYear('request_date', $date->year)
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Toutes les demandes',
            '1' => 'En cours',
            '2' => 'Terminée',
            '3' => 'Annulée',
        ];
    }

    protected function applyStatusFilter($query)
    {
        if ($this->filter !== 'all') {
            $query->where('request_status', $this->filter);
        }

        return $query;
    }
}
