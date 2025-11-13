<?php

namespace App\Filament\Resources\Parcels;

use App\Filament\Resources\Parcels\Pages\CreateParcel;
use App\Filament\Resources\Parcels\Pages\EditParcel;
use App\Filament\Resources\Parcels\Pages\ListParcels;
use App\Filament\Resources\Parcels\Schemas\ParcelForm;
use App\Filament\Resources\Parcels\Tables\ParcelsTable;
use App\Models\Parcel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ParcelResource extends Resource
{
    protected static ?string $model = Parcel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Parcelles';

    protected static ?string $modelLabel = 'parcelle';

    protected static ?string $pluralModelLabel = 'parcelles';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ParcelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParcelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParcels::route('/'),
            'create' => CreateParcel::route('/create'),
            'edit' => EditParcel::route('/{record}/edit'),
        ];
    }
}
