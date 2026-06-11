<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use PhpOffice\PhpWord\Settings as PhpWordSettings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PHPWord n'échappe pas les valeurs injectées dans les templates par défaut :
        // un "&" ou "<" dans une donnée produit un docx au XML invalide, illisible par Word
        PhpWordSettings::setOutputEscapingEnabled(true);

        // Enregistrer les widgets Filament utilisés uniquement dans des pages spécifiques
        // (pas sur le Dashboard principal)
        Livewire::component(
            'app.filament.widgets.template-stats-widget',
            \App\Filament\Widgets\TemplateStatsWidget::class
        );
    }
}
