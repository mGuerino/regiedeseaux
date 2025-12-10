<?php

namespace App\Filament\Widgets;

use App\Models\Request;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class RequestsTimelineChart extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $heading = 'Évolution des demandes';

    protected static ?int $sort = 3;

    public ?array $filters = [];

    protected function getData(): array
    {
        $data = $this->getTimelineData();

        return [
            'datasets' => [
                [
                    'label' => 'Demandes',
                    'data' => $data['values'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Période')
                ->options([
                    'week' => 'Cette semaine',
                    'month' => 'Ce mois',
                    'quarter' => 'Ce trimestre',
                    'year' => 'Cette année',
                    'last_year' => 'Année dernière',
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

    protected function getTimelineData(): array
    {
        $period = $this->filters['period'] ?? 'year';

        return match ($period) {
            'week' => $this->getWeekData(),
            'month' => $this->getMonthData(),
            'quarter' => $this->getQuarterData(),
            'year' => $this->getYearData(),
            'last_year' => $this->getLastYearData(),
            default => $this->getYearData(),
        };
    }

    protected function getWeekData(): array
    {
        $status = $this->filters['status'] ?? 'all';
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->translatedFormat('D d');

            $query = Request::whereDate('request_date', $date);
            if ($status !== 'all') {
                $query->where('request_status', $status);
            }
            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getMonthData(): array
    {
        $status = $this->filters['status'] ?? 'all';
        $labels = [];
        $values = [];
        $daysInMonth = now()->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = now()->startOfMonth()->addDays($day - 1);
            $labels[] = $date->format('d');

            $query = Request::whereDate('request_date', $date);
            if ($status !== 'all') {
                $query->where('request_status', $status);
            }
            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getQuarterData(): array
    {
        $status = $this->filters['status'] ?? 'all';
        $labels = [];
        $values = [];
        $startOfQuarter = now()->startOfQuarter();

        for ($i = 0; $i < 3; $i++) {
            $month = $startOfQuarter->copy()->addMonths($i);
            $labels[] = $month->translatedFormat('M Y');

            $query = Request::whereMonth('request_date', $month->month)
                ->whereYear('request_date', $month->year);
            if ($status !== 'all') {
                $query->where('request_status', $status);
            }
            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getYearData(): array
    {
        $status = $this->filters['status'] ?? 'all';
        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = now()->month($month);
            $labels[] = $date->translatedFormat('M');

            $query = Request::whereMonth('request_date', $month)
                ->whereYear('request_date', now()->year);
            if ($status !== 'all') {
                $query->where('request_status', $status);
            }
            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getLastYearData(): array
    {
        $status = $this->filters['status'] ?? 'all';
        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = now()->subYear()->month($month);
            $labels[] = $date->translatedFormat('M');

            $query = Request::whereMonth('request_date', $month)
                ->whereYear('request_date', now()->subYear()->year);
            if ($status !== 'all') {
                $query->where('request_status', $status);
            }
            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
