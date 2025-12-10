<?php

namespace App\Filament\Widgets;

use App\Models\Request;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\DB;

class RequestsByMunicipalityChart extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $heading = 'Demandes par commune';

    protected static ?int $sort = 2;

    public ?array $filters = [];

    protected function getData(): array
    {
        $data = $this->getRequestsByMunicipality();

        return [
            'datasets' => [
                [
                    'label' => 'Nombre de demandes',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(251, 146, 60)',
                        'rgb(139, 92, 246)',
                        'rgb(236, 72, 153)',
                        'rgb(14, 165, 233)',
                        'rgb(34, 197, 94)',
                        'rgb(249, 115, 22)',
                    ],
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Période')
                ->options([
                    'today' => "Aujourd'hui",
                    'week' => 'Cette semaine',
                    'month' => 'Ce mois',
                    'quarter' => 'Ce trimestre',
                    'year' => 'Cette année',
                    'all' => 'Toutes les périodes',
                ])
                ->default('year'),

            Select::make('status')
                ->label('Statut')
                ->options([
                    'all' => 'Toutes les demandes',
                    '1' => 'En cours',
                    '2' => 'Terminée',
                    '3' => 'Annulée',
                ])
                ->default('all'),
        ]);
    }

    protected function getRequestsByMunicipality()
    {
        $period = $this->filters['period'] ?? 'year';
        $status = $this->filters['status'] ?? 'all';

        $query = Request::select('municipality_code', DB::raw('count(*) as total'))
            ->with('municipality')
            ->whereNotNull('municipality_code');

        // Appliquer le filtre de statut
        if ($status !== 'all') {
            $query->where('request_status', $status);
        }

        // Appliquer le filtre de date
        match ($period) {
            'today' => $query->whereDate('request_date', now()->today()),
            'week' => $query->whereBetween('request_date', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('request_date', now()->month)
                ->whereYear('request_date', now()->year),
            'quarter' => $query->whereBetween('request_date', [now()->startOfQuarter(), now()->endOfQuarter()]),
            'year' => $query->whereYear('request_date', now()->year),
            'all' => null,
            default => $query->whereYear('request_date', now()->year),
        };

        return $query->groupBy('municipality_code')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->municipality?->name ?? 'Non définie',
                    'total' => $item->total,
                ];
            });
    }
}
