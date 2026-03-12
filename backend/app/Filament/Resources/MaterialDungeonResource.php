<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialDungeonResource\Pages;
use App\Models\MaterialDungeon;
use App\Support\AdminOptions;
use App\Support\MaterialDungeonSupport;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MaterialDungeonResource extends Resource
{
    protected static ?string $model = MaterialDungeon::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = '材料副本';

    protected static ?string $modelLabel = '材料副本';

    protected static ?string $pluralModelLabel = '材料副本';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('dungeon_id')->label('副本 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                    Select::make('dungeon_type')->label('副本类型')->required()->options(AdminOptions::dungeonTypeOptions()),
                    TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(1),
                    Select::make('unlock_stage_id')
                        ->label('主线通关要求')
                        ->options(fn (): array => AdminOptions::stageOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('为空时只按开放等级解锁；填写后需通关对应主线关卡。'),
                    TextInput::make('stamina_cost')->label('体力消耗')->integer()->minValue(0)->required()->default(10),
                    TextInput::make('daily_limit')->label('每日次数')->integer()->minValue(0)->required()->default(10),
                ])
                ->columns(3),
            Section::make('副本等级与升级')
                ->description('副本升级只提高产出，不提高战斗强度。1级为初始状态，从2级开始填写升级消耗。')
                ->schema([
                    Repeater::make('level_configs')
                        ->label('副本等级')
                        ->schema([
                            Section::make('等级基础')
                                ->schema([
                                    TextInput::make('level')
                                        ->label('副本等级')
                                        ->integer()
                                        ->minValue(1)
                                        ->required(),
                                    TextInput::make('reward_multiplier')
                                        ->label('奖励倍率')
                                        ->numeric()
                                        ->minValue(1)
                                        ->step(0.1)
                                        ->required()
                                        ->default(1),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                            Section::make('升级消耗')
                                ->schema([
                                    Repeater::make('upgrade_costs')
                                        ->label('升级消耗')
                                        ->schema([
                                            Select::make('item_id')
                                                ->label('材料')
                                                ->options(fn (): array => AdminOptions::itemOptions())
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                            TextInput::make('count')
                                                ->label('数量')
                                                ->integer()
                                                ->minValue(1)
                                                ->required()
                                                ->default(1),
                                        ])
                                        ->columns(2)
                                        ->defaultItems(0)
                                        ->default([])
                                        ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? MaterialDungeonSupport::itemName((string) $state['item_id']) : null)
                                        ->collapsible()
                                        ->reorderable(false)
                                        ->reorderableWithButtons(false)
                                        ->reorderableWithDragAndDrop(false)
                                        ->addActionLabel('新增升级材料')
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->default([])
                        ->itemLabel(fn (array $state): ?string => filled($state['level'] ?? null) ? sprintf('副本等级 %s', $state['level']) : null)
                        ->collapsible()
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->addActionLabel('新增副本等级')
                        ->columnSpanFull(),
                ]),
            Section::make('展示掉落列表')
                ->description('前端副本详情页的掉落预览只读取这里。只选择具体物品，不再使用掉落池标签。')
                ->schema([
                    Repeater::make('display_rewards')
                        ->label('展示掉落')
                        ->table([
                            TableColumn::make('展示物品'),
                            TableColumn::make('品质'),
                            TableColumn::make('图标'),
                        ])
                        ->schema([
                            Select::make('item_id')
                                ->label('展示物品')
                                ->options(fn (): array => AdminOptions::itemOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpan(6),
                            Placeholder::make('rarity_preview')
                                ->label('品质')
                                ->content(fn (Get $get): string => MaterialDungeonSupport::itemRarityLabel((string) $get('item_id')))
                                ->columnSpan(3),
                            Placeholder::make('icon_preview')
                                ->label('图标')
                                ->content(fn (Get $get): string => filled(MaterialDungeonSupport::itemIcon((string) $get('item_id'))) ? '已配置图标' : '未配置')
                                ->columnSpan(3),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->default([])
                        ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? MaterialDungeonSupport::itemName((string) $state['item_id']) : null)
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->addActionLabel('新增展示物品')
                        ->columnSpanFull(),
                ]),
            Section::make('层级掉落规则')
                ->description('实际战斗结算读取这里的掉落组。层级规则与前端展示掉落解耦，不再使用“层级 + 配置值”万能字段。')
                ->schema([
                    Repeater::make('layer_rules')
                        ->label('层级规则')
                        ->table([
                            TableColumn::make('层级'),
                            TableColumn::make('掉落组'),
                            TableColumn::make('首通奖励组'),
                            TableColumn::make('推荐战力'),
                        ])
                        ->schema([
                            TextInput::make('layer')
                                ->label('层级')
                                ->integer()
                                ->minValue(1)
                                ->required()
                                ->columnSpan(2),
                            Select::make('drop_group_id')
                                ->label('掉落组')
                                ->options(fn (): array => AdminOptions::materialDungeonDropGroupOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(4),
                            Select::make('first_clear_reward_group_id')
                                ->label('首通奖励组')
                                ->options(fn (): array => AdminOptions::materialDungeonDropGroupOptions())
                                ->searchable()
                                ->preload()
                                ->columnSpan(4),
                            TextInput::make('recommended_power')
                                ->label('推荐战力')
                                ->integer()
                                ->minValue(0)
                                ->columnSpan(2),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->default([])
                        ->itemLabel(function (array $state): ?string {
                            $layer = filled($state['layer'] ?? null) ? sprintf('第%s层', $state['layer']) : '未设层级';
                            $groupName = AdminOptions::materialDungeonDropGroupName((string) ($state['drop_group_id'] ?? ''));

                            return $groupName !== '' ? sprintf('%s · %s', $layer, $groupName) : $layer;
                        })
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->addActionLabel('新增层级规则')
                        ->columnSpanFull(),
                ]),
            Section::make('说明与状态')
                ->schema([
                    Textarea::make('description')->label('描述')->rows(3)->columnSpanFull(),
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
                TextColumn::make('dungeon_id')->label('副本 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('dungeon_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::dungeonTypeOptions(), $state))->sortable(),
                TextColumn::make('unlock_stage_id')
                    ->label('主线要求')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::stageOptions(), $state))
                    ->placeholder('仅等级解锁'),
                TextColumn::make('display_rewards')
                    ->label('展示掉落')
                    ->getStateUsing(fn (MaterialDungeon $record): string => collect(is_array($record->display_rewards) ? $record->display_rewards : [])
                        ->map(fn (string $itemId): string => MaterialDungeonSupport::itemName($itemId))
                        ->filter()
                        ->take(3)
                        ->implode('、')),
                TextColumn::make('level_configs')
                    ->label('副本等级')
                    ->getStateUsing(fn (MaterialDungeon $record): string => sprintf('Lv1-%d', count(is_array($record->level_configs) ? $record->level_configs : []))),
                TextColumn::make('layer_rules')
                    ->label('层级数')
                    ->getStateUsing(fn (MaterialDungeon $record): int => count(is_array($record->layer_rules) ? $record->layer_rules : [])),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('stamina_cost')->label('体力')->sortable(),
                TextColumn::make('daily_limit')->label('每日次数')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dungeon_type')->label('副本类型')->options(AdminOptions::dungeonTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
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
            'index' => Pages\ListMaterialDungeons::route('/'),
            'create' => Pages\CreateMaterialDungeon::route('/create'),
            'edit' => Pages\EditMaterialDungeon::route('/{record}/edit'),
        ];
    }
}
