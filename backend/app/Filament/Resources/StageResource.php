<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StageResource\Pages;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Stage;
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
            ->where('type', 'item')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
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
                        "monsters_patch.{$poolKey}.{$idx}" => "怪物池 {$poolKey} 第 " . ($idx + 1) . " 行格式错误。",
                    ]);
                }

                $monsterId = trim((string) ($row['id'] ?? ''));
                $weight = (int) ($row['w'] ?? 0);
                if ($monsterId === '') {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.id" => "怪物池 {$poolKey} 第 " . ($idx + 1) . " 行 monster_id 不能为空。",
                    ]);
                }
                if (! Monster::query()->where('id', $monsterId)->where('kind', $kind)->exists()) {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.id" => "怪物池 {$poolKey} 第 " . ($idx + 1) . " 行 monster_id 不存在或类型不匹配。",
                    ]);
                }
                if ($weight < 0) {
                    throw ValidationException::withMessages([
                        "monsters_patch.{$poolKey}.{$idx}.w" => "怪物池 {$poolKey} 第 " . ($idx + 1) . " 行权重不能为负数。",
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
}
