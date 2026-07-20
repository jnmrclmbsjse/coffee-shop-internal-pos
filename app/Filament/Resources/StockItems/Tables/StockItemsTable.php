<?php

namespace App\Filament\Resources\StockItems\Tables;

use App\Enums\StockCountMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->placeholder('—'),
                TextColumn::make('unit'),
                TextColumn::make('count_method')
                    ->badge(),
                IconColumn::make('is_reconciled')
                    ->label('Reconciled')
                    ->boolean(),
                IconColumn::make('is_critical')
                    ->label('Critical')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                SelectFilter::make('count_method')
                    ->options(StockCountMethod::class),
                TernaryFilter::make('is_reconciled'),
                TernaryFilter::make('is_critical'),
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
