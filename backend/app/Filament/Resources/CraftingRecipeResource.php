<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CraftingRecipeResource\Pages;
use App\Models\CraftingRecipe;
use App\Models\Item;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CraftingRecipeResource extends Resource
{
    protected static ?string $model = CraftingRecipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = '打造配方';

    protected static ?string $modelLabel = '打造配方';

    protected static ?string $pluralModelLabel = '打造配方';

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('recipe_id')->label('配方ID')->required()->maxLength(64)->unique(ignoreRecord: true),
            Select::make('recipe_type')->label('配方类型')->required()->options([
                'craft' => '打造',
                'exchange' => '兑换',
                'compose' => '合成',
            ]),
            Select::make('output_type')->label('产出类型')->required()->options([
                'equip' => '装备',
                'material' => '材料',
                'gem' => '宝石',
            ]),
            TextInput::make('output_id')->label('产出ID')->required()->maxLength(64),
            TextInput::make('output_count')->label('产出数量')->integer()->minValue(1)->required()->default(1),
            TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(1),
            Repeater::make('cost_items')
                ->label('材料消耗')
                ->default([])
                ->schema([
                    Select::make('item_id')
                        ->label('物品')
                        ->options(fn (): array => static::itemOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('count')
                        ->label('数量')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                ])
                ->columns(2)
                ->collapsible(),
            TextInput::make('cost_gold')->label('金币消耗')->integer()->minValue(0)->required()->default(0),
            TextInput::make('cost_currency')->label('额外货币')->maxLength(32),
            Textarea::make('notes')->label('备注')->rows(3)->columnSpanFull(),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipe_id')->label('配方ID')->searchable()->sortable(),
                TextColumn::make('recipe_type')->label('类型')->sortable(),
                TextColumn::make('output_type')->label('产出类型')->sortable(),
                TextColumn::make('output_id')->label('产出ID')->searchable(),
                TextColumn::make('output_count')->label('数量')->sortable(),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('cost_gold')->label('金币')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCraftingRecipes::route('/'),
            'create' => Pages\CreateCraftingRecipe::route('/create'),
            'edit' => Pages\EditCraftingRecipe::route('/{record}/edit'),
        ];
    }

    private static function itemOptions(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
