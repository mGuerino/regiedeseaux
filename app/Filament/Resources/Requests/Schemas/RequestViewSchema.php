<?php

namespace App\Filament\Resources\Requests\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class RequestViewSchema
{
    public static function getComponents(): array
    {
        return [
            // ========================================
            // GRID PRINCIPAL : 2 COLONNES (60% / 40%)
            // ========================================
            Grid::make(5)
                ->schema([
                    // COLONNE GAUCHE (60%) - 3 parts sur 5
                    Section::make('Informations générales')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('municipality.name')
                                        ->label('Commune')
                                        ->content(fn ($record) => $record->municipality?->name ?? '-'),

                                    Placeholder::make('applicant')
                                        ->label('Demandeur')
                                        ->content(fn ($record) => $record->applicant
                                            ? "{$record->applicant->last_name} {$record->applicant->first_name}"
                                            : '-'),

                                    Placeholder::make('reference')
                                        ->label('Référence')
                                        ->content(fn ($record) => $record->reference ?? '-'),

                                    Placeholder::make('contact')
                                        ->label('Contact')
                                        ->content(fn ($record) => $record->contact
                                            ? "{$record->contact->first_name} {$record->contact->last_name}"
                                            : '-'),

                                    Placeholder::make('request_date')
                                        ->label('Date de la demande')
                                        ->content(fn ($record) => $record->request_date
                                            ? $record->request_date->format('d/m/Y')
                                            : '-'),

                                    Placeholder::make('response_date')
                                        ->label('Date de la réponse')
                                        ->content(fn ($record) => $record->response_date
                                            ? $record->response_date->format('d/m/Y')
                                            : '-'),

                                    Placeholder::make('followed_by_user')
                                        ->label('Suivi par')
                                        ->content(fn ($record) => $record->followedByUser
                                            ? ($record->followedByUser->first_name
                                                ? "{$record->followedByUser->first_name} {$record->followedByUser->name}"
                                                : $record->followedByUser->name)
                                            : '-')
                                        ->columnSpan(2),
                                ]),
                        ])
                        ->columnSpan(3), // 60% de la largeur

                    // COLONNE DROITE (40%) - 2 parts sur 5
                    Section::make('Statuts et observations')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('request_status')
                                        ->label('Statut')
                                        ->content(fn ($record) => match ($record->request_status) {
                                            1 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-warning-100 text-warning-800">En cours</span>'),
                                            2 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-success-100 text-success-800">Terminée</span>'),
                                            3 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-danger-100 text-danger-800">Annulée</span>'),
                                            default => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">Inconnu</span>'),
                                        })
                                        ->columnSpan(2),

                                    Placeholder::make('water_status')
                                        ->label('Raccordable AEP')
                                        ->content(fn ($record) => $record->water_status
                                            ? new HtmlString('<span class="text-success-600 font-semibold">✓ Oui</span>')
                                            : new HtmlString('<span class="text-gray-500">✗ Non</span>'))
                                        ->columnSpan(1),

                                    Placeholder::make('wastewater_status')
                                        ->label('Raccordable EU')
                                        ->content(fn ($record) => $record->wastewater_status
                                            ? new HtmlString('<span class="text-success-600 font-semibold">✓ Oui</span>')
                                            : new HtmlString('<span class="text-gray-500">✗ Non</span>'))
                                        ->columnSpan(1),
                                ]),

                            Placeholder::make('observations')
                                ->label('Observations')
                                ->content(fn ($record) => $record->observations
                                    ? new HtmlString('<div class="whitespace-pre-wrap text-sm">'.e($record->observations).'</div>')
                                    : new HtmlString('<span class="text-gray-500 text-sm italic">Aucune observation</span>'))
                                ->columnSpanFull(),
                        ])
                        ->columnSpan(2), // 40% de la largeur
                ]),

            // ========================================
            // SECTION PARCELLES ET RUES (Pleine largeur)
            // ========================================
            Section::make('Parcelles et Rues')
                ->description('Parcelles et rues associées à cette demande')
                ->schema([
                    // Parcelles
                    Placeholder::make('parcelles')
                        ->label('Parcelles')
                        ->content(fn ($record) => $record->parcels->isEmpty()
                            ? new HtmlString('<span class="text-gray-500 text-sm italic">Aucune parcelle</span>')
                            : new HtmlString(view('filament.components.parcels-badges', ['parcels' => $record->parcels])->render())
                        ),

                    // Rues
                    Placeholder::make('rues')
                        ->label('Rues')
                        ->content(fn ($record) => $record->roads->isEmpty()
                            ? new HtmlString('<span class="text-gray-500 text-sm italic">Aucune rue</span>')
                            : new HtmlString(
                                '<div class="flex flex-wrap gap-1.5">'.
                                $record->roads->map(fn ($road) => '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800">'.
                                    e($road->name).
                                    '</span>'
                                )->join('').
                                '</div>'
                            )
                        ),
                ]),

            // ========================================
            // SECTION DOCUMENTS (Pleine largeur)
            // ========================================
            Section::make('Documents')
                ->description('Documents attachés à cette demande')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Placeholder::make('documents')
                        ->label('')
                        ->content(fn ($record) => $record->documents->isEmpty()
                            ? new HtmlString('<span class="text-gray-500 text-sm italic">Aucun document attaché</span>')
                            : new HtmlString(view('filament.components.documents-list', [
                                'documents' => $record->documents->sortByDesc('created_date'),
                            ])->render())
                        ),
                ]),

            // ========================================
            // SECTION AGENTS (Pleine largeur)
            // ========================================
            Section::make('Agents')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Placeholder::make('signatory.name')
                                ->label('Signataire')
                                ->content(fn ($record) => $record->signatory?->name ?? '-')
                                ->columnSpan(1),

                            Placeholder::make('certifier.name')
                                ->label('Attestant')
                                ->content(fn ($record) => $record->certifier?->name ?? '-')
                                ->columnSpan(1),

                            Placeholder::make('contactPerson.name')
                                ->label('Interlocuteur')
                                ->content(fn ($record) => $record->contactPerson?->name ?? '-')
                                ->columnSpan(1),
                        ]),
                ]),

            // ========================================
            // SECTION INFORMATIONS SYSTÈME (Collapsible)
            // ========================================
            Section::make('Informations système')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Placeholder::make('created_by')
                                ->label('Créé par')
                                ->content(fn ($record) => $record->created_by ?? '-'),

                            Placeholder::make('created_date')
                                ->label('Date création')
                                ->content(fn ($record) => $record->created_date
                                    ? $record->created_date->format('d/m/Y H:i')
                                    : '-'),

                            Placeholder::make('updated_by')
                                ->label('Modifié par')
                                ->content(fn ($record) => $record->updated_by ?? '-'),

                            Placeholder::make('updated_date')
                                ->label('Date modification')
                                ->content(fn ($record) => $record->updated_date
                                    ? $record->updated_date->format('d/m/Y H:i')
                                    : '-'),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ];
    }
}
