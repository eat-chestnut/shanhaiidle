<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyDungeonLevelResource\Pages;
use App\Models\DailyDungeonLevel;
use App\Support\AdminOptions;
use App\Support\DailyDungeonSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DailyDungeonLevelResource extends Resource
{
    protected static ?string $model = DailyDungeonLevel::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '日常副本等级';

    protected static ?string $modelLabel = '日常副本等级';

    protected static ?string $pluralModelLabel = '日常副本等级';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('dungeon_level_id')->label('副本等级 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('dungeon_id')->label('副本')->options(fn (): array => AdminOptions::dailyDungeonOptions())->searchable()->preload()->required(),
                    Select::make('level_no')->label('等级序号')->options(DailyDungeonSupport::levelNoOptions())->required()->live(),
                    TextInput::make('level_name')->label('等级名')->required()->maxLength(64),
                    TextInput::make('recommended_level')->label('建议等级')->integer()->minValue(1),
                    TextInput::make('recommended_power')->label('建议战力')->integer()->minValue(0),
                    Toggle::make('is_max_level')->label('最高级')->helperText('按 level_no 自动矫正：Lv5=true，其余=false。'),
                    Textarea::make('summary')->label('摘要')->rows(3)->columnSpanFull(),
                    TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                ])
                ->columns(4),
            Section::make('普通怪列表')
                ->description('仅可选择 normal 怪物。')
                ->schema([
                    static::monsterEntriesRepeater('normal_monsters', 'normal'),
                ]),
            Section::make('精英怪列表')
                ->description('仅可选择 elite 怪物。')
                ->schema([
                    static::monsterEntriesRepeater('elite_monsters', 'elite'),
                ]),
            Section::make('Boss 列表')
                ->description('仅可选择 boss 怪物。')
                ->schema([
                    static::monsterEntriesRepeater('boss_monsters', 'boss'),
                ]),
            Section::make('升级消耗')
                ->description('`dungeon_level_id` 表示当前等级，`target_level_no` 表示升级目标等级。Lv5 不再配置升级消耗。')
                ->schema([
                    Repeater::make('upgrade_costs')
                        ->label('升级消耗条目')
                        ->default([])
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : '升级材料')
                        ->addActionLabel('新增升级消耗')
                        ->schema([
                            Select::make('target_level_no')
                                ->label('目标等级')
                                ->options(fn (Get $get): array => DailyDungeonSupport::upgradeTargetOptions((int) $get('../../level_no')))
                                ->required()
                                ->columnSpan(3),
                            Select::make('item_id')
                                ->label('材料')
                                ->options(fn (): array => AdminOptions::itemOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(4),
                            TextInput::make('count')->label('数量')->required()->integer()->minValue(1)->default(1)->columnSpan(2),
                            Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(2),
                            Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                        ])
                        ->columns(12)
                        ->columnSpanFull(),
                ]),
            Section::make('首通奖励')
                ->description('首通奖励直接挂在副本等级上，允许多条 item 记录。')
                ->schema([
                    Repeater::make('first_clear_rewards')
                        ->label('首通奖励条目')
                        ->default([])
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : '奖励')
                        ->addActionLabel('新增首通奖励')
                        ->schema([
                            Select::make('item_id')->label('奖励物品')->options(fn (): array => AdminOptions::itemOptions())->searchable()->preload()->required()->columnSpan(6),
                            TextInput::make('count')->label('数量')->required()->integer()->minValue(1)->default(1)->columnSpan(2),
                            Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(2),
                            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(2),
                            Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                        ])
                        ->columns(12)
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dungeon_level_id')->label('副本等级 ID')->searchable()->sortable(),
                TextColumn::make('dungeon.display_name')->label('副本')->sortable()->searchable(),
                TextColumn::make('level_no')->label('等级')->sortable(),
                TextColumn::make('level_name')->label('等级名')->searchable(),
                TextColumn::make('recommended_level')->label('建议等级')->sortable(),
                TextColumn::make('recommended_power')->label('建议战力')->sortable(),
                IconColumn::make('is_max_level')->label('最高级')->boolean(),
                TextColumn::make('monster_summary')->label('怪物配置')->state(fn (DailyDungeonLevel $record): string => DailyDungeonSupport::monsterSummary($record))->wrap(),
                TextColumn::make('upgrade_summary')->label('升级消耗')->state(fn (DailyDungeonLevel $record): string => DailyDungeonSupport::upgradeSummary($record))->wrap(),
                TextColumn::make('reward_summary')->label('首通奖励')->state(fn (DailyDungeonLevel $record): string => DailyDungeonSupport::rewardSummary($record))->wrap(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dungeon_id')->label('副本')->options(fn (): array => AdminOptions::dailyDungeonOptions()),
                Tables\Filters\SelectFilter::make('level_no')->label('等级')->options(DailyDungeonSupport::levelNoOptions()),
                Tables\Filters\TernaryFilter::make('is_max_level')->label('最高级'),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyDungeonLevels::route('/'),
            'edit' => Pages\EditDailyDungeonLevel::route('/{record}/edit'),
        ];
    }

    public static function monsterEntriesForForm(DailyDungeonLevel $level, string $spawnType): array
    {
        return DailyDungeonSupport::monsterEntriesForForm($level, $spawnType);
    }

    public static function normalizeMonsterEntriesOrFail(mixed $raw, string $spawnType): array
    {
        return DailyDungeonSupport::normalizeMonsterEntriesOrFail($raw, $spawnType);
    }

    public static function upgradeCostsForForm(DailyDungeonLevel $level): array
    {
        return DailyDungeonSupport::upgradeCostsForForm($level);
    }

    public static function normalizeUpgradeCostsOrFail(mixed $raw, int $currentLevelNo): array
    {
        return DailyDungeonSupport::normalizeUpgradeCostsOrFail($raw, $currentLevelNo);
    }

    public static function firstClearRewardsForForm(DailyDungeonLevel $level): array
    {
        return DailyDungeonSupport::firstClearRewardsForForm($level);
    }

    public static function normalizeFirstClearRewardsOrFail(mixed $raw): array
    {
        return DailyDungeonSupport::normalizeFirstClearRewardsOrFail($raw);
    }

    public static function syncRelations(
        DailyDungeonLevel $level,
        array $normalMonsters,
        array $eliteMonsters,
        array $bossMonsters,
        array $upgradeCosts,
        array $firstClearRewards,
    ): void {
        DailyDungeonSupport::syncLevelRelations($level, $normalMonsters, $eliteMonsters, $bossMonsters, $upgradeCosts, $firstClearRewards);
    }

    private static function monsterEntriesRepeater(string $name, string $spawnType): Repeater
    {
        return Repeater::make($name)
            ->label('怪物列表')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['monster_id'] ?? null) ? AdminOptions::monsterName((string) $state['monster_id']) : '怪物')
            ->addActionLabel('新增怪物')
            ->schema([
                Select::make('monster_id')
                    ->label('怪物')
                    ->options(fn (): array => AdminOptions::monsterOptions($spawnType))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                TextInput::make('weight')->label('权重')->required()->integer()->minValue(1)->default(100)->columnSpan(2),
                TextInput::make('min_count')->label('最小数量')->required()->integer()->minValue(1)->default(1)->columnSpan(2),
                TextInput::make('max_count')->label('最大数量')->required()->integer()->minValue(1)->default(1)->columnSpan(2),
                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(1),
                Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
