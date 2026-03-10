<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentSetResource\Pages;
use App\Models\EquipmentSet;
use App\Models\SkillCatalog;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
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
use Illuminate\Validation\ValidationException;

class EquipmentSetResource extends Resource
{
    protected static ?string $model = EquipmentSet::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '套装配置';

    protected static ?string $pluralModelLabel = '套装';

    protected static ?string $modelLabel = '套装';

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('EquipmentSetTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('套装ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?EquipmentSet $record): bool => $record !== null),
                                TextInput::make('set_line_id')
                                    ->label('套装线ID')
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('sect')
                                    ->label('宗门')
                                    ->maxLength(64),
                                TextInput::make('flow_tag')
                                    ->label('流派标签')
                                    ->maxLength(64),
                                Select::make('stage')
                                    ->label('阶段')
                                    ->required()
                                    ->options([
                                        20 => '20级套装',
                                        40 => '40级套装',
                                        60 => '60级套装',
                                    ])
                                    ->live(),
                                TextInput::make('piece_count')
                                    ->label('套装总件数')
                                    ->integer()
                                    ->required()
                                    ->default(4)
                                    ->minValue(2)
                                    ->maxValue(8),
                                TextInput::make('max_pieces')
                                    ->label('兼容总件数')
                                    ->integer()
                                    ->required()
                                    ->default(4)
                                    ->minValue(2)
                                    ->maxValue(10)
                                    ->helperText('旧字段兼容，建议与套装总件数保持一致'),
                                TagsInput::make('slot_ids')
                                    ->label('套装位')
                                    ->placeholder('输入后回车')
                                    ->helperText('建议固定 8 个：main_weapon/off_weapon/armor/belt/shoes/gloves/helm/necklace'),
                                Textarea::make('description')
                                    ->label('说明')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Toggle::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                                TextInput::make('sort_order')
                                    ->label('排序')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                            ])->columns(2),
                        Tab::make('阈值与加成')
                            ->schema([
                                Repeater::make('thresholds')
                                    ->label('阈值列表')
                                    ->default([])
                                    ->minItems(1)
                                    ->reorderableWithButtons()
                                    ->schema([
                                        TextInput::make('count')
                                            ->label('阈值件数')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(fn (Get $get): int => max(1, (int) $get('../../../piece_count'))),
                                        Repeater::make('bonuses')
                                            ->label('加成')
                                            ->default([])
                                            ->minItems(1)
                                            ->reorderableWithButtons()
                                            ->schema([
                                                Select::make('type')
                                                    ->label('加成类型')
                                                    ->required()
                                                    ->options(static::bonusTypeOptions()),
                                                Select::make('stat')
                                                    ->label('属性')
                                                    ->options(static::statOptions())
                                                    ->required(fn (Get $get): bool => $get('type') === 'stat')
                                                    ->hidden(fn (Get $get): bool => $get('type') !== 'stat')
                                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'stat'),
                                                Select::make('skill_id')
                                                    ->label('技能')
                                                    ->options(fn (): array => static::skillOptions())
                                                    ->searchable()
                                                    ->required(fn (Get $get): bool => $get('type') === 'skill_level')
                                                    ->hidden(fn (Get $get): bool => $get('type') !== 'skill_level')
                                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'skill_level'),
                                                TextInput::make('val')
                                                    ->label('数值')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                            ])
                                            ->columns(2)
                                            ->collapsible(),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('套装ID')->searchable()->sortable(),
                TextColumn::make('set_line_id')->label('套装线')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('stage')->label('阶段')->numeric()->sortable(),
                TextColumn::make('piece_count')->label('件数')->numeric()->sortable(),
                TextColumn::make('max_pieces')->label('兼容件数')->numeric()->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')
                    ->label('阶段')
                    ->options([
                        20 => '20级',
                        40 => '40级',
                        60 => '60级',
                    ]),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentSets::route('/'),
            'create' => Pages\CreateEquipmentSet::route('/create'),
            'edit' => Pages\EditEquipmentSet::route('/{record}/edit'),
        ];
    }

    public static function normalizeThresholdsInput(mixed $raw): array
    {
        $rows = [];
        if (! is_array($raw)) {
            return $rows;
        }

        foreach ($raw as $thresholdRaw) {
            if (! is_array($thresholdRaw)) {
                continue;
            }
            $count = (int) ($thresholdRaw['count'] ?? 0);
            $bonuses = [];
            $bonusRows = $thresholdRaw['bonuses'] ?? [];
            if (is_array($bonusRows)) {
                foreach ($bonusRows as $bonusRaw) {
                    if (! is_array($bonusRaw)) {
                        continue;
                    }
                    $type = trim((string) ($bonusRaw['type'] ?? ''));
                    $val = max(0, (int) ($bonusRaw['val'] ?? 0));
                    if ($type === 'stat') {
                        $stat = trim((string) ($bonusRaw['stat'] ?? ''));
                        if ($stat === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'stat',
                            'stat' => $stat,
                            'val' => $val,
                        ];
                    } elseif ($type === 'skill_level') {
                        $skillId = trim((string) ($bonusRaw['skill_id'] ?? ''));
                        if ($skillId === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'skill_level',
                            'skill_id' => $skillId,
                            'val' => $val,
                        ];
                    }
                }
            }

            $rows[] = [
                'count' => $count,
                'bonuses' => array_values($bonuses),
            ];
        }

        return array_values($rows);
    }

    public static function validateThresholdsOrFail(array $thresholds, int $maxPieces, ?int $stage = null): void
    {
        if ($thresholds === []) {
            throw ValidationException::withMessages([
                'thresholds' => '阈值至少需要 1 行。',
            ]);
        }

        if ($stage !== null) {
            $expectedPieces = match ($stage) {
                20 => 4,
                40 => 6,
                60 => 8,
                default => 0,
            };
            if ($expectedPieces > 0 && $maxPieces !== $expectedPieces) {
                throw ValidationException::withMessages([
                    'piece_count' => sprintf('%d级套装总件数必须为 %d。', $stage, $expectedPieces),
                ]);
            }
        }

        $statOptions = static::statOptions();
        $seenCounts = [];
        $lastCount = 0;

        foreach ($thresholds as $idx => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}" => '阈值行格式错误。',
                ]);
            }

            $count = (int) ($row['count'] ?? 0);
            if ($count < 1 || $count > $maxPieces) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => "阈值件数需在 1~{$maxPieces}。",
                ]);
            }
            if (in_array($count, $seenCounts, true)) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => '阈值件数不能重复。',
                ]);
            }
            if ($count <= $lastCount) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => '阈值件数必须按递增顺序填写。',
                ]);
            }
            $seenCounts[] = $count;
            $lastCount = $count;

            $bonuses = $row['bonuses'] ?? [];
            if (! is_array($bonuses) || count($bonuses) < 1) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.bonuses" => '每个阈值至少配置 1 条加成。',
                ]);
            }

            foreach ($bonuses as $bIdx => $bonus) {
                if (! is_array($bonus)) {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bIdx}" => '加成格式错误。',
                    ]);
                }
                $type = (string) ($bonus['type'] ?? '');
                $val = (int) ($bonus['val'] ?? -1);
                if (! in_array($type, ['stat', 'skill_level'], true)) {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bIdx}.type" => '加成类型必须是 stat 或 skill_level。',
                    ]);
                }
                if ($val < 0) {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bIdx}.val" => '数值不能为负数。',
                    ]);
                }

                if ($type === 'stat') {
                    $stat = (string) ($bonus['stat'] ?? '');
                    if ($stat === '' || ! array_key_exists($stat, $statOptions)) {
                        throw ValidationException::withMessages([
                            "thresholds.{$idx}.bonuses.{$bIdx}.stat" => '属性类型不合法。',
                        ]);
                    }
                } else {
                    $skillId = trim((string) ($bonus['skill_id'] ?? ''));
                    if ($skillId === '' || ! SkillCatalog::query()->where('id', $skillId)->exists()) {
                        throw ValidationException::withMessages([
                            "thresholds.{$idx}.bonuses.{$bIdx}.skill_id" => '技能ID不存在。',
                        ]);
                    }
                }
            }
        }

        if ($stage !== null) {
            $required = match ($stage) {
                20 => [2, 4],
                40 => [2, 4, 6],
                60 => [2, 4, 6, 8],
                default => [],
            };

            if ($required !== []) {
                foreach ($required as $must) {
                    if (! in_array($must, $seenCounts, true)) {
                        throw ValidationException::withMessages([
                            'thresholds' => sprintf('%d级套装必须包含 %s 件阈值。', $stage, implode('/', $required)),
                        ]);
                    }
                }
            }
        }
    }

    protected static function bonusTypeOptions(): array
    {
        return [
            'stat' => '属性加成',
            'skill_level' => '技能等级',
        ];
    }

    protected static function statOptions(): array
    {
        return [
            'HP' => '生命',
            'ATK' => '攻击',
            'DEF' => '防御',
            'CRIT_PERCENT' => '暴击',
            'QI' => '气',
            'LOOT_BONUS_PERCENT' => '掉落',
            'WD' => '物伤',
            'SP' => '术伤',
        ];
    }

    protected static function skillOptions(): array
    {
        return SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
