<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Enums\DayType;
use App\Enums\StockCountMethod;
use App\Enums\StockLevel;
use App\Models\StockItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'parLevels';

    protected static ?string $title = 'Par levels';

    /**
     * Whether the owning stock item is counted by quantity (vs. level). Drives
     * which mutually-exclusive target set the form shows (chk_par_has_target).
     */
    protected function ownerCountsByQuantity(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof StockItem
            && $owner->count_method === StockCountMethod::Quantity;
    }

    public function form(Schema $schema): Schema
    {
        $byQuantity = fn (): bool => $this->ownerCountsByQuantity();
        $byLevel = fn (): bool => ! $this->ownerCountsByQuantity();

        return $schema
            ->components([
                Select::make('day_type')
                    ->options(DayType::class)
                    ->required()
                    ->native(false),

                // Quantity target set — for count_method = quantity items.
                TextInput::make('par_qty')
                    ->numeric()
                    ->minValue(0)
                    ->required($byQuantity)
                    ->visible($byQuantity),
                TextInput::make('low_qty_threshold')
                    ->numeric()
                    ->minValue(0)
                    ->visible($byQuantity),
                TextInput::make('urgent_qty_threshold')
                    ->numeric()
                    ->minValue(0)
                    ->visible($byQuantity),

                // Level target set — for count_method = level items.
                Select::make('par_level_value')
                    ->options(StockLevel::class)
                    ->required($byLevel)
                    ->visible($byLevel)
                    ->native(false),
                Select::make('low_level_threshold')
                    ->options(StockLevel::class)
                    ->visible($byLevel)
                    ->native(false),
                Select::make('urgent_level_threshold')
                    ->options(StockLevel::class)
                    ->visible($byLevel)
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day_type')
            ->columns([
                TextColumn::make('day_type')
                    ->badge(),
                TextColumn::make('par_qty')
                    ->placeholder('—'),
                TextColumn::make('low_qty_threshold')
                    ->label('Low')
                    ->placeholder('—'),
                TextColumn::make('urgent_qty_threshold')
                    ->label('Urgent')
                    ->placeholder('—'),
                TextColumn::make('par_level_value')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('low_level_threshold')
                    ->label('Low level')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('urgent_level_threshold')
                    ->label('Urgent level')
                    ->badge()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
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
}
