<?php

namespace App\Filament\Resources\Requests\Widgets;

use App\Models\Request;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ce qui attend l'équipe, plutôt que le volume historique par commune : ce
 * dernier était déjà lisible sur les onglets, et n'indiquait aucun travail à
 * faire. Chaque indicateur applique un filtre qui se combine avec la commune
 * sélectionnée dans les onglets.
 */
class RequestsWorkloadStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            $this->awaitingValidationStat(),
            $this->overdueValidationStat(),
            $this->withoutResponseStat(),
        ];
    }

    private function awaitingValidationStat(): Stat
    {
        $count = Request::awaitingValidation()->count();

        return Stat::make('En attente de validation', $count)
            ->description($count > 0 ? 'Filtrer la liste' : 'Rien à valider')
            ->descriptionIcon($count > 0 ? 'heroicon-m-funnel' : 'heroicon-o-check-circle', 'after')
            ->color($count > 0 ? 'warning' : 'gray')
            ->extraAttributes($this->clickable('awaiting_validation', $count, 'attention'));
    }

    private function overdueValidationStat(): Stat
    {
        $count = Request::validationOverdue()->count();

        return Stat::make('En retard de plus de '.Request::VALIDATION_OVERDUE_DAYS.' jours', $count)
            ->description($count > 0 ? 'Filtrer la liste' : 'Aucun retard')
            ->descriptionIcon($count > 0 ? 'heroicon-m-funnel' : 'heroicon-o-check-circle', 'after')
            ->color($count > 0 ? 'danger' : 'gray')
            ->extraAttributes($this->clickable('validation_overdue', $count, 'urgent'));
    }

    private function withoutResponseStat(): Stat
    {
        $count = Request::withoutResponse()->count();

        return Stat::make('Sans réponse envoyée', $count)
            ->description($count > 0 ? 'Filtrer la liste' : 'Tout est clôturé')
            ->descriptionIcon($count > 0 ? 'heroicon-m-funnel' : 'heroicon-o-check-circle', 'after')
            ->color($count > 0 ? 'primary' : 'gray')
            ->extraAttributes($this->clickable('without_response', $count, 'neutral'));
    }

    /**
     * Un indicateur à zéro ne filtre rien : il reste sobre et non cliquable.
     *
     * @return array<string, string>
     */
    private function clickable(string $filter, int $count, string $tone): array
    {
        if ($count === 0) {
            return [
                'class' => 'regie-stat regie-stat-idle',
                'title' => 'Aucune demande dans cet état',
            ];
        }

        $dispatch = "\$dispatch('apply-requests-filter', { filter: '{$filter}' })";

        return [
            'class' => 'regie-stat regie-stat-'.$tone,
            'role' => 'button',
            'tabindex' => '0',
            'title' => 'Filtrer la liste sur cet état — un second clic retire le filtre',
            'wire:click' => $dispatch,
            // La carte est annoncée comme un bouton et reçoit le focus : sans
            // ces deux raccourcis, un utilisateur au clavier l'atteindrait sans
            // jamais pouvoir l'actionner.
            'wire:keydown.enter' => $dispatch,
            'wire:keydown.space.prevent' => $dispatch,
        ];
    }
}
