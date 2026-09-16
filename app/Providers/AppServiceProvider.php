<?php

namespace App\Providers;

use Filament\Tables\Table;
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

        // Filtres appliqués à la saisie, sans bouton « Appliquer » à valider :
        // sur les écrans de recherche, l'aller-retour supplémentaire fait perdre
        // le fil de ce qu'on est en train d'affiner.
        Table::configureUsing(fn (Table $table) => $table->deferFilters(false));

        // Enregistrer les widgets Filament utilisés uniquement dans des pages spécifiques
        // (pas sur le Dashboard principal)
        Livewire::component(
            'app.filament.widgets.template-stats-widget',
            \App\Filament\Widgets\TemplateStatsWidget::class
        );
    }
}
