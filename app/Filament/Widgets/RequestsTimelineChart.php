<?php

namespace App\Filament\Widgets;

use App\Models\Request;
use Filament\Widgets\ChartWidget;

class RequestsTimelineChart extends ChartWidget
{
    protected ?string $heading = 'Évolution des demandes';

    protected static ?int $sort = 3;

    public ?string $filter = 'year';

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

    protected function getFilters(): ?array
    {
        return [
            'week' => 'Cette semaine',
            'month' => 'Ce mois',
            'quarter' => 'Ce trimestre',
            'year' => 'Cette année',
            'last_year' => 'Année dernière',
        ];
    }

    protected function getTimelineData(): array
    {
        return match ($this->filter) {
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
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->translatedFormat('D d');
            $values[] = Request::whereDate('request_date', $date)->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getMonthData(): array
    {
        $labels = [];
        $values = [];
        $daysInMonth = now()->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = now()->startOfMonth()->addDays($day - 1);
            $labels[] = $date->format('d');
            $values[] = Request::whereDate('request_date', $date)->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getQuarterData(): array
    {
        $labels = [];
        $values = [];
        $startOfQuarter = now()->startOfQuarter();

        for ($i = 0; $i < 3; $i++) {
            $month = $startOfQuarter->copy()->addMonths($i);
            $labels[] = $month->translatedFormat('M Y');
            $values[] = Request::whereMonth('request_date', $month->month)
                ->whereYear('request_date', $month->year)
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getYearData(): array
    {
        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = now()->month($month);
            $labels[] = $date->translatedFormat('M');
            $values[] = Request::whereMonth('request_date', $month)
                ->whereYear('request_date', now()->year)
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function getLastYearData(): array
    {
        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = now()->subYear()->month($month);
            $labels[] = $date->translatedFormat('M');
            $values[] = Request::whereMonth('request_date', $month)
                ->whereYear('request_date', now()->subYear()->year)
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
