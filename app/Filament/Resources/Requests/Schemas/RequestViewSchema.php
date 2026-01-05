<?php

namespace App\Filament\Resources\Requests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class RequestViewSchema
{
    public static function getComponents(): array
    {
        return [
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
                                    ->label('Demande suivie par')
                                    ->content(fn ($record) => $record->followedByUser 
                                        ? ($record->followedByUser->first_name 
                                            ? "{$record->followedByUser->first_name} {$record->followedByUser->name}"
                                            : $record->followedByUser->name)
                                        : '-')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Parcelles')
                    ->description('Parcelles associées à cette demande')
                    ->schema([
                        Placeholder::make('parcels_display')
                            ->label('')
                            ->content(fn ($record) => $record->parcels->isEmpty() 
                                ? new HtmlString('<span class="text-gray-500">Aucune parcelle</span>')
                                : new HtmlString(view('filament.components.parcels-badges', ['parcels' => $record->parcels])->render())
                            ),
                    ]),

                Section::make('Rues')
                    ->description('Rues associées à cette demande')
                    ->schema([
                        Placeholder::make('roads_display')
                            ->label('')
                            ->content(fn ($record) => $record->roads->isEmpty() 
                                ? new HtmlString('<span class="text-gray-500">Aucune rue</span>')
                                : new HtmlString(
                                    '<div class="flex flex-wrap gap-2">' .
                                    $record->roads->map(fn ($road) => 
                                        '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800">' . 
                                        e($road->name) . 
                                        '</span>'
                                    )->join('') .
                                    '</div>'
                                )
                            ),
                    ]),

                Section::make('Statuts et observations')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('request_status')
                                    ->label('Statut de la demande')
                                    ->content(fn ($record) => match ($record->request_status) {
                                        1 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-warning-100 text-warning-800">En cours</span>'),
                                        2 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-success-100 text-success-800">Terminée</span>'),
                                        3 => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-danger-100 text-danger-800">Annulée</span>'),
                                        default => new HtmlString('<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">Inconnu</span>'),
                                    })
                                    ->columnSpan(1),

                                Placeholder::make('water_status')
                                    ->label('Connectable AEP')
                                    ->content(fn ($record) => $record->water_status 
                                        ? new HtmlString('<span class="text-success-600 font-semibold">✓ Oui</span>')
                                        : new HtmlString('<span class="text-gray-500">✗ Non</span>'))
                                    ->columnSpan(1),

                                Placeholder::make('wastewater_status')
                                    ->label('Connectable EU')
                                    ->content(fn ($record) => $record->wastewater_status 
                                        ? new HtmlString('<span class="text-success-600 font-semibold">✓ Oui</span>')
                                        : new HtmlString('<span class="text-gray-500">✗ Non</span>'))
                                    ->columnSpan(1),
                            ]),

                        Placeholder::make('observations')
                            ->label('Observations')
                            ->content(fn ($record) => $record->observations 
                                ? new HtmlString('<div class="whitespace-pre-wrap">' . e($record->observations) . '</div>')
                                : new HtmlString('<span class="text-gray-500">Aucune observation</span>'))
                            ->columnSpanFull(),
                    ]),

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
