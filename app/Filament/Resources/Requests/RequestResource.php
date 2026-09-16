<?php

namespace App\Filament\Resources\Requests;

use App\Filament\Resources\Requests\Pages\CreateRequest;
use App\Filament\Resources\Requests\Pages\EditRequest;
use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ValidateRequest;
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
use Illuminate\Support\Facades\Auth;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Demandes';

    protected static ?string $modelLabel = 'demande';

    protected static ?string $pluralModelLabel = 'demandes';

    protected static ?int $navigationSort = 1;

    /**
     * Pour un superviseur, le badge compte les attestations qui attendent son
     * intervention : c'est la seule chose qu'il puisse traiter depuis le menu.
     * Les autres utilisateurs conservent le décompte des demandes en cours.
     */
    public static function getNavigationBadge(): ?string
    {
        $awaitingValidation = self::awaitingValidationCount();

        if ($awaitingValidation > 0) {
            return (string) $awaitingValidation;
        }

        return (string) Request::where('request_status', 1)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::awaitingValidationCount() > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $awaitingValidation = self::awaitingValidationCount();

        if ($awaitingValidation > 0) {
            return $awaitingValidation > 1
                ? "{$awaitingValidation} attestations attendent votre validation"
                : 'Une attestation attend votre validation';
        }

        return 'Demandes en cours';
    }

    /**
     * Nombre d'attestations en attente de validation, nul pour un utilisateur
     * qui n'est pas superviseur.
     */
    protected static function awaitingValidationCount(): int
    {
        if (! Auth::user()?->canValidateAttestations()) {
            return 0;
        }

        return Request::awaitingValidation()->count();
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
                'applicant:id,last_name,first_name,address,address2,postal_code,city,email,phone1,phone2',  // Pour applicant.last_name et génération Word
                'municipality:code,name,code_with_division',  // Pour municipality.name
                'contact:id,first_name,last_name',  // Pour contact.last_name

                // Relations pour les colonnes cachées par défaut
                // Chargées uniquement si visibles, mais préchargées pour éviter N+1
                // title et signature_path sont indispensables hors de la table :
                // la page de validation et les messages d'envoi interrogent
                // hasSignature(), qui répondait « non » sur une relation amputée
                // alors que la signature était bien apposée sur le document.
                'signatory:id,name,title,signature_path',
                'certifier:id,name',  // Pour certifier.name (toggleable hidden)
                'contactPerson:id,name',  // Pour contactPerson.name (toggleable hidden)
                'followedByUser:id,name,first_name',  // Pour followedByUser.name
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'create' => CreateRequest::route('/create'),
            'edit' => EditRequest::route('/{record}/edit'),
            'validation' => ValidateRequest::route('/{record}/validation'),
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
