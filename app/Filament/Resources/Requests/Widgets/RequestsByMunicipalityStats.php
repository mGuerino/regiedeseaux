<?php

namespace App\Filament\Resources\Requests\Widgets;

use App\Models\Request;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RequestsByMunicipalityStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Récupérer les 4 communes avec le plus de demandes
        $topMunicipalities = Request::select('municipality_code', DB::raw('count(*) as total'))
            ->with('municipality')
            ->whereNotNull('municipality_code')
            ->groupBy('municipality_code')
            ->orderByDesc('total')
            ->limit(4)
            ->get();

        $stats = [];

        foreach ($topMunicipalities as $municipality) {
            // Calculer les demandes du mois en cours pour cette commune
            $thisMonth = Request::where('municipality_code', $municipality->municipality_code)
                ->whereMonth('request_date', now()->month)
                ->whereYear('request_date', now()->year)
                ->count();

            // Calculer les demandes du mois dernier pour cette commune
            $lastMonth = Request::where('municipality_code', $municipality->municipality_code)
                ->whereMonth('request_date', now()->subMonth()->month)
                ->whereYear('request_date', now()->subMonth()->year)
                ->count();

            // Calculer la tendance
            $difference = $thisMonth - $lastMonth;
            $icon = $difference >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down';
            $color = $difference >= 0 ? 'success' : 'danger';

            $description = $difference > 0
                ? "+{$difference} ce mois"
                : ($difference < 0 ? "{$difference} ce mois" : 'Aucune évolution');

            $stats[] = Stat::make(
                $municipality->municipality?->name ?? 'Non définie',
                $municipality->total.' demandes'
            )
                ->description($description)
                ->descriptionIcon($icon)
                ->color($color)
                ->chart($this->getMunicipalityChartData($municipality->municipality_code));
        }

        return $stats;
    }

    /**
     * Récupère les données graphiques des 6 derniers mois pour une commune
     */
    protected function getMunicipalityChartData(string $municipalityCode): array
    {
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Request::where('municipality_code', $municipalityCode)
                ->whereMonth('request_date', $date->month)
                ->whereYear('request_date', $date->year)
                ->count();
            $data[] = $count;
        }

        return $data;
    }
}
