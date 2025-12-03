<?php

namespace App\Filament\Resources\Requests\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;

class RequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('applicant.last_name')
                    ->label('Demandeur')
                    ->searchable(['last_name', 'first_name'])
                    ->formatStateUsing(fn ($record) => $record->applicant ? "{$record->applicant->last_name} {$record->applicant->first_name}" : '-')
                    ->sortable(),

                TextColumn::make('municipality.name')
                    ->label('Commune')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parcels_list')
                    ->label('Parcelles')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->parcels->map(fn ($parcel) => $parcel->pivot->parcel_name ?: $parcel->ident))
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('parcels', function ($query) use ($search) {
                            $query->where('ident', 'like', "%{$search}%")
                                ->orWhere('parcel_request.parcel_name', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),

                TextColumn::make('contact')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('request_date')
                    ->label('Date demande')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('response_date')
                    ->label('Date réponse')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('request_status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'En cours',
                        2 => 'Terminée',
                        3 => 'Annulée',
                        default => 'Inconnu',
                    })
                    ->color(fn ($state) => match ($state) {
                        1 => 'warning',
                        2 => 'success',
                        3 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('water_status')
                    ->label('AEP')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('wastewater_status')
                    ->label('EU')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('signatory.name')
                    ->label('Signataire')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('certifier.name')
                    ->label('Attestant')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contactPerson.name')
                    ->label('Interlocuteur')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_by')
                    ->label('Créé par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_date')
                    ->label('Date création')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_by')
                    ->label('Modifié par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_date')
                    ->label('Date modification')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('municipality_code')
                    ->label('Commune')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('request_status')
                    ->label('Statut')
                    ->options([
                        1 => 'En cours',
                        2 => 'Terminée',
                        3 => 'Annulée',
                    ])
                    ->native(false),

                SelectFilter::make('water_status')
                    ->label('Connectable AEP')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                SelectFilter::make('wastewater_status')
                    ->label('Connectable EU')
                    ->options([
                        true => 'Oui',
                        false => 'Non',
                    ])
                    ->native(false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('generate_document')
                    ->label('Générer document')
                    ->icon(Heroicon::Document)
                    ->action(function ($record) {
                        $templateProcessor = new TemplateProcessor(base_path('templates/template_attestation.docx'));

                        // Adresse avec sauts de ligne
                        $addressTextRun = new TextRun();
                        $addressTextRun->addText($record->applicant->adress ?? '');
                        $addressTextRun->addTextBreak();
                        if ($record->applicant->address2) {
                            $addressTextRun->addText($record->applicant->address2);
                            $addressTextRun->addTextBreak();
                        }
                        $addressTextRun->addText(($record->applicant->postal_code ?? '') . ' ' . ($record->applicant->city ?? ''));

                        $parcelsList = $record->parcels->map(function ($parcel) {
                            return $parcel->pivot->parcel_name ?: $parcel->ident;
                        })->implode(', ');
                        //refacotorisation des lignes en dessous
                        $mapping = [
                            'demandeur.nom' => $record->applicant->last_name ?? 'N/A',
                            'demandeur.prenom' => $record->applicant->first_name ?? 'N/A',
                            'demandeur.contact' => $record->contact ?? 'N/A',
                            'demandeur.adresse' => $addressTextRun,
                            'reference' => $record->reference ?? 'N/A',
                            'commune.nom' => $record->municipality->name ?? 'N/A',
                            'demande.date' => $record->request_date ? $record->request_date->format('d/m/Y') : 'N/A',
                            'demande.adresse' => $record->request_address ?? 'N/A',
                            'parcelles' => $parcelsList ?? 'aucune parcelles',
                            'interlocuteur.nom' => $record->contactPerson->name ?? 'N/A',
                            'interlocuteur.tel' => $record->contactPerson->phone ?? 'N/A',
                        ];

                        foreach ($mapping as $key => $value) {
                            if ($key === 'demandeur.adresse') {
                                $templateProcessor->setComplexValue($key, $value);
                            } else {
                                $templateProcessor->setValue($key, $value);
                            }
                        }

                        /* $templateProcessor->setValue('demandeur.nom', $record->applicant->last_name ?? 'N/A'); */
                        /* $templateProcessor->setValue('demandeur.prenom', $record->applicant->first_name ?? 'N/A'); */
                        /* $templateProcessor->setValue('demandeur.contact', $record->contact ?? 'N/A'); */
                        /* $templateProcessor->setComplexValue('demandeur.adresse', $addressTextRun); */
                        /* $templateProcessor->setValue('reference', $record->reference ?? 'N/A'); */
                        /* $templateProcessor->setValue('commune.nom', $record->municipality->name ?? 'N/A'); */
                        /* $templateProcessor->setValue('demande.date', $record->request_date ? $record->request_date->format('d/m/Y') : 'N/A'); */
                        /* $templateProcessor->setValue('demande.adresse', $record->request_address ?? 'N/A'); */
                        /* $templateProcessor->setValue('parcelles', $parcelsList ?: 'N/A'); */
                        /* $templateProcessor->setValue('interlocuteur.nom', $record->contactPerson->name ?? 'N/A'); */
                        /* $templateProcessor->setValue('interlocuteur.tel', $record->contactPerson->phone ?? 'N/A'); */

                        $templateProcessor->saveAs("attestation_{$record->id}.docx");
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_date', 'desc');
    }
}
