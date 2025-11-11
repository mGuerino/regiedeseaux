<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Models\Document;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger les attachments existants
        $data['attachments'] = $this->record->documents->pluck('file_name')->toArray();

        return $data;
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
    }

    protected ?array $newAttachments = null;
}
