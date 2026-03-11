<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CraftingRecipeResource\Pages;
use App\Models\CraftingRecipe;
use App\Support\AdminOptions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Forms\Set;

class CraftingRecipeResource extends Resource
{
    protected static ?string $model = CraftingRecipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = '打造配方';

    protected static ?string $modelLabel = '打造配方';

    protected static ?string $pluralModelLabel = '打造配方';

    protected static ?string $navigationGroup = '装备成长';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('recipe_id')->label('配方 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    Select::make('recipe_type')->label('配方类型')->required()->options(AdminOptions::recipeTypeOptions()),
                    Select::make('output_type')->label('产出类型')->required()->options(AdminOptions::outputTypeOptions()),
                    TextInput::make('output_id')->label('产出 ID')->required()->maxLength(64),
                    TextInput::make('output_count')->label('产出数量')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(1),
                ])
                ->columns(3),
            Section::make('材料与费用')
                ->description('实际打造成本统一在这里维护。每条材料都必须明确物品、数量、材料类别与排序。')
                ->collapsible()
                ->schema([
                    Repeater::make('cost_items')
                        ->label('材料消耗')
                        ->default([])
                        ->helperText('紧凑模式下默认展示物品与数量；拖拽可调整顺序。材料类别保留为隐藏值，默认按辅材处理。')
                        ->itemLabel(function (array $state): ?string {
                            $itemName = AdminOptions::itemName((string) ($state['item_id'] ?? ''));
                            $count = max(1, (int) ($state['count'] ?? 1));

                            if ($itemName === '') {
                                return null;
                            }

                            return sprintf('%s × %d', $itemName, $count);
                        })
                        ->extraAttributes(['class' => 'compact-cost-items'])
                        ->schema([
                            Select::make('item_id')
                                ->hiddenLabel()
                                ->options(fn (): array => AdminOptions::itemOptions())
                                ->searchable()
                                ->preload()
                                ->placeholder('选择物品')
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('item_name', AdminOptions::itemName($state));
                                })
                                ->columnSpan(10),
                            TextInput::make('count')
                                ->hiddenLabel()
                                ->integer()
                                ->minValue(1)
                                ->required()
                                ->default(1)
                                ->placeholder('数量')
                                ->columnSpan(2),
                            Hidden::make('material_type')
                                ->default('sub')
                                ->dehydrateStateUsing(fn (?string $state): string => blank($state) ? 'sub' : $state),
                            Hidden::make('item_name')
                                ->dehydrateStateUsing(fn (Get $get): string => AdminOptions::itemName((string) $get('item_id'))),
                        ])
                        ->columns(12)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(true)
                        ->addActionLabel('新增材料')
                        ->columnSpanFull(),
                    TextInput::make('cost_gold')->label('金币消耗')->integer()->minValue(0)->required()->default(0),
                    TextInput::make('cost_currency')->label('额外货币')->maxLength(32)->helperText('没有则留空。'),
                ])
                ->columns(2),
            Section::make('说明与状态')
                ->schema([
                    Textarea::make('notes')->label('备注说明')->rows(3)->columnSpanFull(),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipe_id')->label('配方 ID')->searchable()->sortable(),
                TextColumn::make('recipe_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::recipeTypeOptions(), $state))->sortable(),
                TextColumn::make('output_type')->label('产出类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::outputTypeOptions(), $state))->sortable(),
                TextColumn::make('output_id')->label('产出 ID')->searchable(),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('cost_gold')->label('金币')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('recipe_type')->label('配方类型')->options(AdminOptions::recipeTypeOptions()),
                Tables\Filters\SelectFilter::make('output_type')->label('产出类型')->options(AdminOptions::outputTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
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
}
