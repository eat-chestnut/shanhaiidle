<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StageResource\Pages;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Stage;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StageResource extends Resource
{
    protected static ?string $model = Stage::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = '地图关卡';

    protected static ?string $pluralModelLabel = '关卡';

    protected static ?string $modelLabel = '关卡';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('StageTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('关卡ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?Stage $record): bool => $record !== null),
                                TextInput::make('name')
                                    ->label('关卡名')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('unlock_min_level')
                                    ->label('解锁等级')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1),
                                TextInput::make('sort_order')
                                    ->label('排序')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                                Toggle::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                            ])->columns(2),
                        Tab::make('刷怪参数')
                            ->schema([
                                TextInput::make('spawn_patch.respawn_s')
                                    ->label('刷新间隔（秒）')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.5)
                                    ->maxValue(10)
                                    ->default(1.6),
                                TextInput::make('spawn_patch.max_alive')
                                    ->label('同屏上限')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->default(10),
                                TextInput::make('spawn_patch.spawn_radius')
                                    ->label('刷怪半径')
                                    ->required()
                                    ->integer()
                                    ->minValue(50)
                                    ->maxValue(800)
                                    ->default(220),
                            ])->columns(3),
                        Tab::make('怪物配置')
                            ->schema([
                                static::monsterPoolRepeater('normal', '普通怪池', fn (): array => static::normalMonsterOptions()),
                                static::monsterPoolRepeater('elite', '精英怪池', fn (): array => static::eliteMonsterOptions()),
                                static::monsterPoolRepeater('boss', 'Boss池', fn (): array => static::bossMonsterOptions()),
                            ])->columns(1),
                        Tab::make('难度')
                            ->schema([
                                Repeater::make('difficulties')
                                    ->label('难度列表')
                                    ->default(static::defaultDifficultiesForForm())
                                    ->minItems(1)
                                    ->reorderable(false)
                                    ->addActionLabel('添加难度')
                                    ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? '难度'))
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('难度名')
                                            ->required()
                                            ->maxLength(32),
                                        TextInput::make('recommend_score')
                                            ->label('建议战力')
                                            ->required()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0),
                                        Fieldset::make('怪物倍率')
                                            ->schema([
                                                TextInput::make('monster_mult.hp')
                                                    ->label('生命倍率')
                                                    ->required()
                                                    ->numeric()
                                                    ->step(0.05)
                                                    ->minValue(0.1)
                                                    ->maxValue(10)
                                                    ->default(1.0),
                                                TextInput::make('monster_mult.atk')
                                                    ->label('攻击倍率')
                                                    ->required()
                                                    ->numeric()
                                                    ->step(0.05)
                                                    ->minValue(0.1)
                                                    ->maxValue(10)
                                                    ->default(1.0),
                                                TextInput::make('monster_mult.def')
                                                    ->label('防御倍率')
                                                    ->required()
                                                    ->numeric()
                                                    ->step(0.05)
                                                    ->minValue(0.1)
                                                    ->maxValue(10)
                                                    ->default(1.0),
                                            ])
                                            ->columns(3),
                                        Fieldset::make('解锁条件')
                                            ->schema([
                                                TextInput::make('unlock.boss_kills_required')
                                                    ->label('Boss击杀需求')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                                Repeater::make('unlock.material_cost')
                                                    ->label('材料消耗')
                                                    ->default([])
                                                    ->schema([
                                                        Select::make('item_id')
                                                            ->label('材料')
                                                            ->required()
                                                            ->searchable()
                                                            ->options(fn (): array => static::itemOptions()),
                                                        TextInput::make('count')
                                                            ->label('数量')
                                                            ->required()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->default(0),
                                                    ])
                                                    ->columns(2)
                                                    ->collapsible(),
                                                Fieldset::make('升级奖励')
                                                    ->schema([
                                                        TextInput::make('unlock.reward.gold')
                                                            ->label('金币')
                                                            ->required()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->default(0),
                                                        TextInput::make('unlock.reward.skill_points')
                                                            ->label('技能点')
                                                            ->required()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->default(0),
                                                        Repeater::make('unlock.reward.items')
                                                            ->label('物品奖励')
                                                            ->default([])
                                                            ->schema([
                                                                Select::make('item_id')
                                                                    ->label('物品')
                                                                    ->required()
                                                                    ->searchable()
                                                                    ->options(fn (): array => static::itemOptions()),
                                                                TextInput::make('count')
                                                                    ->label('数量')
                                                                    ->required()
                                                                    ->integer()
                                                                    ->minValue(0)
                                                                    ->default(0),
                                                            ])
                                                            ->columns(2)
                                                            ->collapsible(),
                                                    ])
                                                    ->columns(2),
                                            ])
                                            ->columns(1),
                                        Fieldset::make('首通奖励')
                                            ->schema([
                                                TextInput::make('first_clear_reward.gold')
                                                    ->label('金币')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                                TextInput::make('first_clear_reward.skill_points')
                                                    ->label('技能点')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                                Repeater::make('first_clear_reward.items')
                                                    ->label('物品奖励')
                                                    ->default([])
                                                    ->schema([
                                                        Select::make('item_id')
                                                            ->label('物品')
                                                            ->required()
                                                            ->searchable()
                                                            ->options(fn (): array => static::itemOptions()),
                                                        TextInput::make('count')
                                                            ->label('数量')
                                                            ->required()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->default(0),
                                                    ])
                                                    ->columns(2)
                                                    ->collapsible(),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('掉落覆盖（可选）')
                                            ->helperText('按稀有度覆盖该难度的掉落池；留空或空数组将继承地图默认池。本版不支持追加/删除差分，仅支持整池覆盖。')
                                            ->schema([
                                                TextInput::make('drops_override.drop_chance')
                                                    ->label('掉落概率覆盖')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                TextInput::make('drops_override.rarity_weights.white')
                                                    ->label('白色权重')
                                                    ->integer()
                                                    ->minValue(0),
                                                TextInput::make('drops_override.rarity_weights.blue')
                                                    ->label('蓝色权重')
                                                    ->integer()
                                                    ->minValue(0),
                                                TextInput::make('drops_override.rarity_weights.gold')
                                                    ->label('金色权重')
                                                    ->integer()
                                                    ->minValue(0),
                                                MultiSelect::make('drops_override.white_items')
                                                    ->label('白色掉落池覆盖')
                                                    ->options(fn (): array => static::itemOptions())
                                                    ->searchable()
                                                    ->default([]),
                                                MultiSelect::make('drops_override.blue_items')
                                                    ->label('蓝色掉落池覆盖')
                                                    ->options(fn (): array => static::itemOptions())
                                                    ->searchable()
                                                    ->default([]),
                                                MultiSelect::make('drops_override.gold_items')
                                                    ->label('金色掉落池覆盖')
                                                    ->options(fn (): array => static::itemOptions())
                                                    ->searchable()
                                                    ->default([]),
                                                MultiSelect::make('drops_override.purple_items')
                                                    ->label('紫色掉落池覆盖')
                                                    ->options(fn (): array => static::itemOptions())
                                                    ->searchable()
                                                    ->default([]),
                                                MultiSelect::make('drops_override.orange_items')
                                                    ->label('橙色掉落池覆盖')
                                                    ->options(fn (): array => static::itemOptions())
                                                    ->searchable()
                                                    ->default([]),
                                                TextInput::make('drops_override.special.normal.extra_gem_chance')
                                                    ->label('普通宝石概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                MultiSelect::make('drops_override.special.normal.extra_gems')
                                                    ->label('普通宝石池')
                                                    ->options(fn (): array => static::gemOptions())
                                                    ->default([]),
                                                TextInput::make('drops_override.special.elite.punch_stone_chance')
                                                    ->label('精英打孔石概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                TextInput::make('drops_override.special.elite.extra_gem_chance')
                                                    ->label('精英宝石概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                MultiSelect::make('drops_override.special.elite.extra_gems')
                                                    ->label('精英宝石池')
                                                    ->options(fn (): array => static::gemOptions())
                                                    ->default([]),
                                                Select::make('drops_override.special.boss.core_guarantee')
                                                    ->label('Boss核心保底')
                                                    ->options(fn (): array => static::gemOptions())
                                                    ->searchable(),
                                                TextInput::make('drops_override.special.boss.punch_stone_chance')
                                                    ->label('Boss打孔石概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                TextInput::make('drops_override.special.boss.extra_gem_chance')
                                                    ->label('Boss宝石概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.01),
                                                MultiSelect::make('drops_override.special.boss.extra_gems')
                                                    ->label('Boss宝石池')
                                                    ->options(fn (): array => static::gemOptions())
                                                    ->default([]),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),
                        Tab::make('普通掉落')
                            ->schema([
                                TextInput::make('drops_patch.drop_chance')
                                    ->label('基础掉落概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.28),
                                TextInput::make('drops_patch.rarity_weights.white')
                                    ->label('白色权重')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(85),
                                TextInput::make('drops_patch.rarity_weights.blue')
                                    ->label('蓝色权重')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(13),
                                TextInput::make('drops_patch.rarity_weights.gold')
                                    ->label('金色权重')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(2),
                                MultiSelect::make('drops_patch.items_by_rarity.white')
                                    ->label('白色掉落池')
                                    ->options(fn (): array => static::itemOptions())
                                    ->required()
                                    ->minItems(1),
                                MultiSelect::make('drops_patch.items_by_rarity.blue')
                                    ->label('蓝色掉落池')
                                    ->options(fn (): array => static::itemOptions())
                                    ->default([]),
                                MultiSelect::make('drops_patch.items_by_rarity.gold')
                                    ->label('金色掉落池')
                                    ->options(fn (): array => static::itemOptions())
                                    ->default([]),
                            ])->columns(2),
                        Tab::make('特殊掉落')
                            ->schema([
                                TextInput::make('drops_patch.special.normal.extra_gem_chance')
                                    ->label('普通怪额外宝石概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.02),
                                MultiSelect::make('drops_patch.special.normal.extra_gems')
                                    ->label('普通怪额外宝石池')
                                    ->options(fn (): array => static::gemOptions())
                                    ->default([]),

                                TextInput::make('drops_patch.special.elite.punch_stone_chance')
                                    ->label('精英打孔石概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.18),
                                TextInput::make('drops_patch.special.elite.extra_gem_chance')
                                    ->label('精英额外宝石概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.10),
                                MultiSelect::make('drops_patch.special.elite.extra_gems')
                                    ->label('精英额外宝石池')
                                    ->options(fn (): array => static::gemOptions())
                                    ->default([]),

                                TextInput::make('drops_patch.special.boss.punch_stone_chance')
                                    ->label('Boss打孔石概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.45),
                                TextInput::make('drops_patch.special.boss.extra_gem_chance')
                                    ->label('Boss额外宝石概率')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->default(0.25),
                                MultiSelect::make('drops_patch.special.boss.extra_gems')
                                    ->label('Boss额外宝石池')
                                    ->options(fn (): array => static::gemOptions())
                                    ->default([]),
                                Select::make('drops_patch.special.boss.core_guarantee')
                                    ->label('Boss保底核心')
                                    ->required()
                                    ->options(fn (): array => static::gemOptions())
                                    ->default('妖王核心'),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('关卡ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('关卡名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unlock_min_level')
                    ->label('解锁等级')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('elite_every_kills')
                    ->label('精英阈值')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('boss_every_kills')
                    ->label('Boss阈值')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('difficulties')
                    ->label('难度数')
                    ->formatStateUsing(fn (mixed $state): int => is_array($state) && count($state) > 0 ? count($state) : 1),
                ToggleColumn::make('is_enabled')
                    ->label('启用')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('enable')
                        ->label('批量启用')
                        ->action(fn ($records) => $records->each->update(['is_enabled' => true])),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('批量禁用')
                        ->action(fn ($records) => $records->each->update(['is_enabled' => false])),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStages::route('/'),
            'create' => Pages\CreateStage::route('/create'),
            'edit' => Pages\EditStage::route('/{record}/edit'),
        ];
    }

    protected static function itemOptions(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->whereIn('type', ['item', 'material', 'blueprint', 'blueprint_fragment', 'currency'])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'rarity'])
            ->mapWithKeys(fn (Item $item): array => [
                (string) $item->id => sprintf(
                    '%s（%s｜%s）',
                    (string) $item->name,
                    (string) $item->id,
                    (string) $item->rarity
                ),
            ])
            ->all();
    }

    protected static function gemOptions(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->where('type', 'gem')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function normalMonsterOptions(): array
    {
        return Monster::query()
            ->where('is_enabled', true)
            ->where('kind', 'normal')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function eliteMonsterOptions(): array
    {
        return Monster::query()
            ->where('is_enabled', true)
            ->where('kind', 'elite')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function bossMonsterOptions(): array
    {
        return Monster::query()
            ->where('is_enabled', true)
            ->where('kind', 'boss')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    public static function normalizeMonstersPatchInput(mixed $raw): array
    {
        $legacy = is_array($raw) ? $raw : [];
        $normalized = [
            'normal_pool' => static::normalizePoolRows($legacy['normal_pool'] ?? [], (string) ($legacy['normal'] ?? '')),
            'elite_pool' => static::normalizePoolRows($legacy['elite_pool'] ?? [], (string) ($legacy['elite'] ?? '')),
            'boss_pool' => static::normalizePoolRows($legacy['boss_pool'] ?? [], (string) ($legacy['boss'] ?? '')),
        ];

        if ($normalized['normal_pool'] === []) {
            $normalized['normal_pool'] = [['id' => 'mob_a', 'w' => 100]];
        }
        if ($normalized['elite_pool'] === []) {
            $normalized['elite_pool'] = [['id' => 'elite_a', 'w' => 100]];
        }
        if ($normalized['boss_pool'] === []) {
            $normalized['boss_pool'] = [['id' => 'boss_a', 'w' => 100]];
        }

        return $normalized;
    }

    public static function validateMonstersPatchOrFail(array $patch): void
    {
        foreach (['normal', 'elite', 'boss'] as $kind) {
            $poolKey = $kind . '_pool';
            $pool = $patch[$poolKey] ?? [];
            if (! is_array($pool) || count($pool) === 0) {
                throw ValidationException::withMessages([
                    "monsters_patch.{$poolKey}" => "怪物池 {$poolKey} 至少需要 1 条。",
                ]);
            }

            $sum = 0;
            foreach ($pool as $idx => $row) {
                if (! is_array($row)) {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}" => "怪物池 {$poolKey} 第 " . ($idx + 1) . ' 行格式错误。',
                    ]);
                }

                $monsterId = trim((string) ($row['id'] ?? ''));
                $weight = (int) ($row['w'] ?? 0);
                if ($monsterId === '') {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.id" => "怪物池 {$poolKey} 第 " . ($idx + 1) . ' 行 monster_id 不能为空。',
                    ]);
                }
                if (! Monster::query()->where('id', $monsterId)->where('kind', $kind)->exists()) {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.id" => "怪物池 {$poolKey} 第 " . ($idx + 1) . ' 行 monster_id 不存在或类型不匹配。',
                    ]);
                }
                if ($weight < 0) {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.w" => "怪物池 {$poolKey} 第 " . ($idx + 1) . ' 行权重不能为负数。',
                    ]);
                }
                $sum += $weight;
            }

            if ($sum <= 0) {
                throw ValidationException::withMessages([
                    "monsters_patch.{$poolKey}" => "怪物池 {$poolKey} 权重总和必须 > 0。",
                ]);
            }
        }
    }

    public static function defaultDifficultiesForForm(): array
    {
        $defaults = [
            [
                'name' => '普通',
                'unlock' => [
                    'boss_kills_required' => 0,
                    'material_cost' => [],
                    'reward' => ['gold' => 0, 'skill_points' => 0, 'items' => []],
                ],
                'first_clear_reward' => ['gold' => 0, 'skill_points' => 0, 'items' => []],
                'recommend_score' => 0,
                'monster_mult' => ['hp' => 1.0, 'atk' => 1.0, 'def' => 1.0],
                'drops_override' => [],
            ],
            [
                'name' => '困难I',
                'unlock' => [
                    'boss_kills_required' => 3,
                    'material_cost' => ['玉屑' => 20, '桂枝' => 20],
                    'reward' => ['gold' => 80, 'skill_points' => 1, 'items' => ['玉屑' => 10]],
                ],
                'first_clear_reward' => ['gold' => 120, 'skill_points' => 1, 'items' => ['玉屑' => 15]],
                'recommend_score' => 900,
                'monster_mult' => ['hp' => 1.4, 'atk' => 1.2, 'def' => 1.2],
                'drops_override' => [
                    'rarity_weights' => ['white' => 78, 'blue' => 19, 'gold' => 3],
                ],
            ],
            [
                'name' => '困难II',
                'unlock' => [
                    'boss_kills_required' => 10,
                    'material_cost' => ['白玉碎' => 12, '妖核' => 2],
                    'reward' => ['gold' => 150, 'skill_points' => 1, 'items' => ['白玉碎' => 8, '妖核' => 1]],
                ],
                'first_clear_reward' => ['gold' => 240, 'skill_points' => 1, 'items' => ['白玉碎' => 10, '妖核' => 1]],
                'recommend_score' => 1600,
                'monster_mult' => ['hp' => 1.8, 'atk' => 1.35, 'def' => 1.35],
                'drops_override' => [
                    'rarity_weights' => ['white' => 70, 'blue' => 24, 'gold' => 6],
                    'special' => [
                        'elite' => [
                            'punch_stone_chance' => 0.22,
                            'extra_gem_chance' => 0.12,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                        'boss' => [
                            'core_guarantee' => '妖王核心',
                            'punch_stone_chance' => 0.55,
                            'extra_gem_chance' => 0.30,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                    ],
                ],
            ],
        ];

        return array_map(
            fn (array $difficulty, int $idx): array => static::difficultyStorageToForm($difficulty, $idx),
            $defaults,
            array_keys($defaults),
        );
    }

    public static function normalizeDifficultiesForForm(mixed $raw): array
    {
        $normalized = static::normalizeDifficultiesInput($raw);

        $rows = [];
        foreach ($normalized as $idx => $difficulty) {
            if (! is_array($difficulty)) {
                continue;
            }
            $rows[] = static::difficultyStorageToForm($difficulty, (int) $idx);
        }

        return $rows;
    }

    public static function normalizeDifficultiesInput(mixed $raw): array
    {
        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = static::normalizeDifficultyRowForStorage($row, (int) $idx);
            }
        }

        if ($rows === []) {
            $rows[] = static::defaultNormalDifficultyStorage();
        }

        return array_values($rows);
    }

    public static function validateDifficultiesOrFail(array $difficulties): void
    {
        if ($difficulties === []) {
            throw ValidationException::withMessages([
                'difficulties' => '难度至少需要 1 个。',
            ]);
        }

        $itemIds = array_keys(static::itemOptions());
        $gemIds = array_keys(static::gemOptions());

        foreach ($difficulties as $idx => $difficulty) {
            if (! is_array($difficulty)) {
                throw ValidationException::withMessages([
                    "difficulties.{$idx}" => '难度格式错误。',
                ]);
            }

            $name = trim((string) ($difficulty['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    "difficulties.{$idx}.name" => '难度名不能为空。',
                ]);
            }

            $recommendScore = (int) ($difficulty['recommend_score'] ?? 0);
            if ($recommendScore < 0) {
                throw ValidationException::withMessages([
                    "difficulties.{$idx}.recommend_score" => '建议战力不能为负数。',
                ]);
            }

            $monsterMult = is_array($difficulty['monster_mult'] ?? null) ? $difficulty['monster_mult'] : [];
            foreach (['hp', 'atk', 'def'] as $field) {
                $value = (float) ($monsterMult[$field] ?? 1.0);
                if ($value < 0.1 || $value > 10) {
                    throw ValidationException::withMessages([
                        "difficulties.{$idx}.monster_mult.{$field}" => '怪物倍率需在 0.1~10 之间。',
                    ]);
                }
            }

            $unlock = is_array($difficulty['unlock'] ?? null) ? $difficulty['unlock'] : [];
            $needKills = (int) ($unlock['boss_kills_required'] ?? 0);
            if ($needKills < 0) {
                throw ValidationException::withMessages([
                    "difficulties.{$idx}.unlock.boss_kills_required" => 'Boss击杀需求不能为负数。',
                ]);
            }

            $materialCost = static::normalizeItemCountMap($unlock['material_cost'] ?? []);
            static::validateItemCountMapOrFail($materialCost, $itemIds, "difficulties.{$idx}.unlock.material_cost");

            $reward = static::normalizeRewardInput($unlock['reward'] ?? []);
            static::validateRewardOrFail($reward, $itemIds, "difficulties.{$idx}.unlock.reward");

            $firstClearReward = static::normalizeRewardInput($difficulty['first_clear_reward'] ?? []);
            static::validateRewardOrFail($firstClearReward, $itemIds, "difficulties.{$idx}.first_clear_reward");

            $drops = is_array($difficulty['drops_override'] ?? null) ? $difficulty['drops_override'] : [];
            if (array_key_exists('drop_chance', $drops)) {
                $chance = (float) $drops['drop_chance'];
                static::validateChanceOrFail($chance, "difficulties.{$idx}.drops_override.drop_chance");
            }

            if (array_key_exists('rarity_weights', $drops)) {
                $weights = is_array($drops['rarity_weights']) ? $drops['rarity_weights'] : [];
                $sum = 0;
                foreach (['white', 'blue', 'gold'] as $color) {
                    if (! array_key_exists($color, $weights)) {
                        throw ValidationException::withMessages([
                            "difficulties.{$idx}.drops_override.rarity_weights.{$color}" => 'rarity_weights 需完整提供 white/blue/gold。',
                        ]);
                    }
                    $w = (int) $weights[$color];
                    if ($w < 0) {
                        throw ValidationException::withMessages([
                            "difficulties.{$idx}.drops_override.rarity_weights.{$color}" => 'rarity_weights 不能为负数。',
                        ]);
                    }
                    $sum += $w;
                }
                if ($sum <= 0) {
                    throw ValidationException::withMessages([
                        "difficulties.{$idx}.drops_override.rarity_weights" => 'rarity_weights 总和必须 > 0。',
                    ]);
                }
            }

            $itemsByRarity = is_array($drops['items_by_rarity'] ?? null) ? $drops['items_by_rarity'] : [];
            foreach (static::dropRarityKeys() as $color) {
                $items = static::normalizeStringArray($itemsByRarity[$color] ?? []);
                foreach ($items as $itemId) {
                    if (! in_array($itemId, $itemIds, true)) {
                        throw ValidationException::withMessages([
                            "difficulties.{$idx}.drops_override.items_by_rarity.{$color}" => "物品 {$itemId} 不存在或不可用。",
                        ]);
                    }
                }
            }

            $special = is_array($drops['special'] ?? null) ? $drops['special'] : [];
            foreach (['normal', 'elite', 'boss'] as $kind) {
                $specialDef = is_array($special[$kind] ?? null) ? $special[$kind] : [];
                if (array_key_exists('extra_gem_chance', $specialDef)) {
                    static::validateChanceOrFail((float) $specialDef['extra_gem_chance'], "difficulties.{$idx}.drops_override.special.{$kind}.extra_gem_chance");
                }
                if (($kind === 'elite' || $kind === 'boss') && array_key_exists('punch_stone_chance', $specialDef)) {
                    static::validateChanceOrFail((float) $specialDef['punch_stone_chance'], "difficulties.{$idx}.drops_override.special.{$kind}.punch_stone_chance");
                }
                $extraGems = static::normalizeStringArray($specialDef['extra_gems'] ?? []);
                foreach ($extraGems as $gemId) {
                    if (! in_array($gemId, $gemIds, true)) {
                        throw ValidationException::withMessages([
                            "difficulties.{$idx}.drops_override.special.{$kind}.extra_gems" => "宝石 {$gemId} 不存在或不可用。",
                        ]);
                    }
                }
                if ($kind === 'boss') {
                    $core = trim((string) ($specialDef['core_guarantee'] ?? ''));
                    if ($core !== '' && ! in_array($core, $gemIds, true)) {
                        throw ValidationException::withMessages([
                            "difficulties.{$idx}.drops_override.special.boss.core_guarantee" => "Boss核心 {$core} 不存在或不可用。",
                        ]);
                    }
                }
            }
        }

        $firstUnlock = is_array($difficulties[0]['unlock'] ?? null) ? $difficulties[0]['unlock'] : [];
        $firstNeedKills = (int) ($firstUnlock['boss_kills_required'] ?? 0);
        if ($firstNeedKills !== 0) {
            throw ValidationException::withMessages([
                'difficulties.0.unlock.boss_kills_required' => '第1档难度（普通）的 Boss 击杀需求必须为 0。',
            ]);
        }
        $firstMaterialCost = static::normalizeItemCountMap($firstUnlock['material_cost'] ?? []);
        if ($firstMaterialCost !== []) {
            throw ValidationException::withMessages([
                'difficulties.0.unlock.material_cost' => '第1档难度（普通）材料消耗必须为空。',
            ]);
        }
    }

    protected static function normalizePoolRows(mixed $poolRaw, string $legacyId = ''): array
    {
        $rows = [];
        if (is_array($poolRaw)) {
            foreach ($poolRaw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $monsterId = trim((string) ($row['id'] ?? ''));
                if ($monsterId === '') {
                    continue;
                }
                $rows[] = [
                    'id' => $monsterId,
                    'w' => (int) ($row['w'] ?? 0),
                ];
            }
        }

        $legacyId = trim($legacyId);
        if ($rows === [] && $legacyId !== '') {
            $rows[] = ['id' => $legacyId, 'w' => 100];
        }

        return array_values($rows);
    }

    protected static function monsterPoolRepeater(string $kind, string $label, callable $optionsResolver): Repeater
    {
        return Repeater::make("monsters_patch.{$kind}_pool")
            ->label($label)
            ->default([['id' => static::defaultMonsterId($kind), 'w' => 100]])
            ->minItems(1)
            ->reorderableWithButtons()
            ->schema([
                Select::make('id')
                    ->label('怪物')
                    ->searchable()
                    ->required()
                    ->options($optionsResolver),
                TextInput::make('w')
                    ->label('权重')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(100),
            ])
            ->columns(2)
            ->collapsible();
    }

    protected static function defaultMonsterId(string $kind): string
    {
        return match ($kind) {
            'elite' => 'elite_a',
            'boss' => 'boss_a',
            default => 'mob_a',
        };
    }

    protected static function defaultNormalDifficultyStorage(): array
    {
        return [
            'name' => '普通',
            'unlock' => [
                'boss_kills_required' => 0,
                'material_cost' => [],
                'reward' => [
                    'gold' => 0,
                    'skill_points' => 0,
                    'items' => [],
                ],
            ],
            'first_clear_reward' => [
                'gold' => 0,
                'skill_points' => 0,
                'items' => [],
            ],
            'recommend_score' => 0,
            'monster_mult' => [
                'hp' => 1.0,
                'atk' => 1.0,
                'def' => 1.0,
            ],
            'drops_override' => [],
        ];
    }

    protected static function normalizeDifficultyRowForStorage(array $row, int $index): array
    {
        $unlock = is_array($row['unlock'] ?? null) ? $row['unlock'] : [];
        $monsterMult = is_array($row['monster_mult'] ?? null) ? $row['monster_mult'] : [];

        return [
            'name' => static::normalizeDifficultyName((string) ($row['name'] ?? ''), $index),
            'unlock' => [
                'boss_kills_required' => max(0, (int) ($unlock['boss_kills_required'] ?? 0)),
                'material_cost' => static::normalizeItemCountMap($unlock['material_cost'] ?? []),
                'reward' => static::normalizeRewardInput($unlock['reward'] ?? []),
            ],
            'first_clear_reward' => static::normalizeRewardInput($row['first_clear_reward'] ?? []),
            'recommend_score' => max(0, (int) ($row['recommend_score'] ?? 0)),
            'monster_mult' => [
                'hp' => static::normalizeFloat((float) ($monsterMult['hp'] ?? 1.0), 1.0),
                'atk' => static::normalizeFloat((float) ($monsterMult['atk'] ?? 1.0), 1.0),
                'def' => static::normalizeFloat((float) ($monsterMult['def'] ?? 1.0), 1.0),
            ],
            'drops_override' => static::normalizeDropsOverrideInput($row['drops_override'] ?? []),
        ];
    }

    protected static function normalizeDifficultyName(string $name, int $index): string
    {
        $name = trim($name);
        if ($name !== '') {
            return $name;
        }
        if ($index <= 0) {
            return '普通';
        }

        return '困难' . $index;
    }

    protected static function normalizeRewardInput(mixed $rewardRaw): array
    {
        $reward = is_array($rewardRaw) ? $rewardRaw : [];

        return [
            'gold' => max(0, (int) ($reward['gold'] ?? 0)),
            'skill_points' => max(0, (int) ($reward['skill_points'] ?? 0)),
            'items' => static::normalizeItemCountMap($reward['items'] ?? []),
        ];
    }

    protected static function normalizeDropsOverrideInput(mixed $dropsRaw): array
    {
        if (! is_array($dropsRaw)) {
            return [];
        }

        $out = [];

        $dropChance = static::normalizeNullableFloat($dropsRaw['drop_chance'] ?? null);
        if ($dropChance !== null) {
            $out['drop_chance'] = $dropChance;
        }

        $weightsRaw = is_array($dropsRaw['rarity_weights'] ?? null) ? $dropsRaw['rarity_weights'] : [];
        $hasWeights = false;
        $weights = [
            'white' => 0,
            'blue' => 0,
            'gold' => 0,
        ];
        foreach (['white', 'blue', 'gold'] as $color) {
            if (array_key_exists($color, $weightsRaw) && $weightsRaw[$color] !== '' && $weightsRaw[$color] !== null) {
                $weights[$color] = max(0, (int) $weightsRaw[$color]);
                $hasWeights = true;
            }
        }
        if ($hasWeights) {
            $out['rarity_weights'] = $weights;
        }

        $itemsRaw = is_array($dropsRaw['items_by_rarity'] ?? null) ? $dropsRaw['items_by_rarity'] : [];
        $whiteItems = static::normalizeStringArray($dropsRaw['white_items'] ?? ($itemsRaw['white'] ?? []));
        $blueItems = static::normalizeStringArray($dropsRaw['blue_items'] ?? ($itemsRaw['blue'] ?? []));
        $goldItems = static::normalizeStringArray($dropsRaw['gold_items'] ?? ($itemsRaw['gold'] ?? []));
        $purpleItems = static::normalizeStringArray($dropsRaw['purple_items'] ?? ($itemsRaw['purple'] ?? []));
        $orangeItems = static::normalizeStringArray($dropsRaw['orange_items'] ?? ($itemsRaw['orange'] ?? []));
        $itemsOut = [];
        if ($whiteItems !== []) {
            $itemsOut['white'] = $whiteItems;
        }
        if ($blueItems !== []) {
            $itemsOut['blue'] = $blueItems;
        }
        if ($goldItems !== []) {
            $itemsOut['gold'] = $goldItems;
        }
        if ($purpleItems !== []) {
            $itemsOut['purple'] = $purpleItems;
        }
        if ($orangeItems !== []) {
            $itemsOut['orange'] = $orangeItems;
        }
        if ($itemsOut !== []) {
            $out['items_by_rarity'] = $itemsOut;
        }

        $specialRaw = is_array($dropsRaw['special'] ?? null) ? $dropsRaw['special'] : [];
        $specialOut = [];
        foreach (['normal', 'elite', 'boss'] as $kind) {
            $kindRaw = is_array($specialRaw[$kind] ?? null) ? $specialRaw[$kind] : [];
            $kindOut = [];

            $extraGemChance = static::normalizeNullableFloat($kindRaw['extra_gem_chance'] ?? null);
            if ($extraGemChance !== null) {
                $kindOut['extra_gem_chance'] = $extraGemChance;
            }

            $extraGems = static::normalizeStringArray($kindRaw['extra_gems'] ?? []);
            if ($extraGems !== []) {
                $kindOut['extra_gems'] = $extraGems;
            }

            if ($kind === 'elite' || $kind === 'boss') {
                $punchChance = static::normalizeNullableFloat($kindRaw['punch_stone_chance'] ?? null);
                if ($punchChance !== null) {
                    $kindOut['punch_stone_chance'] = $punchChance;
                }
            }

            if ($kind === 'boss') {
                $core = trim((string) ($kindRaw['core_guarantee'] ?? ''));
                if ($core !== '') {
                    $kindOut['core_guarantee'] = $core;
                }
            }

            if ($kindOut !== []) {
                $specialOut[$kind] = $kindOut;
            }
        }
        if ($specialOut !== []) {
            $out['special'] = $specialOut;
        }

        return $out;
    }

    protected static function normalizeItemCountMap(mixed $raw): array
    {
        $map = [];
        if (! is_array($raw)) {
            return $map;
        }

        foreach ($raw as $key => $value) {
            $itemId = '';
            $count = 0;

            if (is_array($value)) {
                $itemId = trim((string) ($value['item_id'] ?? $value['id'] ?? ''));
                $count = (int) ($value['count'] ?? $value['cnt'] ?? 0);
            } elseif (is_string($key)) {
                $itemId = trim($key);
                $count = (int) $value;
            }

            if ($itemId === '' || $count <= 0) {
                continue;
            }

            $map[$itemId] = ($map[$itemId] ?? 0) + $count;
        }

        ksort($map);

        return $map;
    }

    protected static function itemCountMapToRows(mixed $raw): array
    {
        $rows = [];
        foreach (static::normalizeItemCountMap($raw) as $itemId => $count) {
            $rows[] = [
                'item_id' => (string) $itemId,
                'count' => (int) $count,
            ];
        }

        return $rows;
    }

    protected static function difficultyStorageToForm(array $difficulty, int $index = 0): array
    {
        $difficulty = static::normalizeDifficultyRowForStorage($difficulty, $index);
        $unlock = is_array($difficulty['unlock'] ?? null) ? $difficulty['unlock'] : [];
        $reward = is_array($unlock['reward'] ?? null) ? $unlock['reward'] : ['gold' => 0, 'skill_points' => 0, 'items' => []];
        $firstReward = is_array($difficulty['first_clear_reward'] ?? null) ? $difficulty['first_clear_reward'] : ['gold' => 0, 'skill_points' => 0, 'items' => []];
        $drops = is_array($difficulty['drops_override'] ?? null) ? $difficulty['drops_override'] : [];
        $special = is_array($drops['special'] ?? null) ? $drops['special'] : [];

        return [
            'name' => (string) ($difficulty['name'] ?? '普通'),
            'recommend_score' => (int) ($difficulty['recommend_score'] ?? 0),
            'monster_mult' => [
                'hp' => (float) (($difficulty['monster_mult']['hp'] ?? 1.0)),
                'atk' => (float) (($difficulty['monster_mult']['atk'] ?? 1.0)),
                'def' => (float) (($difficulty['monster_mult']['def'] ?? 1.0)),
            ],
            'unlock' => [
                'boss_kills_required' => (int) ($unlock['boss_kills_required'] ?? 0),
                'material_cost' => static::itemCountMapToRows($unlock['material_cost'] ?? []),
                'reward' => [
                    'gold' => (int) ($reward['gold'] ?? 0),
                    'skill_points' => (int) ($reward['skill_points'] ?? 0),
                    'items' => static::itemCountMapToRows($reward['items'] ?? []),
                ],
            ],
            'first_clear_reward' => [
                'gold' => (int) ($firstReward['gold'] ?? 0),
                'skill_points' => (int) ($firstReward['skill_points'] ?? 0),
                'items' => static::itemCountMapToRows($firstReward['items'] ?? []),
            ],
            'drops_override' => [
                'drop_chance' => array_key_exists('drop_chance', $drops) ? (float) $drops['drop_chance'] : null,
                'rarity_weights' => is_array($drops['rarity_weights'] ?? null) ? $drops['rarity_weights'] : [],
                'white_items' => static::normalizeStringArray(($drops['items_by_rarity']['white'] ?? [])),
                'blue_items' => static::normalizeStringArray(($drops['items_by_rarity']['blue'] ?? [])),
                'gold_items' => static::normalizeStringArray(($drops['items_by_rarity']['gold'] ?? [])),
                'purple_items' => static::normalizeStringArray(($drops['items_by_rarity']['purple'] ?? [])),
                'orange_items' => static::normalizeStringArray(($drops['items_by_rarity']['orange'] ?? [])),
                'special' => [
                    'normal' => [
                        'extra_gem_chance' => array_key_exists('extra_gem_chance', $special['normal'] ?? []) ? (float) $special['normal']['extra_gem_chance'] : null,
                        'extra_gems' => static::normalizeStringArray(($special['normal']['extra_gems'] ?? [])),
                    ],
                    'elite' => [
                        'punch_stone_chance' => array_key_exists('punch_stone_chance', $special['elite'] ?? []) ? (float) $special['elite']['punch_stone_chance'] : null,
                        'extra_gem_chance' => array_key_exists('extra_gem_chance', $special['elite'] ?? []) ? (float) $special['elite']['extra_gem_chance'] : null,
                        'extra_gems' => static::normalizeStringArray(($special['elite']['extra_gems'] ?? [])),
                    ],
                    'boss' => [
                        'core_guarantee' => (string) (($special['boss']['core_guarantee'] ?? '')),
                        'punch_stone_chance' => array_key_exists('punch_stone_chance', $special['boss'] ?? []) ? (float) $special['boss']['punch_stone_chance'] : null,
                        'extra_gem_chance' => array_key_exists('extra_gem_chance', $special['boss'] ?? []) ? (float) $special['boss']['extra_gem_chance'] : null,
                        'extra_gems' => static::normalizeStringArray(($special['boss']['extra_gems'] ?? [])),
                    ],
                ],
            ],
        ];
    }

    protected static function validateRewardOrFail(array $reward, array $validItemIds, string $path): void
    {
        if ((int) ($reward['gold'] ?? -1) < 0) {
            throw ValidationException::withMessages([
                "{$path}.gold" => '金币奖励不能为负数。',
            ]);
        }
        if ((int) ($reward['skill_points'] ?? -1) < 0) {
            throw ValidationException::withMessages([
                "{$path}.skill_points" => '技能点奖励不能为负数。',
            ]);
        }
        $items = static::normalizeItemCountMap($reward['items'] ?? []);
        static::validateItemCountMapOrFail($items, $validItemIds, "{$path}.items");
    }

    protected static function validateItemCountMapOrFail(array $itemMap, array $validItemIds, string $path): void
    {
        foreach ($itemMap as $itemId => $count) {
            if ($count < 0) {
                throw ValidationException::withMessages([
                    "{$path}.{$itemId}" => '数量不能为负数。',
                ]);
            }
            if (! in_array($itemId, $validItemIds, true)) {
                throw ValidationException::withMessages([
                    "{$path}.{$itemId}" => "物品 {$itemId} 不存在或不可用。",
                ]);
            }
        }
    }

    protected static function validateChanceOrFail(float $chance, string $path): void
    {
        if ($chance < 0 || $chance > 1) {
            throw ValidationException::withMessages([
                $path => '概率必须在 0~1 之间。',
            ]);
        }
    }

    protected static function normalizeStringArray(mixed $raw): array
    {
        $arr = [];
        if (! is_array($raw)) {
            return $arr;
        }
        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $arr[$value] = true;
        }

        return array_values(array_keys($arr));
    }

    protected static function dropRarityKeys(): array
    {
        return ['white', 'blue', 'gold', 'purple', 'orange'];
    }

    protected static function normalizeFloat(float $value, float $default = 1.0): float
    {
        if (! is_finite($value) || $value <= 0) {
            return $default;
        }

        return $value;
    }

    protected static function normalizeNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
