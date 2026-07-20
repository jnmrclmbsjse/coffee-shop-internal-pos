<?php

namespace App\Filament\Resources\StockItems\Schemas;

use App\Enums\StockCountMethod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(160),
                TextInput::make('unit')
                    ->required()
                    ->default('pcs')
                    ->maxLength(32)
                    ->helperText('e.g. pcs / ml / bottle / pack'),
                TextInput::make('size')
                    ->maxLength(32)
                    ->helperText('Set for cups/lids (S/M/L).'),
                Toggle::make('is_reconciled')
                    ->label('Reconciled (cup/lid)')
                    ->helperText('Cups & lids only. Forces counting by quantity.')
                    ->live()
                    ->afterStateUpdated(function (bool $state, Set $set): void {
                        if ($state) {
                            $set('count_method', StockCountMethod::Quantity->value);
                        }
                    }),
                // chk_reconciled_is_quantity: reconciled items must count by quantity.
                // Lock the field to Quantity when reconciled, but keep it dehydrated so
                // the forced value still saves (disabled fields are not saved by default).
                Select::make('count_method')
                    ->options(StockCountMethod::class)
                    ->required()
                    ->default(StockCountMethod::Quantity)
                    ->disabled(fn (Get $get): bool => (bool) $get('is_reconciled'))
                    ->dehydrated(),
                Toggle::make('is_critical')
                    ->label('Critical (opening sheet)')
                    ->helperText('Shows on the short opening count sheet.'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
