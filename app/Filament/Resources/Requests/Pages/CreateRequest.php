<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Models\Document;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRequest extends CreateRecord
{
    protected static string $resource = RequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->name;
        $data['updated_by'] = Auth::user()->name;
        $data['created_date'] = now();
        $data['updated_date'] = now();

        // Stocker temporairement les attachments pour les traiter après la création
        if (isset($data['attachments'])) {
            $this->attachments = $data['attachments'];
            unset($data['attachments']);
        }

        // Stocker temporairement les parcelles pour les traiter après la création
        if (isset($data['parcels'])) {
            $this->parcels = $data['parcels'];
            unset($data['parcels']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Créer les documents associés si des fichiers ont été uploadés
        if (! empty($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                Document::create([
                    'request_id' => $this->record->id,
                    'file_name' => $attachment,
                    'document_name' => basename($attachment),
                    'created_by' => Auth::user()->name,
                    'created_date' => now(),
                ]);
            }
        }

        // Attacher les parcelles à la demande
        if (! empty($this->parcels)) {
            $this->record->parcels()->attach($this->parcels);
        }
    }

    protected ?array $attachments = null;

    protected ?array $parcels = null;
}
