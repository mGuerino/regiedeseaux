<?php

namespace App\Filament\Widgets;

use App\Models\DocumentTemplate;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TemplateStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = DocumentTemplate::getGlobalStats();
        
        return [
            Stat::make('Total Templates', $stats['total'])
                ->description('Nombre total de templates')
                ->icon(Heroicon::DocumentDuplicate)
                ->color('gray'),
                
            Stat::make('Templates Actifs', $stats['active'])
                ->description('Templates activés')
                ->icon(Heroicon::CheckCircle)
                ->color('success'),
                
            Stat::make('Template par Défaut', $stats['default'] ? $stats['default']->name : 'Aucun')
                ->description($stats['default'] ? 'Template utilisé actuellement' : 'Veuillez définir un template par défaut')
                ->icon(Heroicon::Star)
                ->color($stats['default'] ? 'info' : 'warning'),
                
            Stat::make('Variables Non Mappées', $stats['with_unmapped'])
                ->description('Templates avec variables non mappées')
                ->icon(Heroicon::ExclamationTriangle)
                ->color($stats['with_unmapped'] > 0 ? 'warning' : 'success'),
        ];
    }
}
