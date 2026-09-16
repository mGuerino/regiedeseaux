<?php

namespace App\Filament\Widgets;

use App\Enums\ValidationStatus;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Request;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ce qui attend l'équipe, en tête du tableau de bord.
 *
 * Les autres widgets racontent l'activité passée ; celui-ci ouvre la journée
 * sur ce qu'il reste à traiter, et chaque chiffre mène à la liste déjà filtrée.
 */
class ValidationWorkloadOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $awaiting = Request::awaitingValidation()->count();
        $overdue = Request::validationOverdue()->count();
        $withoutResponse = Request::withoutResponse()->count();

        return [
            Stat::make('En attente de validation', $awaiting)
                ->description($awaiting > 0 ? 'Ouvrir la liste à valider' : 'Rien à valider')
                ->descriptionIcon($awaiting > 0 ? 'heroicon-m-arrow-right' : 'heroicon-o-check-circle', 'after')
                ->color($awaiting > 0 ? 'warning' : 'gray')
                ->extraAttributes($this->cardAttributes($awaiting, 'attention'))
                ->url($this->listUrl($awaiting, ['validation_status' => ValidationStatus::Pending->value])),

            Stat::make('En retard de plus de '.Request::VALIDATION_OVERDUE_DAYS.' jours', $overdue)
                ->description($overdue > 0 ? 'Ces validations bloquent une réponse' : 'Aucun retard')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-arrow-right' : 'heroicon-o-check-circle', 'after')
                ->color($overdue > 0 ? 'danger' : 'gray')
                ->extraAttributes($this->cardAttributes($overdue, 'urgent'))
                ->url($this->listUrl($overdue, ['validation_overdue' => '1'])),

            Stat::make('Sans réponse envoyée', $withoutResponse)
                ->description($withoutResponse > 0 ? 'Voir les demandes non clôturées' : 'Tout est clôturé')
                ->descriptionIcon($withoutResponse > 0 ? 'heroicon-m-arrow-right' : 'heroicon-o-check-circle', 'after')
                ->color($withoutResponse > 0 ? 'primary' : 'gray')
                ->extraAttributes($this->cardAttributes($withoutResponse, 'neutral'))
                ->url($this->listUrl($withoutResponse, ['has_response' => '0'])),
        ];
    }

    /**
     * Rend la carte visiblement cliquable et colore son liseré selon l'urgence.
     * À zéro, elle reste sobre : rien n'appelle le clic.
     *
     * @return array<string, string>
     */
    private function cardAttributes(int $count, string $tone): array
    {
        return [
            'class' => 'regie-stat regie-stat-'.($count > 0 ? $tone : 'idle'),
            'title' => $count > 0
                ? 'Ouvrir la liste des demandes correspondante'
                : 'Aucune demande dans cet état',
        ];
    }

    /**
     * Lien vers la liste des demandes, filtre déjà appliqué.
     *
     * À zéro, la carte ne mène nulle part : suivre le lien remplacerait les
     * filtres que l'utilisateur a enregistrés dans sa session par un filtre
     * qu'il n'a pas demandé, pour n'afficher aucune ligne.
     *
     * @param  array<string, string>  $filters
     */
    private function listUrl(int $count, array $filters): ?string
    {
        if ($count === 0) {
            return null;
        }

        $query = [];

        foreach ($filters as $name => $value) {
            $query["filters[{$name}][value]"] = $value;
        }

        return RequestResource::getUrl('index').'?'.http_build_query($query);
    }
}
