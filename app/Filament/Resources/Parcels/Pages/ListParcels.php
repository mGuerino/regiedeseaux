<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Filament\Resources\Parcels\ParcelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParcels extends ListRecords
{
    protected static string $resource = ParcelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
