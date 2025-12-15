<?php

namespace App\Filament\Resources\Requests;

use App\Filament\Resources\Requests\Pages\CreateRequest;
use App\Filament\Resources\Requests\Pages\EditRequest;
use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Schemas\RequestForm;
use App\Filament\Resources\Requests\Tables\RequestsTable;
use App\Models\Request;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Demandes';

    protected static ?string $modelLabel = 'demande';

    protected static ?string $pluralModelLabel = 'demandes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) Request::where('request_status', 'En cours')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return RequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Optimisation: Eager load toutes les relations utilisées dans la table
     * pour éviter le problème N+1
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                // Relations principales affichées dans les colonnes
                'parcels:ident,codcomm,objectid',  // Pour parcels_list column
                'applicant:id,last_name,first_name',  // Pour applicant.last_name
                'municipality:code,name,code_with_division',  // Pour municipality.name
                'contact:id,first_name,last_name',  // Pour contact.last_name
                
                // Relations pour les colonnes cachées par défaut
                // Chargées uniquement si visibles, mais préchargées pour éviter N+1
                'signatory:id,name',  // Pour signatory.name (toggleable hidden)
                'certifier:id,name',  // Pour certifier.name (toggleable hidden)
                'contactPerson:id,name',  // Pour contactPerson.name (toggleable hidden)
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'create' => CreateRequest::route('/create'),
            'edit' => EditRequest::route('/{record}/edit'),
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
