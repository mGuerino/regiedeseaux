<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Empêcher un admin de se retirer ses propres droits admin
        if ($this->record->getKey() === auth()->id() && isset($data['is_admin'])) {
            $data['is_admin'] = $this->record->is_admin;
        }

        return $data;
    }
}
