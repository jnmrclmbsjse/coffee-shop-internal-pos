<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\StockItem;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(160),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Sizes')
                    ->description('Each size carries its own price and maps to the cup + lid it draws down.')
                    ->schema([
                        Repeater::make('sizes')
                            ->relationship()
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(32)
                                    ->helperText('S / M / L'),
                                TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('₱'),
                                Select::make('cup_stock_item_id')
                                    ->label('Cup')
                                    ->options(fn (): array => self::reconciledOptions('cup'))
                                    ->searchable(),
                                Select::make('lid_stock_item_id')
                                    ->label('Lid')
                                    ->options(fn (): array => self::reconciledOptions('lid'))
                                    ->searchable(),
                                TextInput::make('sort_weight')
                                    ->integer()
                                    ->default(0)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_weight')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Add size')
                            ->defaultItems(1),
                    ]),
            ]);
    }

    /**
     * Options for a cup/lid select: reconciled items in a matching category
     * (see StockItem::scopeReconciledInCategory + EXISTING-PATTERNS §6).
     *
     * @return array<string, string>
     */
    protected static function reconciledOptions(string $categoryKeyword): array
    {
        return StockItem::query()
            ->reconciledInCategory($categoryKeyword)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (StockItem $item): array => [
                $item->id => $item->size ? "{$item->name} ({$item->size})" : $item->name,
            ])
            ->all();
    }
}
