<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MainStageDifficultyResource\Pages;
use App\Models\MainStageDifficulty;
use App\Models\StageDifficultyMonster;
use App\Support\AdminOptions;
use App\Support\MainStageModuleSupport;
use App\Support\MonsterModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MainStageDifficultyResource extends Resource
{
    protected static ?string $model = MainStageDifficulty::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '主线难度';

    protected static ?string $modelLabel = '主线难度';

    protected static ?string $pluralModelLabel = '主线难度';

    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('难度基础')
                ->description('主线难度只维护显示名、奖励挂载和怪物列表，不再使用 monster_pool。')
                ->schema([
                    TextInput::make('difficulty_id')->label('难度 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('chapter_id')->label('所属章节')
                        ->options(fn (): array => AdminOptions::mainStageCombatChapterOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Select::make('difficulty_code')->label('难度编码')->options(MainStageModuleSupport::difficultyCodeOptions())->required(),
                    TextInput::make('difficulty_name')->label('难度名称')->required()->maxLength(64),
                    TextInput::make('drop_preview_group_id')->label('掉落预览组 ID')->required()->maxLength(160),
                    TextInput::make('first_clear_reward_group_id')->label('首通奖励组 ID')->required()->maxLength(160),
                    TextInput::make('sort_order')->label('排序')->required()->integer()->default(0)->minValue(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                ])->columns(4),
            Section::make('普通怪列表')
                ->description('仅可选择普通怪。')
                ->schema([
                    static::monsterEntriesRepeater('normal_monsters', 'normal'),
                ]),
            Section::make('精英怪列表')
                ->description('仅可选择精英怪。')
                ->schema([
                    static::monsterEntriesRepeater('elite_monsters', 'elite'),
                ]),
            Section::make('Boss列表')
                ->description('通常只配 1 条。Boss 详细机制在怪物库的 Boss 扩展中维护。')
                ->schema([
                    static::monsterEntriesRepeater('boss_monsters', 'boss'),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('difficulty_id')->label('难度 ID')->searchable()->sortable(),
                TextColumn::make('chapter.chapter_name')->label('章节')->sortable()->searchable(),
                TextColumn::make('difficulty_code')->label('难度编码')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(MainStageModuleSupport::difficultyCodeOptions(), $state))->sortable(),
                TextColumn::make('difficulty_name')->label('难度名称')->searchable(),
                TextColumn::make('monster_summary')->label('怪物配置')->state(fn (MainStageDifficulty $record): string => static::monsterSummary($record))->wrap(),
                TextColumn::make('drop_preview_group_id')->label('掉落预览组')->toggleable(),
                TextColumn::make('first_clear_reward_group_id')->label('首通奖励组')->toggleable(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('chapter_id')->label('所属章节')->options(fn (): array => AdminOptions::mainStageCombatChapterOptions()),
                Tables\Filters\SelectFilter::make('difficulty_code')->label('难度编码')->options(MainStageModuleSupport::difficultyCodeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
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
            'index' => Pages\ListMainStageDifficulties::route('/'),
            'create' => Pages\CreateMainStageDifficulty::route('/create'),
            'edit' => Pages\EditMainStageDifficulty::route('/{record}/edit'),
        ];
    }

    public static function monsterEntriesForForm(MainStageDifficulty $record, string $spawnType): array
    {
        return $record->monsterEntries()
            ->where('spawn_type', $spawnType)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (StageDifficultyMonster $entry): array => [
                'monster_id' => (string) $entry->monster_id,
                'weight' => (int) $entry->weight,
                'min_count' => (int) $entry->min_count,
                'max_count' => (int) $entry->max_count,
                'sort_order' => (int) $entry->sort_order,
                'is_enabled' => (bool) $entry->is_enabled,
                'remark' => $entry->remark !== null ? (string) $entry->remark : null,
            ])->all();
    }

    public static function normalizeMonsterEntriesOrFail(mixed $raw, string $spawnType): array
    {
        $rows = MonsterModuleSupport::normalizeDifficultyMonsters($raw, $spawnType);
        MonsterModuleSupport::validateDifficultyMonstersOrFail($rows, $spawnType);

        return $rows;
    }

    public static function syncMonsterEntries(MainStageDifficulty $difficulty, array $normalMonsters, array $eliteMonsters, array $bossMonsters): void
    {
        $difficulty->monsterEntries()->delete();

        foreach (array_merge($normalMonsters, $eliteMonsters, $bossMonsters) as $row) {
            $difficulty->monsterEntries()->create($row);
        }
    }

    public static function monsterSummary(MainStageDifficulty $difficulty): string
    {
        $counts = $difficulty->monsterEntries()
            ->selectRaw('spawn_type, count(*) as total')
            ->groupBy('spawn_type')
            ->pluck('total', 'spawn_type')
            ->all();

        return sprintf(
            '普通%d / 精英%d / Boss%d',
            (int) ($counts['normal'] ?? 0),
            (int) ($counts['elite'] ?? 0),
            (int) ($counts['boss'] ?? 0),
        );
    }

    private static function monsterEntriesRepeater(string $name, string $spawnType): Repeater
    {
        return Repeater::make($name)
            ->label('怪物列表')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['monster_id'] ?? null) ? AdminOptions::monsterName((string) $state['monster_id']) : null)
            ->addActionLabel('新增怪物')
            ->schema([
                Select::make('monster_id')
                    ->label('怪物')
                    ->options(fn ($get): array => AdminOptions::monsterOptions($spawnType, filled($get('../../chapter_id')) ? (string) $get('../../chapter_id') : null))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                TextInput::make('weight')->label('权重')->required()->integer()->minValue(1)->default(100)->columnSpan(2),
                TextInput::make('min_count')->label('最小数量')->required()->integer()->minValue(1)->default($spawnType === 'boss' ? 1 : 1)->columnSpan(2),
                TextInput::make('max_count')->label('最大数量')->required()->integer()->minValue(1)->default($spawnType === 'boss' ? 1 : 1)->columnSpan(2),
                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(1),
                Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
