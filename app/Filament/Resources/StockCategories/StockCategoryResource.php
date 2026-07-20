<?php

namespace App\Filament\Resources\StockCategories;

use App\Filament\Resources\StockCategories\Pages\ManageStockCategories;
use App\Models\StockCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class StockCategoryResource extends Resource
{
    protected static ?string $model = StockCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->helperText('Reconciled cup/lid items are matched by a category name containing "cup" or "lid".'),
                TextInput::make('sort_weight')
                    ->integer()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sort_weight')
                    ->sortable(),
                TextColumn::make('stock_items_count')
                    ->counts('stockItems')
                    ->label('Items'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('sort_weight')
            ->reorderable('sort_weight')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockCategories::route('/'),
        ];
    }
}
