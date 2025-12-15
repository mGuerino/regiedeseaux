<?php

namespace App\Filament\Resources\Roads;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Roads\Pages\CreateRoad;
use App\Filament\Resources\Roads\Pages\EditRoad;
use App\Filament\Resources\Roads\Pages\ListRoads;
use App\Filament\Resources\Roads\Schemas\RoadForm;
use App\Filament\Resources\Roads\Tables\RoadsTable;
use App\Models\Road;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RoadResource extends Resource
{
    protected static ?string $model = Road::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Rues';

    protected static ?string $modelLabel = 'rue';

    protected static ?string $pluralModelLabel = 'rues';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Referentiels;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return RoadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoadsTable::configure($table);
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
            'index' => ListRoads::route('/'),
            'create' => CreateRoad::route('/create'),
            'edit' => EditRoad::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
