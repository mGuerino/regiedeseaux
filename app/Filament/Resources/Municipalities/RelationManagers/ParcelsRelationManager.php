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
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParcelsRelationManager extends RelationManager
{
    protected static string $relationship = 'parcels';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ccocomm')
                    ->numeric(),
                TextInput::make('ccodep')
                    ->numeric(),
                TextInput::make('ccodir')
                    ->numeric(),
                TextInput::make('ccoifp')
                    ->numeric(),
                TextInput::make('ccopre'),
                TextInput::make('ccosec'),
                TextInput::make('ccovoi'),
                TextInput::make('codeident'),
                TextInput::make('cprsecr'),
                TextInput::make('dnupla'),
                TextInput::make('ident'),
                TextInput::make('parcelle')
                    ->required()
                    ->numeric(),
                TextInput::make('sect_cad'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ident')
            ->columns([
                TextColumn::make('ccocomm')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ccodep')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ccodir')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ccoifp')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ccopre')
                    ->searchable(),
                TextColumn::make('ccosec')
                    ->searchable(),
                TextColumn::make('ccovoi')
                    ->searchable(),
                TextColumn::make('codeident')
                    ->searchable(),
                TextColumn::make('cprsecr')
                    ->searchable(),
                TextColumn::make('dnupla')
                    ->searchable(),
                TextColumn::make('ident')
                    ->searchable(),
                TextColumn::make('parcelle')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sect_cad')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
