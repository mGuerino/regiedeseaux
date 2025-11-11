<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Filament\Resources\Parcels\ParcelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParcel extends EditRecord
{
    protected static string $resource = ParcelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
