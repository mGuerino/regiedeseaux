<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Actions\GenerateWordAction;
use App\Filament\Actions\SendEmailFromRequestAction;
use App\Filament\Actions\SendForValidationAction;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    /**
     * Attributs de la demande sans incidence sur le contenu de l'attestation :
     * leur modification n'annule pas la validation.
     */
    private const ATTRIBUTES_OUTSIDE_ATTESTATION = [
        'request_status',
        'updated_by',
        'updated_date',
        'updated_at',
    ];

    protected bool $hadActiveValidationBeforeSave = false;

    /**
     * @var array<string, mixed>
     */
    protected array $attributesBeforeSave = [];

    /**
     * @var array{parcels: array<int, string>, roads: array<int, string>}
     */
    protected array $relatedKeysBeforeSave = ['parcels' => [], 'roads' => []];

    protected function getHeaderActions(): array
    {
        return [
            SendForValidationAction::make(),
            Action::make('open_validation')
                ->label('Voir la validation')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('warning')
                ->visible(fn () => Auth::user()->canValidateAttestations() && $this->record->isAwaitingValidation())
                ->url(fn () => RequestResource::getUrl('validation', ['record' => $this->record])),
            SendEmailFromRequestAction::make(),
            GenerateWordAction::make(),
            GenerateWordAction::makeWithDownload(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger les attachments existants
        $data['attachments'] = $this->record->documents->pluck('file_name')->toArray();

        // Charger les parcelles existantes (identifiants)
        $data['parcels'] = $this->record->parcels->pluck('ident')->toArray();

        return $data;
    }

    /**
     * Mémoriser l'état de la demande avant l'enregistrement des relations
     * (rues) et des attributs, pour détecter une modification de l'attestation.
     */
    protected function beforeSave(): void
    {
        $this->hadActiveValidationBeforeSave = $this->record->hasActiveValidation();
        $this->attributesBeforeSave = $this->record->getAttributes();
        $this->relatedKeysBeforeSave = $this->relatedKeys();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;
        $data['updated_date'] = now();

        // Stocker temporairement les attachments pour les traiter après la sauvegarde
        if (isset($data['attachments'])) {
            $this->newAttachments = $data['attachments'];
            unset($data['attachments']);
        }

        // Stocker temporairement les parcelles pour les traiter après la sauvegarde
        if (isset($data['parcels'])) {
            $this->parcels = $data['parcels'];
            unset($data['parcels']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Mettre à jour les documents si nécessaire
        if (isset($this->newAttachments)) {
            $existingFiles = $this->record->documents->pluck('file_name')->toArray();
            $newFiles = array_diff($this->newAttachments, $existingFiles);

            // Ajouter les nouveaux fichiers
            foreach ($newFiles as $attachment) {
                Document::create([
                    'request_id' => $this->record->id,
                    'file_name' => $attachment,
                    'document_name' => basename($attachment),
                    'created_by' => Auth::user()->name,
                    'created_date' => now(),
                ]);
            }

            // Supprimer les fichiers retirés
            $removedFiles = array_diff($existingFiles, $this->newAttachments);
            if (! empty($removedFiles)) {
                $this->record->documents()->whereIn('file_name', $removedFiles)->delete();
            }
        }

        // Synchroniser les parcelles avec la demande
        if (! empty($this->parcels)) {
            // $this->parcels contient déjà directement les identifiants (strings)
            // Ex: ["13001000AB0001", "13001000AB0002"]
            $this->record->parcels()->sync($this->parcels);
        } else {
            // Si aucune parcelle n'est fournie, détacher toutes les parcelles
            $this->record->parcels()->detach();
        }

        if ($this->hadActiveValidationBeforeSave && $this->attestationContentChanged()) {
            $this->record->resetValidation();

            Notification::make()
                ->title('Validation annulée')
                ->body("La demande a été modifiée après son envoi en validation : l'attestation doit être renvoyée en validation avant d'être signée.")
                ->warning()
                ->duration(10000)
                ->send();
        }
    }

    /**
     * L'enregistrement a-t-il modifié une donnée reprise dans l'attestation ?
     */
    protected function attestationContentChanged(): bool
    {
        // Comparaison souple : la base renvoie 0/1 là où le formulaire envoie false/true
        $changedAttributes = collect($this->record->getChanges())
            ->except(self::ATTRIBUTES_OUTSIDE_ATTESTATION)
            ->filter(fn ($value, string $attribute): bool => $value != ($this->attributesBeforeSave[$attribute] ?? null));

        return $changedAttributes->isNotEmpty() || $this->relatedKeys() !== $this->relatedKeysBeforeSave;
    }

    /**
     * Identifiants des parcelles et des rues rattachées à la demande.
     *
     * @return array{parcels: array<int, string>, roads: array<int, string>}
     */
    protected function relatedKeys(): array
    {
        $normalize = fn ($ids): array => $ids->map(fn ($id): string => (string) $id)->sort()->values()->all();

        return [
            'parcels' => $normalize($this->record->parcels()->allRelatedIds()),
            'roads' => $normalize($this->record->roads()->allRelatedIds()),
        ];
    }

    protected ?array $newAttachments = null;

    protected ?array $parcels = null;
}
