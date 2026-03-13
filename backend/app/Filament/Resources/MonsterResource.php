<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonsterResource\Pages;
use App\Models\Monster;
use App\Models\MonsterBossProfile;
use App\Models\MonsterDropItem;
use App\Models\MonsterSkillBinding;
use App\Support\AdminOptions;
use App\Support\MonsterModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MonsterResource extends Resource
{
    protected static ?string $model = Monster::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = '怪物库';

    protected static ?string $pluralModelLabel = '怪物';

    protected static ?string $modelLabel = '怪物';

    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('monster_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('基础字段')
                                    ->description('普通怪、精英怪、Boss 共用一张主表。这里不录入技能明细和掉落条目。')
                                    ->schema([
                                        TextInput::make('monster_id')->label('怪物 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                                        TextInput::make('monster_name')->label('名称')->required()->maxLength(255),
                                        TextInput::make('display_name')->label('展示名')->required()->maxLength(255),
                                        Select::make('monster_type')->label('怪物类型')->required()->options(MonsterModuleSupport::monsterTypeOptions())->live(),
                                        Select::make('chapter_id')->label('所属章节')->required()->options(fn (): array => AdminOptions::mainStageCombatChapterOptions())->searchable()->preload(),
                                        Select::make('display_stage_id')->label('展示关卡')->options(fn (): array => AdminOptions::mainStageCombatChapterOptions())->searchable()->preload()->placeholder('同章节展示'),
                                        TextInput::make('family')->label('族系')->maxLength(120),
                                        TextInput::make('title')->label('称号')->maxLength(120),
                                        TextInput::make('prefab_key')->label('预制体 Key')->maxLength(160),
                                        TextInput::make('icon')->label('图标')->maxLength(255),
                                        TextInput::make('sprite')->label('立绘/精灵资源')->maxLength(255),
                                        Select::make('rarity_tag')->label('稀有标签')->required()->options(MonsterModuleSupport::rarityTagOptions()),
                                        TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                                        Toggle::make('is_enabled')->label('启用')->default(true),
                                        Textarea::make('desc')->label('说明')->rows(3)->columnSpanFull(),
                                        Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                                    ])->columns(4),
                            ]),
                        Tab::make('战斗数值')
                            ->schema([
                                Section::make('战斗参数')
                                    ->schema([
                                        TextInput::make('level')->label('等级')->required()->integer()->minValue(1),
                                        TextInput::make('hp')->label('生命')->required()->integer()->minValue(1),
                                        TextInput::make('atk')->label('攻击')->required()->integer()->minValue(0),
                                        TextInput::make('def')->label('防御')->required()->integer()->minValue(0),
                                        TextInput::make('speed')->label('速度')->required()->integer()->minValue(0),
                                        TextInput::make('move_speed')->label('移动速度')->required()->integer()->minValue(0),
                                        TextInput::make('attack_range')->label('攻击范围')->required()->integer()->minValue(0),
                                        TextInput::make('attack_interval')->label('攻击间隔')->required()->numeric()->minValue(0.1),
                                        TextInput::make('aggro_range')->label('警戒范围')->required()->integer()->minValue(0),
                                        Select::make('ai_type')->label('AI 类型')->required()->options(MonsterModuleSupport::aiTypeOptions()),
                                    ])->columns(5),
                            ]),
                        Tab::make('技能挂载')
                            ->schema([
                                Section::make('怪物技能')
                                    ->description('技能挂载结构化维护，不允许在怪物主表里写 skill_ids 字符串。')
                                    ->schema([
                                        Repeater::make('skill_bindings')
                                            ->label('技能挂载')
                                            ->default([])
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->itemLabel(fn (array $state): ?string => filled($state['skill_id'] ?? null) ? (string) $state['skill_id'] : '技能')
                                            ->addActionLabel('新增技能挂载')
                                            ->schema([
                                                TextInput::make('skill_id')->label('技能 ID')->required()->maxLength(160)->columnSpan(4),
                                                Select::make('slot_type')->label('槽位类型')->required()->options(MonsterModuleSupport::slotTypeOptions())->columnSpan(3),
                                                TextInput::make('trigger_priority')->label('触发优先级')->required()->integer()->minValue(0)->default(10)->columnSpan(2),
                                                TextInput::make('phase_limit')->label('阶段限制')->maxLength(64)->columnSpan(2),
                                                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                                                TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(2),
                                                Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                                            ])
                                            ->columns(12)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('掉落条目')
                            ->schema([
                                Section::make('怪物掉落')
                                    ->description('怪物直接维护掉落物条目，不再通过 drop_group 间接挂载。')
                                    ->schema([
                                        Repeater::make('drop_items')
                                            ->label('掉落条目')
                                            ->default([])
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : '掉落物')
                                            ->addActionLabel('新增掉落条目')
                                            ->schema([
                                                Select::make('item_id')->label('掉落物品')->options(fn (): array => AdminOptions::itemOptions())->searchable()->preload()->required()->columnSpan(4),
                                                Select::make('drop_type')->label('掉落类型')->options(MonsterModuleSupport::dropTypeOptions())->required()->default('fixed')->columnSpan(2),
                                                TextInput::make('count_min')->label('最小数量')->integer()->minValue(1)->required()->default(1)->columnSpan(2),
                                                TextInput::make('count_max')->label('最大数量')->integer()->minValue(1)->required()->default(1)->columnSpan(2),
                                                TextInput::make('drop_rate')->label('掉落概率')->numeric()->minValue(0)->maxValue(1)->required()->default(1)->columnSpan(1),
                                                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                                                TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(2),
                                                Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                                            ])
                                            ->columns(12)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Boss扩展')
                            ->visible(fn ($get): bool => (string) $get('monster_type') === 'boss')
                            ->schema([
                                Section::make('Boss扩展')
                                    ->description('仅 Boss 使用。普通怪和精英怪不写这部分。')
                                    ->schema([
                                        TextInput::make('boss_profile.phase_count')->label('阶段数')->integer()->minValue(1)->default(1),
                                        TextInput::make('boss_profile.battle_bgm_id')->label('战斗 BGM ID')->maxLength(160),
                                        TextInput::make('boss_profile.camera_rule')->label('镜头规则')->maxLength(120),
                                        TextInput::make('boss_profile.entry_fx_key')->label('入场特效 Key')->maxLength(160),
                                        TextInput::make('boss_profile.death_fx_key')->label('死亡特效 Key')->maxLength(160),
                                        TextInput::make('boss_profile.story_flag_on_clear')->label('通关剧情标记')->maxLength(160),
                                        Textarea::make('boss_profile.intro_text')->label('Boss 出场文本')->rows(3)->columnSpanFull(),
                                        Textarea::make('boss_profile.remark')->label('Boss 备注')->rows(3)->columnSpanFull(),
                                        static::bossRuleRepeater('boss_profile.phase_rules', '阶段规则', '新增阶段规则'),
                                        static::bossRuleRepeater('boss_profile.summon_rules', '召唤规则', '新增召唤规则'),
                                        static::bossRuleRepeater('boss_profile.rage_rules', '狂暴规则', '新增狂暴规则'),
                                        static::bossRuleRepeater('boss_profile.weak_point_rules', '弱点规则', '新增弱点规则'),
                                    ])->columns(4),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('monster_id')->label('怪物ID')->searchable()->sortable(),
                TextColumn::make('monster_name')->label('名称')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名')->searchable(),
                TextColumn::make('monster_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(MonsterModuleSupport::monsterTypeOptions(), $state))->sortable(),
                TextColumn::make('chapter_id')->label('所属章节')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::mainStageChapterOptions(), $state))->sortable(),
                TextColumn::make('level')->label('等级')->numeric()->sortable(),
                TextColumn::make('hp')->label('HP')->numeric()->sortable(),
                TextColumn::make('ai_type')->label('AI')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(MonsterModuleSupport::aiTypeOptions(), $state))->wrap(),
                IconColumn::make('has_skills')->label('已配技能')->state(fn (Monster $record): bool => $record->skillBindings()->exists())->boolean(),
                IconColumn::make('has_drops')->label('已配掉落')->state(fn (Monster $record): bool => $record->drops()->exists())->boolean(),
                IconColumn::make('is_boss')->label('Boss')->state(fn (Monster $record): bool => $record->monster_type === 'boss')->boolean(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('monster_type')->label('怪物类型')->options(MonsterModuleSupport::monsterTypeOptions()),
                Tables\Filters\SelectFilter::make('chapter_id')->label('所属章节')->options(fn (): array => AdminOptions::mainStageCombatChapterOptions()),
                Tables\Filters\TernaryFilter::make('is_boss')
                    ->label('是否Boss')
                    ->queries(
                        true: fn ($query) => $query->where('monster_type', 'boss'),
                        false: fn ($query) => $query->where('monster_type', '!=', 'boss'),
                        blank: fn ($query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('missing_skills')
                    ->label('缺技能配置')
                    ->queries(
                        true: fn ($query) => $query->doesntHave('skillBindings'),
                        false: fn ($query) => $query->has('skillBindings'),
                        blank: fn ($query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('missing_drops')
                    ->label('缺掉落配置')
                    ->queries(
                        true: fn ($query) => $query->doesntHave('drops'),
                        false: fn ($query) => $query->has('drops'),
                        blank: fn ($query) => $query,
                    ),
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
            'index' => Pages\ListMonsters::route('/'),
            'create' => Pages\CreateMonster::route('/create'),
            'edit' => Pages\EditMonster::route('/{record}/edit'),
        ];
    }

    public static function skillBindingsForForm(Monster $record): array
    {
        return $record->skillBindings()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MonsterSkillBinding $binding): array => [
                'skill_id' => (string) $binding->skill_id,
                'slot_type' => (string) $binding->slot_type,
                'trigger_priority' => (int) $binding->trigger_priority,
                'phase_limit' => $binding->phase_limit !== null ? (string) $binding->phase_limit : null,
                'sort_order' => (int) $binding->sort_order,
                'is_enabled' => (bool) $binding->is_enabled,
                'remark' => $binding->remark !== null ? (string) $binding->remark : null,
            ])->all();
    }

    public static function dropItemsForForm(Monster $record): array
    {
        return $record->drops()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MonsterDropItem $drop): array => [
                'item_id' => (string) $drop->item_id,
                'drop_type' => (string) $drop->drop_type,
                'count_min' => (int) $drop->count_min,
                'count_max' => (int) $drop->count_max,
                'drop_rate' => (float) $drop->drop_rate,
                'sort_order' => (int) $drop->sort_order,
                'is_enabled' => (bool) $drop->is_enabled,
                'remark' => $drop->remark !== null ? (string) $drop->remark : null,
            ])->all();
    }

    public static function bossProfileForForm(Monster $record): array
    {
        $profile = $record->bossProfile;

        if (! $profile instanceof MonsterBossProfile) {
            return [
                'phase_count' => 1,
                'phase_rules' => [],
                'summon_rules' => [],
                'rage_rules' => [],
                'weak_point_rules' => [],
            ];
        }

        return [
            'phase_count' => (int) $profile->phase_count,
            'phase_rules' => array_values(is_array($profile->phase_rules) ? $profile->phase_rules : []),
            'summon_rules' => array_values(is_array($profile->summon_rules) ? $profile->summon_rules : []),
            'rage_rules' => array_values(is_array($profile->rage_rules) ? $profile->rage_rules : []),
            'weak_point_rules' => array_values(is_array($profile->weak_point_rules) ? $profile->weak_point_rules : []),
            'intro_text' => $profile->intro_text,
            'battle_bgm_id' => $profile->battle_bgm_id,
            'camera_rule' => $profile->camera_rule,
            'entry_fx_key' => $profile->entry_fx_key,
            'death_fx_key' => $profile->death_fx_key,
            'story_flag_on_clear' => $profile->story_flag_on_clear,
            'remark' => $profile->remark,
        ];
    }

    public static function normalizeSkillBindingsOrFail(mixed $raw): array
    {
        $rows = MonsterModuleSupport::normalizeSkillBindings($raw);
        MonsterModuleSupport::validateSkillBindingsOrFail($rows);

        return $rows;
    }

    public static function normalizeDropItemsOrFail(mixed $raw): array
    {
        $rows = MonsterModuleSupport::normalizeDropItems($raw);
        MonsterModuleSupport::validateDropItemsOrFail($rows);

        return $rows;
    }

    public static function normalizeBossProfile(mixed $raw): array
    {
        return MonsterModuleSupport::normalizeBossProfile($raw);
    }

    public static function syncRelations(Monster $monster, array $skillBindings, array $dropItems, array $bossProfile): void
    {
        $monster->skillBindings()->delete();
        foreach ($skillBindings as $row) {
            $monster->skillBindings()->create($row);
        }

        $monster->drops()->delete();
        foreach ($dropItems as $row) {
            $monster->drops()->create($row);
        }

        if ($monster->monster_type === 'boss') {
            $monster->bossProfile()->updateOrCreate(
                ['monster_id' => $monster->monster_id],
                $bossProfile,
            );
        } else {
            $monster->bossProfile()->delete();
        }
    }

    private static function bossRuleRepeater(string $name, string $label, string $addLabel): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->addActionLabel($addLabel)
            ->schema([
                TextInput::make('phase')->label('阶段')->integer()->minValue(1)->default(1)->columnSpan(2),
                TextInput::make('trigger')->label('触发条件')->maxLength(120)->columnSpan(3),
                TextInput::make('value')->label('数值/标识')->maxLength(120)->columnSpan(3),
                Textarea::make('note')->label('说明')->rows(2)->columnSpan(4),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
