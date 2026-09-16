<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Palette explicite, dans la teinte du logo. Passer le seul #003143
            // laissait Filament en déduire une échelle aux boutons cyan vif ;
            // mais #003143 en aplat est trop sombre pour se lire comme du bleu.
            // Les boutons prennent donc un bleu franc (600), et le bleu de la
            // marque revient dans les tons foncés (900).
            ->colors([
                'primary' => [
                    50 => '#eff9fd',
                    100 => '#d9effa',
                    200 => '#b3dff5',
                    300 => '#7fc5e6',
                    400 => '#409ec6',
                    500 => '#0080aa',
                    600 => '#006289',
                    700 => '#004e6f',
                    800 => '#003f59',
                    900 => '#003248',
                    950 => '#001f2f',
                ],
                'secondary' => '#ff9900',
            ])
            ->maxContentWidth(Width::ScreenTwoExtraLarge) // Largeur maximale augmentée pour les tables
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            /* ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets') */
            ->widgets([
                // Le travail à faire d'abord, l'activité passée ensuite.
                \App\Filament\Widgets\ValidationWorkloadOverview::class,
                \App\Filament\Widgets\RequestsOverview::class,
                \App\Filament\Widgets\RequestsByMunicipalityChart::class,
                \App\Filament\Widgets\RequestsTimelineChart::class,
            ])
            ->collapsibleNavigationGroups(true)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                FilamentNavigationGroup::make(NavigationGroup::Referentiels->getLabel()),
                FilamentNavigationGroup::make(NavigationGroup::Administration->getLabel())
                    ->collapsed(),
            ])
            ->profile(EditProfile::class)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('Mon profil'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
