<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentSetResource\Pages;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetCraftRecipe;
use App\Models\EquipmentSetEffect;
use App\Models\EquipmentSetItem;
use App\Models\EquipmentSetRecipeCostItem;
use App\Support\AdminOptions;
use App\Support\EquipmentSetModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipmentSetResource extends Resource
{
    protected static ?string $model = EquipmentSet::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '套装配置';

    protected static ?string $pluralModelLabel = '套装配置';

    protected static ?string $modelLabel = '套装配置';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('equipment_set_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('套装基础')
                                    ->description('套装线不是 item，套装成品装备通过 items.item_id 承接。本轮只实现 20 / 40 / 50 / 60 四档打造链，不包含升星。')
                                    ->schema([
                                        TextInput::make('set_id')
                                            ->label('set_id')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(ignoreRecord: true)
                                            ->disabled(fn (?EquipmentSet $record): bool => $record !== null),
                                        TextInput::make('set_name')
                                            ->label('set_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('display_name')
                                            ->label('display_name')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('set_level')
                                            ->label('set_level')
                                            ->options(EquipmentSet::SET_LEVEL_OPTIONS)
                                            ->required()
                                            ->default(20)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                                                $level = (int) ($state ?? 0);
                                                $set('piece_total', EquipmentSet::PIECE_TOTAL_BY_LEVEL[$level] ?? null);
                                            }),
                                        TextInput::make('piece_total')
                                            ->label('piece_total')
                                            ->required()
                                            ->integer()
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(EquipmentSet::PIECE_TOTAL_BY_LEVEL[20]),
                                        Select::make('set_type')
                                            ->label('set_type')
                                            ->options(EquipmentSet::SET_TYPE_OPTIONS)
                                            ->required()
                                            ->default('combat_set'),
                                        Select::make('quality')
                                            ->label('quality')
                                            ->options(AdminOptions::qualityOptions())
                                            ->required()
                                            ->default('blue'),
                                        Select::make('rarity')
                                            ->label('rarity')
                                            ->options(AdminOptions::rarityOptions())
                                            ->required()
                                            ->default('blue'),
                                        TextInput::make('unlock_level')
                                            ->label('unlock_level')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->default(20),
                                        TextInput::make('icon')
                                            ->label('icon')
                                            ->maxLength(255),
                                        Textarea::make('summary')
                                            ->label('summary')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        TextInput::make('source_desc')
                                            ->label('source_desc')
                                            ->maxLength(255),
                                        TextInput::make('sort_order')
                                            ->label('sort_order')
                                            ->required()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0),
                                        Toggle::make('is_enabled')
                                            ->label('is_enabled')
                                            ->default(true),
                                        Textarea::make('remark')
                                            ->label('remark')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('套装成品')
                            ->schema([
                                Section::make('equipment_set_items')
                                    ->description('只维护套装成品映射。20级固定 4 件，40级固定 6 件，50 / 60 级固定 8 件。')
                                    ->schema([
                                        static::setItemsRepeater(),
                                    ]),
                            ]),
                        Tab::make('件数效果')
                            ->schema([
                                Section::make('equipment_set_effects')
                                    ->description('套装效果必须使用 effect_key / value_type / value 的结构化字段表达。')
                                    ->schema([
                                        static::effectsRepeater(),
                                    ]),
                            ]),
                        Tab::make('打造配方')
                            ->schema([
                                Section::make('equipment_set_craft_recipes')
                                    ->description('20级不需要图纸与前一档装备；40 / 50 / 60 级必须消耗前一档同部位成品和图纸，并支持附加材料。')
                                    ->schema([
                                        static::recipesRepeater(),
                                    ]),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('set_id')->label('set_id')->searchable()->sortable(),
                TextColumn::make('display_name')->label('display_name')->searchable()->sortable(),
                TextColumn::make('set_level')
                    ->label('set_level')
                    ->formatStateUsing(fn (int|string|null $state): string => AdminOptions::optionLabel(EquipmentSet::SET_LEVEL_OPTIONS, $state === null ? null : (string) $state))
                    ->sortable(),
                TextColumn::make('piece_total')->label('piece_total')->numeric()->sortable(),
                TextColumn::make('quality')
                    ->label('quality')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::qualityOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('unlock_level')->label('unlock_level')->numeric()->sortable(),
                TextColumn::make('source_desc')->label('source_desc')->searchable()->toggleable(),
                ToggleColumn::make('is_enabled')->label('is_enabled')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('set_level')->label('set_level')->options(EquipmentSet::SET_LEVEL_OPTIONS),
                Tables\Filters\SelectFilter::make('quality')->label('quality')->options(AdminOptions::qualityOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('is_enabled'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentSets::route('/'),
            'create' => Pages\CreateEquipmentSet::route('/create'),
            'edit' => Pages\EditEquipmentSet::route('/{record}/edit'),
        ];
    }

    public static function setItemsForForm(EquipmentSet $record): array
    {
        return $record->items()
            ->orderBy('sort_order')
            ->orderBy('slot_type')
            ->get()
            ->map(fn (EquipmentSetItem $row): array => [
                'item_id' => (string) $row->item_id,
                'slot_type' => (string) $row->slot_type,
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : null,
            ])
            ->all();
    }

    public static function effectsForForm(EquipmentSet $record): array
    {
        return $record->effects()
            ->orderBy('piece_count')
            ->orderBy('sort_order')
            ->orderBy('effect_key')
            ->get()
            ->map(fn (EquipmentSetEffect $row): array => [
                'piece_count' => (int) $row->piece_count,
                'effect_key' => (string) $row->effect_key,
                'value_type' => (string) $row->value_type,
                'value' => EquipmentSetModuleSupport::normalizeNumericValue((float) $row->value),
                'summary' => $row->summary !== null ? (string) $row->summary : null,
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : null,
            ])
            ->all();
    }

    public static function recipesForForm(EquipmentSet $record): array
    {
        return $record->recipes()
            ->orderBy('sort_order')
            ->orderBy('slot_type')
            ->get()
            ->map(fn (EquipmentSetCraftRecipe $row): array => [
                'recipe_id' => (string) $row->recipe_id,
                'slot_type' => (string) $row->slot_type,
                'result_item_id' => (string) $row->result_item_id,
                'required_base_item_id' => $row->required_base_item_id !== null ? (string) $row->required_base_item_id : null,
                'required_blueprint_item_id' => $row->required_blueprint_item_id !== null ? (string) $row->required_blueprint_item_id : null,
                'unlock_level' => (int) $row->unlock_level,
                'summary' => $row->summary !== null ? (string) $row->summary : null,
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : null,
                'cost_items' => $row->costItems()
                    ->orderBy('sort_order')
                    ->orderBy('item_id')
                    ->get()
                    ->map(fn (EquipmentSetRecipeCostItem $cost): array => [
                        'item_id' => (string) $cost->item_id,
                        'count' => (int) $cost->count,
                        'sort_order' => (int) $cost->sort_order,
                        'is_enabled' => (bool) $cost->is_enabled,
                        'remark' => $cost->remark !== null ? (string) $cost->remark : null,
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array{
     *     set: array<string, mixed>,
     *     items: array<int, array<string, mixed>>,
     *     effects: array<int, array<string, mixed>>,
     *     recipes: array<int, array<string, mixed>>,
     *     cost_items: array<int, array<string, mixed>>
     * }
     */
    public static function normalizeFormDataOrFail(array $data): array
    {
        return EquipmentSetModuleSupport::normalizeSingleSetFormOrFail($data);
    }

    public static function syncRelations(EquipmentSet $record, array $items, array $effects, array $recipes, array $costItems): void
    {
        $record->items()->delete();
        $record->effects()->delete();
        $record->recipes()->delete();

        foreach ($items as $row) {
            $record->items()->create($row);
        }

        foreach ($effects as $row) {
            $record->effects()->create($row);
        }

        foreach ($recipes as $row) {
            $record->recipes()->create($row);
        }

        foreach ($costItems as $row) {
            EquipmentSetRecipeCostItem::query()->create($row);
        }
    }

    private static function setItemsRepeater(): Repeater
    {
        return Repeater::make('set_items')
            ->label('套装成品')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : null)
            ->addActionLabel('新增套装成品')
            ->schema([
                Select::make('item_id')
                    ->label('item_id')
                    ->options(fn (): array => EquipmentSetModuleSupport::equipmentItemOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                Select::make('slot_type')
                    ->label('slot_type')
                    ->options(EquipmentSet::SLOT_TYPE_OPTIONS)
                    ->required()
                    ->columnSpan(3),
                TextInput::make('sort_order')
                    ->label('sort_order')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->columnSpan(2),
                Toggle::make('is_enabled')
                    ->label('is_enabled')
                    ->default(true)
                    ->columnSpan(1),
                Textarea::make('remark')
                    ->label('remark')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(10)
            ->columnSpanFull();
    }

    private static function effectsRepeater(): Repeater
    {
        return Repeater::make('effects')
            ->label('件数效果')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->addActionLabel('新增件数效果')
            ->schema([
                Select::make('piece_count')
                    ->label('piece_count')
                    ->options([
                        2 => '2',
                        4 => '4',
                        6 => '6',
                        8 => '8',
                    ])
                    ->required()
                    ->columnSpan(2),
                Select::make('effect_key')
                    ->label('effect_key')
                    ->options(EquipmentSet::EFFECT_KEY_OPTIONS)
                    ->searchable()
                    ->required()
                    ->columnSpan(4),
                Select::make('value_type')
                    ->label('value_type')
                    ->options(EquipmentSet::VALUE_TYPE_OPTIONS)
                    ->required()
                    ->columnSpan(2),
                TextInput::make('value')
                    ->label('value')
                    ->numeric()
                    ->required()
                    ->columnSpan(2),
                TextInput::make('sort_order')
                    ->label('sort_order')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->columnSpan(2),
                TextInput::make('summary')
                    ->label('summary')
                    ->maxLength(255)
                    ->columnSpan(10),
                Toggle::make('is_enabled')
                    ->label('is_enabled')
                    ->default(true)
                    ->columnSpan(2),
                Textarea::make('remark')
                    ->label('remark')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(10)
            ->columnSpanFull();
    }

    private static function recipesRepeater(): Repeater
    {
        return Repeater::make('recipes')
            ->label('打造配方')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['recipe_id'] ?? null) ? (string) $state['recipe_id'] : null)
            ->addActionLabel('新增打造配方')
            ->schema([
                TextInput::make('recipe_id')
                    ->label('recipe_id')
                    ->required()
                    ->maxLength(64)
                    ->columnSpan(3),
                Select::make('slot_type')
                    ->label('slot_type')
                    ->options(EquipmentSet::SLOT_TYPE_OPTIONS)
                    ->required()
                    ->columnSpan(2),
                Select::make('result_item_id')
                    ->label('result_item_id')
                    ->options(fn (): array => EquipmentSetModuleSupport::equipmentItemOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                TextInput::make('unlock_level')
                    ->label('unlock_level')
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->columnSpan(2),
                TextInput::make('sort_order')
                    ->label('sort_order')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->columnSpan(1),
                Select::make('required_base_item_id')
                    ->label('required_base_item_id')
                    ->options(fn (): array => EquipmentSetModuleSupport::equipmentItemOptions())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->columnSpan(4),
                Select::make('required_blueprint_item_id')
                    ->label('required_blueprint_item_id')
                    ->options(fn (): array => EquipmentSetModuleSupport::blueprintItemOptions())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->columnSpan(4),
                Toggle::make('is_enabled')
                    ->label('is_enabled')
                    ->default(true)
                    ->columnSpan(2),
                TextInput::make('summary')
                    ->label('summary')
                    ->maxLength(255)
                    ->columnSpan(10),
                Textarea::make('remark')
                    ->label('remark')
                    ->rows(2)
                    ->columnSpanFull(),
                static::recipeCostItemsRepeater(),
            ])
            ->columns(12)
            ->columnSpanFull();
    }

    private static function recipeCostItemsRepeater(): Repeater
    {
        return Repeater::make('cost_items')
            ->label('equipment_set_recipe_cost_items')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : null)
            ->addActionLabel('新增附加材料')
            ->extraAttributes(['class' => 'compact-cost-items'])
            ->schema([
                Select::make('item_id')
                    ->label('item_id')
                    ->options(fn (): array => EquipmentSetModuleSupport::costMaterialOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(5),
                TextInput::make('count')
                    ->label('count')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->default(1)
                    ->columnSpan(2),
                TextInput::make('sort_order')
                    ->label('sort_order')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->columnSpan(2),
                Toggle::make('is_enabled')
                    ->label('is_enabled')
                    ->default(true)
                    ->columnSpan(1),
                TextInput::make('remark')
                    ->label('remark')
                    ->maxLength(255)
                    ->columnSpan(2),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
