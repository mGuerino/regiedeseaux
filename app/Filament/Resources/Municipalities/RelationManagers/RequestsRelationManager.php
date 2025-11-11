<?php

namespace App\Filament\Resources\Municipalities\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'requests';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('applicant_id')
                    ->relationship('applicant', 'id')
                    ->required(),
                TextInput::make('contact'),
                TextInput::make('reference'),
                DatePicker::make('request_date')
                    ->required(),
                DatePicker::make('response_date'),
                Toggle::make('request_status')
                    ->required(),
                Toggle::make('water_status'),
                Toggle::make('wastewater_status'),
                TextInput::make('observations'),
                Select::make('signatory_id')
                    ->relationship('signatory', 'name'),
                TextInput::make('map_url')
                    ->url(),
                Select::make('certifier_id')
                    ->relationship('certifier', 'name'),
                Select::make('contact_person_id')
                    ->relationship('contactPerson', 'name'),
                TextInput::make('created_by'),
                DatePicker::make('created_date'),
                TextInput::make('updated_by'),
                DatePicker::make('updated_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('applicant.id')
                    ->searchable(),
                TextColumn::make('contact')
                    ->searchable(),
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('response_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('request_status')
                    ->boolean(),
                IconColumn::make('water_status')
                    ->boolean(),
                IconColumn::make('wastewater_status')
                    ->boolean(),
                TextColumn::make('observations')
                    ->searchable(),
                TextColumn::make('signatory.name')
                    ->searchable(),
                TextColumn::make('map_url')
                    ->searchable(),
                TextColumn::make('certifier.name')
                    ->searchable(),
                TextColumn::make('contactPerson.name')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('created_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('updated_by')
                    ->searchable(),
                TextColumn::make('updated_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
