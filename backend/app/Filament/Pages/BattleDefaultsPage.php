<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Services\BattleDefaultsService;
use App\Support\AdminOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

class BattleDefaultsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = '战斗默认配置';

    protected static ?string $title = '战斗默认配置';

    protected static ?int $navigationSort = 99;

    protected static string | \UnitEnum | null $navigationGroup = '系统配置';

    protected string $view = 'filament.pages.battle-defaults-page';

    public ?array $data = [];

    public function mount(): void
    {
        BattleDefaultsService::ensureDefaultSetting();
        $this->form->fill($this->toFormState(BattleDefaultsService::loadConfig()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('BattleDefaultsTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('阈值')
                            ->schema([
                                Section::make('刷怪阈值')
                                    ->description('控制普通击杀累计到多少后刷出精英和 Boss。')
                                    ->schema([
                                        TextInput::make('spawn_rules.elite_every_kills')
                                            ->label('精英触发击杀数')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->helperText('建议保持中等频率，避免战斗节奏过慢。'),
                                        TextInput::make('spawn_rules.boss_every_kills')
                                            ->label('Boss触发击杀数')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->helperText('数值越高，Boss 出现越晚。'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('普通掉落')
                            ->schema([
                                Section::make('基础概率')
                                    ->description('统一配置普通掉落的基础概率和稀有度权重。')
                                    ->schema([
                                        TextInput::make('drops.drop_chance')
                                            ->label('基础掉落概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1)
                                            ->helperText('填写 0~1 之间的小数。'),
                                        TextInput::make('drops.rarity_weights.white')
                                            ->label('白色权重')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('drops.rarity_weights.blue')
                                            ->label('蓝色权重')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('drops.rarity_weights.gold')
                                            ->label('金色权重')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                    ])
                                    ->columns(4),
                                Section::make('按稀有度掉落池')
                                    ->description('掉落池统一用可搜索多选录入，不允许手输物品 ID。')
                                    ->schema([
                                        Select::make('drops.items_by_rarity.white')
                                            ->label('白色掉落池')
                                            ->multiple()
                                            ->options(fn (): array => $this->itemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->minItems(1),
                                        Select::make('drops.items_by_rarity.blue')
                                            ->label('蓝色掉落池')
                                            ->multiple()
                                            ->options(fn (): array => $this->itemOptions())
                                            ->searchable()
                                            ->preload(),
                                        Select::make('drops.items_by_rarity.gold')
                                            ->label('金色掉落池')
                                            ->multiple()
                                            ->options(fn (): array => $this->itemOptions())
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(1),
                            ]),

                        Tab::make('特殊掉落')
                            ->schema([
                                Section::make('普通怪特殊掉落')
                                    ->schema([
                                        TextInput::make('special_drops.normal.extra_gem_chance')
                                            ->label('额外宝石概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                        Select::make('special_drops.normal.extra_gems')
                                            ->label('宝石池')
                                            ->multiple()
                                            ->options(fn (): array => $this->gemOptions())
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                                Section::make('精英怪特殊掉落')
                                    ->schema([
                                        TextInput::make('special_drops.elite.punch_stone_chance')
                                            ->label('打孔石概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                        TextInput::make('special_drops.elite.extra_gem_chance')
                                            ->label('额外宝石概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                        Select::make('special_drops.elite.extra_gems')
                                            ->label('宝石池')
                                            ->multiple()
                                            ->options(fn (): array => $this->gemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Section::make('Boss 特殊掉落')
                                    ->schema([
                                        Select::make('special_drops.boss.core_guarantee')
                                            ->label('Boss 保底核心')
                                            ->options(fn (): array => $this->gemOptions())
                                            ->searchable()
                                            ->required()
                                            ->default('妖王核心'),
                                        TextInput::make('special_drops.boss.punch_stone_chance')
                                            ->label('打孔石概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                        TextInput::make('special_drops.boss.extra_gem_chance')
                                            ->label('额外宝石概率')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                        Select::make('special_drops.boss.extra_gems')
                                            ->label('宝石池')
                                            ->multiple()
                                            ->options(fn (): array => $this->gemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3),
                            ]),

                        Tab::make('回血')
                            ->schema([
                                Section::make('基础回血规则')
                                    ->schema([
                                        TextInput::make('player_regen.regen_delay')
                                            ->label('受击后回血延迟（秒）')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(10),
                                        TextInput::make('player_regen.regen_base')
                                            ->label('基础回血速率（每秒）')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(10),
                                        TextInput::make('player_regen.regen_per_physique')
                                            ->label('每体魄加成回血')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                    ])
                                    ->columns(3),
                                Section::make('击杀回血')
                                    ->schema([
                                        TextInput::make('player_regen.heal_on_kill.normal')
                                            ->label('击杀普通怪回血')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('player_regen.heal_on_kill.elite')
                                            ->label('击杀精英回血')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('player_regen.heal_on_kill.boss')
                                            ->label('击杀 Boss 回血')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                    ])
                                    ->columns(3),
                            ]),

                        Tab::make('宗门与AI')
                            ->schema([
                                Section::make('宗门基础战斗')
                                    ->description('技能页的宗门切换、基础攻击参数和战斗 AI 默认资料统一从这里读取。')
                                    ->schema([
                                        Repeater::make('classes')
                                            ->label('宗门列表')
                                            ->defaultItems(0)
                                            ->addActionLabel('新增宗门')
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->itemLabel(fn (array $state): string => trim((string) ($state['name'] ?? $state['id'] ?? '宗门')))
                                            ->schema([
                                                TextInput::make('id')->label('宗门ID')->required()->maxLength(64),
                                                TextInput::make('name')->label('宗门名称')->required()->maxLength(64),
                                                TextInput::make('role')->label('定位文案')->maxLength(255),
                                                TextInput::make('starter.skill_points')->label('默认技能点兜底')->required()->integer()->minValue(0)->default(1),
                                                TextInput::make('base_combat.move_speed')->label('移速倍率')->required()->numeric()->minValue(0.1)->default(1),
                                                TextInput::make('base_combat.attack_range')->label('普攻距离')->required()->numeric()->minValue(0.1)->default(1.8),
                                                TextInput::make('base_combat.basic_attack_cd')->label('普攻间隔')->required()->numeric()->minValue(0.05)->default(1.2),
                                                Select::make('base_combat.basic_attack_damage.source')
                                                    ->label('普攻伤害来源')
                                                    ->required()
                                                    ->options(AdminOptions::combatDamageSourceOptions())
                                                    ->default('WD'),
                                                TextInput::make('base_combat.basic_attack_damage.coef')->label('普攻伤害系数')->required()->numeric()->minValue(0)->default(0.7),
                                                Textarea::make('base_combat.notes')->label('战斗备注')->rows(2)->columnSpanFull(),
                                            ])
                                            ->columns(3)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('战斗全局')
                                    ->schema([
                                        TextInput::make('combat.gcd_seconds')
                                            ->label('公共施法间隔（秒）')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0.05),
                                        TextInput::make('combat.default_skill_coef_per_level')
                                            ->label('技能默认每级成长系数')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0),
                                        TextInput::make('combat.avoid_waste.debuff_refresh_threshold_sec')
                                            ->label('减益刷新阈值（秒）')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0),
                                        TextInput::make('combat.avoid_waste.shield_keep_threshold_pct')
                                            ->label('护盾保留阈值')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(1),
                                    ])
                                    ->columns(4),
                                Section::make('AI 策略')
                                    ->description('优先级和施法条件使用 JSON 对象维护，键名为宗门ID或技能ID。')
                                    ->schema([
                                        Repeater::make('ai_profiles.profiles')
                                            ->label('策略列表')
                                            ->defaultItems(0)
                                            ->addActionLabel('新增策略')
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->itemLabel(fn (array $state): string => trim((string) ($state['name'] ?? $state['id'] ?? '策略')))
                                            ->schema([
                                                TextInput::make('id')->label('策略ID')->required()->maxLength(64),
                                                TextInput::make('name')->label('策略名称')->required()->maxLength(64),
                                                Textarea::make('priorities_json')
                                                    ->label('优先级 JSON')
                                                    ->rows(8)
                                                    ->helperText('示例：{\"bing\":{\"BING_01\":95}}')
                                                    ->rule(static function (): \Closure {
                                                        return function (string $attribute, mixed $value, \Closure $fail): void {
                                                            $text = trim((string) $value);
                                                            if ($text === '') {
                                                                return;
                                                            }

                                                            $decoded = json_decode($text, true);
                                                            if (! is_array($decoded) || array_is_list($decoded)) {
                                                                $fail('优先级 JSON 必须是对象。');
                                                            }
                                                        };
                                                    }),
                                                Textarea::make('cast_conditions_json')
                                                    ->label('施法条件 JSON')
                                                    ->rows(8)
                                                    ->helperText('示例：{\"BING_01\":\"boss_present || elite_present\"}')
                                                    ->rule(static function (): \Closure {
                                                        return function (string $attribute, mixed $value, \Closure $fail): void {
                                                            $text = trim((string) $value);
                                                            if ($text === '') {
                                                                return;
                                                            }

                                                            $decoded = json_decode($text, true);
                                                            if (! is_array($decoded) || array_is_list($decoded)) {
                                                                $fail('施法条件 JSON 必须是对象。');
                                                            }
                                                        };
                                                    }),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                        TextInput::make('ai_profiles.auto_switch.switch_cooldown_sec')
                                            ->label('自动切换冷却（秒）')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0),
                                        Repeater::make('ai_profiles.auto_switch.rules_in_order')
                                            ->label('自动切换规则')
                                            ->defaultItems(0)
                                            ->addActionLabel('新增切换规则')
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->schema([
                                                TextInput::make('if')->label('条件表达式')->required()->maxLength(255),
                                                TextInput::make('profile')->label('目标策略ID')->required()->maxLength(64),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('经济')
                            ->schema([
                                Section::make('鉴定消耗')
                                    ->description('不同品质装备的鉴定金币消耗。')
                                    ->schema([
                                        TextInput::make('economy.identify_cost_by_rarity.white')
                                            ->label('白装鉴定金币')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('economy.identify_cost_by_rarity.blue')
                                            ->label('蓝装鉴定金币')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                        TextInput::make('economy.identify_cost_by_rarity.gold')
                                            ->label('金装鉴定金币')
                                            ->required()
                                            ->integer()
                                            ->minValue(0),
                                    ])
                                    ->columns(3),
                                Section::make('进阶消耗（每级）')
                                    ->description('金币放在同一行展示，各品质材料独占整行，便于策划长期维护。')
                                    ->schema([
                                        TextInput::make('economy.refine_cost_by_rarity.white.gold')->label('白装进阶金币')->required()->integer()->minValue(0),
                                        TextInput::make('economy.refine_cost_by_rarity.blue.gold')->label('蓝装进阶金币')->required()->integer()->minValue(0),
                                        TextInput::make('economy.refine_cost_by_rarity.gold.gold')->label('金装进阶金币')->required()->integer()->minValue(0),
                                        $this->compactItemRepeater('economy.refine_cost_by_rarity.white.items_rows', '白装进阶材料'),
                                        $this->compactItemRepeater('economy.refine_cost_by_rarity.blue.items_rows', '蓝装进阶材料'),
                                        $this->compactItemRepeater('economy.refine_cost_by_rarity.gold.items_rows', '金装进阶材料'),
                                    ])
                                    ->columns(3),
                                Section::make('分解奖励')
                                    ->description('分解金币按品质横向显示，材料使用紧凑 Repeater。')
                                    ->schema([
                                        TextInput::make('economy.salvage_reward_by_rarity.white.gold')->label('白装分解金币')->required()->integer()->minValue(0),
                                        TextInput::make('economy.salvage_reward_by_rarity.blue.gold')->label('蓝装分解金币')->required()->integer()->minValue(0),
                                        TextInput::make('economy.salvage_reward_by_rarity.gold.gold')->label('金装分解金币')->required()->integer()->minValue(0),
                                        $this->compactItemRepeater('economy.salvage_reward_by_rarity.white.items_rows', '白装分解材料'),
                                        $this->compactItemRepeater('economy.salvage_reward_by_rarity.blue.items_rows', '蓝装分解材料'),
                                        $this->compactItemRepeater('economy.salvage_reward_by_rarity.gold.items_rows', '金装分解材料'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tab::make('词条池（进阶）')
                            ->schema([
                                Section::make('进阶新增词条池')
                                    ->description('统一维护每次进阶可能新增的词条，不允许自由输入属性键。')
                                    ->schema([
                                        Repeater::make('refine_effect_pool')
                                            ->label('词条池')
                                            ->required()
                                            ->minItems(1)
                                            ->defaultItems(1)
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->schema([
                                                TextInput::make('w')
                                                    ->label('权重')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                                Select::make('type')
                                                    ->label('类型')
                                                    ->required()
                                                    ->options([
                                                        'stat' => '属性',
                                                        'skill_level' => '技能等级',
                                                    ])
                                                    ->default('stat'),
                                                Select::make('stat')
                                                    ->label('属性')
                                                    ->options(fn (): array => $this->statOptions())
                                                    ->required(fn (Get $get): bool => $get('type') === 'stat')
                                                    ->hidden(fn (Get $get): bool => $get('type') !== 'stat')
                                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'stat'),
                                                Select::make('skill_id')
                                                    ->label('技能')
                                                    ->options(fn (): array => $this->skillOptions())
                                                    ->searchable()
                                                    ->required(fn (Get $get): bool => $get('type') === 'skill_level')
                                                    ->hidden(fn (Get $get): bool => $get('type') !== 'skill_level')
                                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'skill_level'),
                                                TextInput::make('val')
                                                    ->label('数值')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(1),
                                            ])
                                            ->columns(4)
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function compactItemRepeater(string $path, string $label): Repeater
    {
        return Repeater::make($path)
            ->label($label)
            ->defaultItems(0)
            ->addActionLabel('添加材料')
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->collapsible()
            ->columnSpanFull()
            ->schema([
                Select::make('item_id')
                    ->label('物品')
                    ->options(fn (): array => $this->itemOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                TextInput::make('count')
                    ->label('数量')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(1)
                    ->columnSpan(2),
            ])
            ->columns(6);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportBattleDefaults')
                ->label('导出 battle_defaults.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): void {
                    Artisan::call('game:export-battle-defaults');

                    Notification::make()
                        ->title('导出成功')
                        ->body('已生成：storage/app/exports/battle_defaults.json')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $payload = $this->buildPayload($state);
        $this->validatePayloadOrFail($payload);
        BattleDefaultsService::saveConfig($payload);
        $this->form->fill($this->toFormState($payload));

        Notification::make()
            ->title('保存成功')
            ->body('战斗默认配置已更新。')
            ->success()
            ->send();
    }

    private function buildPayload(array $state): array
    {
        $coreGuarantee = $state['special_drops']['boss']['core_guarantee'] ?? '';

        return [
            'spawn_rules' => [
                'elite_every_kills' => (int) ($state['spawn_rules']['elite_every_kills'] ?? 40),
                'boss_every_kills' => (int) ($state['spawn_rules']['boss_every_kills'] ?? 120),
            ],
            'drops' => [
                'drop_chance' => (float) ($state['drops']['drop_chance'] ?? 0.28),
                'rarity_weights' => [
                    'white' => (int) ($state['drops']['rarity_weights']['white'] ?? 85),
                    'blue' => (int) ($state['drops']['rarity_weights']['blue'] ?? 13),
                    'gold' => (int) ($state['drops']['rarity_weights']['gold'] ?? 2),
                ],
                'items_by_rarity' => [
                    'white' => $this->normalizeIdList($state['drops']['items_by_rarity']['white'] ?? []),
                    'blue' => $this->normalizeIdList($state['drops']['items_by_rarity']['blue'] ?? []),
                    'gold' => $this->normalizeIdList($state['drops']['items_by_rarity']['gold'] ?? []),
                ],
            ],
            'special_drops' => [
                'normal' => [
                    'extra_gem_chance' => (float) ($state['special_drops']['normal']['extra_gem_chance'] ?? 0.02),
                    'extra_gems' => $this->normalizeIdList($state['special_drops']['normal']['extra_gems'] ?? []),
                ],
                'elite' => [
                    'punch_stone_chance' => (float) ($state['special_drops']['elite']['punch_stone_chance'] ?? 0.15),
                    'extra_gem_chance' => (float) ($state['special_drops']['elite']['extra_gem_chance'] ?? 0.08),
                    'extra_gems' => $this->normalizeIdList($state['special_drops']['elite']['extra_gems'] ?? []),
                ],
                'boss' => [
                    'core_guarantee' => trim((string) $coreGuarantee),
                    'punch_stone_chance' => (float) ($state['special_drops']['boss']['punch_stone_chance'] ?? 0.40),
                    'extra_gem_chance' => (float) ($state['special_drops']['boss']['extra_gem_chance'] ?? 0.22),
                    'extra_gems' => $this->normalizeIdList($state['special_drops']['boss']['extra_gems'] ?? []),
                ],
            ],
            'player_regen' => [
                'regen_delay' => (float) ($state['player_regen']['regen_delay'] ?? 1.2),
                'regen_base' => (float) ($state['player_regen']['regen_base'] ?? 0.35),
                'regen_per_physique' => (float) ($state['player_regen']['regen_per_physique'] ?? 0.02),
                'heal_on_kill' => [
                    'normal' => (int) ($state['player_regen']['heal_on_kill']['normal'] ?? 1),
                    'elite' => (int) ($state['player_regen']['heal_on_kill']['elite'] ?? 2),
                    'boss' => (int) ($state['player_regen']['heal_on_kill']['boss'] ?? 4),
                ],
            ],
            'classes' => $this->normalizeRuntimeClasses($state['classes'] ?? []),
            'combat' => [
                'gcd_seconds' => (float) ($state['combat']['gcd_seconds'] ?? 0.4),
                'default_skill_coef_per_level' => (float) ($state['combat']['default_skill_coef_per_level'] ?? 0.03),
                'avoid_waste' => [
                    'debuff_refresh_threshold_sec' => (float) ($state['combat']['avoid_waste']['debuff_refresh_threshold_sec'] ?? 2.0),
                    'shield_keep_threshold_pct' => (float) ($state['combat']['avoid_waste']['shield_keep_threshold_pct'] ?? 0.3),
                ],
            ],
            'ai_profiles' => [
                'profiles' => $this->normalizeAiProfiles($state['ai_profiles']['profiles'] ?? []),
                'auto_switch' => [
                    'switch_cooldown_sec' => (float) ($state['ai_profiles']['auto_switch']['switch_cooldown_sec'] ?? 2.0),
                    'rules_in_order' => $this->normalizeAiRules($state['ai_profiles']['auto_switch']['rules_in_order'] ?? []),
                ],
            ],
            'refine_effect_pool' => $this->normalizeRefineEffectPool($state['refine_effect_pool'] ?? []),
            'economy' => [
                'identify_cost_by_rarity' => [
                    'white' => max(0, (int) ($state['economy']['identify_cost_by_rarity']['white'] ?? 20)),
                    'blue' => max(0, (int) ($state['economy']['identify_cost_by_rarity']['blue'] ?? 60)),
                    'gold' => max(0, (int) ($state['economy']['identify_cost_by_rarity']['gold'] ?? 160)),
                ],
                'refine_cost_by_rarity' => [
                    'white' => [
                        'gold' => max(0, (int) ($state['economy']['refine_cost_by_rarity']['white']['gold'] ?? 15)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['refine_cost_by_rarity']['white']['items_rows'] ?? []),
                    ],
                    'blue' => [
                        'gold' => max(0, (int) ($state['economy']['refine_cost_by_rarity']['blue']['gold'] ?? 40)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['refine_cost_by_rarity']['blue']['items_rows'] ?? []),
                    ],
                    'gold' => [
                        'gold' => max(0, (int) ($state['economy']['refine_cost_by_rarity']['gold']['gold'] ?? 100)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['refine_cost_by_rarity']['gold']['items_rows'] ?? []),
                    ],
                ],
                'salvage_reward_by_rarity' => [
                    'white' => [
                        'gold' => max(0, (int) ($state['economy']['salvage_reward_by_rarity']['white']['gold'] ?? 8)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['salvage_reward_by_rarity']['white']['items_rows'] ?? []),
                    ],
                    'blue' => [
                        'gold' => max(0, (int) ($state['economy']['salvage_reward_by_rarity']['blue']['gold'] ?? 20)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['salvage_reward_by_rarity']['blue']['items_rows'] ?? []),
                    ],
                    'gold' => [
                        'gold' => max(0, (int) ($state['economy']['salvage_reward_by_rarity']['gold']['gold'] ?? 50)),
                        'items' => $this->normalizeItemRewardRows($state['economy']['salvage_reward_by_rarity']['gold']['items_rows'] ?? []),
                    ],
                ],
            ],
        ];
    }

    private function validatePayloadOrFail(array $payload): void
    {
        $errors = [];
        $this->assertChanceRange($errors, 'drops.drop_chance', (float) $payload['drops']['drop_chance'], '基础掉落概率');
        $this->assertChanceRange($errors, 'special_drops.normal.extra_gem_chance', (float) $payload['special_drops']['normal']['extra_gem_chance'], '普通怪额外宝石概率');
        $this->assertChanceRange($errors, 'special_drops.elite.punch_stone_chance', (float) $payload['special_drops']['elite']['punch_stone_chance'], '精英打孔石概率');
        $this->assertChanceRange($errors, 'special_drops.elite.extra_gem_chance', (float) $payload['special_drops']['elite']['extra_gem_chance'], '精英额外宝石概率');
        $this->assertChanceRange($errors, 'special_drops.boss.punch_stone_chance', (float) $payload['special_drops']['boss']['punch_stone_chance'], 'Boss打孔石概率');
        $this->assertChanceRange($errors, 'special_drops.boss.extra_gem_chance', (float) $payload['special_drops']['boss']['extra_gem_chance'], 'Boss额外宝石概率');

        $weights = $payload['drops']['rarity_weights'];
        $weightSum = (int) $weights['white'] + (int) $weights['blue'] + (int) $weights['gold'];
        if ($weightSum <= 0) {
            $errors['drops.rarity_weights.white'] = '稀有度权重总和必须大于0。';
        }

        foreach (['white', 'blue', 'gold'] as $rarity) {
            if ((int) $weights[$rarity] < 0) {
                $errors["drops.rarity_weights.{$rarity}"] = '稀有度权重不能为负数。';
            }
        }

        if (count($payload['drops']['items_by_rarity']['white']) < 1) {
            $errors['drops.items_by_rarity.white'] = '白色掉落池至少选择1项。';
        }

        $core = trim((string) $payload['special_drops']['boss']['core_guarantee']);
        if ($core === '') {
            $errors['special_drops.boss.core_guarantee'] = 'Boss保底核心必填。';
        } elseif (! Item::query()->where('id', $core)->where('type', 'gem')->exists()) {
            $errors['special_drops.boss.core_guarantee'] = 'Boss保底核心必须是宝石类型物品。';
        }

        foreach (['normal', 'elite', 'boss'] as $kind) {
            $heal = (int) $payload['player_regen']['heal_on_kill'][$kind];
            if ($heal < 0) {
                $errors["player_regen.heal_on_kill.{$kind}"] = '击杀回血不能为负数。';
            }
        }

        foreach (['white', 'blue', 'gold'] as $rarity) {
            $cost = (int) ($payload['economy']['identify_cost_by_rarity'][$rarity] ?? 0);
            if ($cost < 0) {
                $errors["economy.identify_cost_by_rarity.{$rarity}"] = '鉴定金币不能为负数。';
            }

            $refineGold = (int) ($payload['economy']['refine_cost_by_rarity'][$rarity]['gold'] ?? 0);
            if ($refineGold < 0) {
                $errors["economy.refine_cost_by_rarity.{$rarity}.gold"] = '进阶金币不能为负数。';
            }
            $refineItems = $payload['economy']['refine_cost_by_rarity'][$rarity]['items'] ?? [];
            if (! is_array($refineItems)) {
                $errors["economy.refine_cost_by_rarity.{$rarity}.items_rows"] = '进阶材料配置格式错误。';
            } else {
                foreach ($refineItems as $itemId => $count) {
                    $id = trim((string) $itemId);
                    $qty = (int) $count;
                    if ($id === '') {
                        continue;
                    }
                    if ($qty < 0) {
                        $errors["economy.refine_cost_by_rarity.{$rarity}.items_rows"] = '进阶材料数量不能为负数。';
                        break;
                    }
                    if (! Item::query()->where('id', $id)->whereIn('type', ['item', 'material', 'blueprint', 'blueprint_fragment', 'currency'])->exists()) {
                        $errors["economy.refine_cost_by_rarity.{$rarity}.items_rows"] = "进阶材料 {$id} 必须是有效的普通物品。";
                        break;
                    }
                }
            }

            $salvageGold = (int) ($payload['economy']['salvage_reward_by_rarity'][$rarity]['gold'] ?? 0);
            if ($salvageGold < 0) {
                $errors["economy.salvage_reward_by_rarity.{$rarity}.gold"] = '分解金币不能为负数。';
            }
            $salvageItems = $payload['economy']['salvage_reward_by_rarity'][$rarity]['items'] ?? [];
            if (! is_array($salvageItems)) {
                $errors["economy.salvage_reward_by_rarity.{$rarity}.items_rows"] = '分解材料配置格式错误。';
                continue;
            }
            foreach ($salvageItems as $itemId => $count) {
                $id = trim((string) $itemId);
                $qty = (int) $count;
                if ($id === '') {
                    continue;
                }
                if ($qty < 0) {
                    $errors["economy.salvage_reward_by_rarity.{$rarity}.items_rows"] = '分解材料数量不能为负数。';
                    break;
                }
                if (! Item::query()->where('id', $id)->whereIn('type', ['item', 'material', 'blueprint', 'blueprint_fragment', 'currency'])->exists()) {
                    $errors["economy.salvage_reward_by_rarity.{$rarity}.items_rows"] = "分解材料 {$id} 必须是有效的普通物品。";
                    break;
                }
            }
        }

        $pool = $payload['refine_effect_pool'] ?? [];
        if (! is_array($pool) || count($pool) < 1) {
            $errors['refine_effect_pool'] = '进阶词条池至少1条。';
        } else {
            $sumWeight = 0;
            $statAllowed = array_keys($this->statOptions());
            foreach ($pool as $idx => $row) {
                if (! is_array($row)) {
                    $errors['refine_effect_pool'] = '进阶词条池格式错误。';
                    break;
                }
                $w = (int) ($row['w'] ?? -1);
                $val = (int) ($row['val'] ?? -1);
                $type = trim((string) ($row['type'] ?? ''));
                if ($w < 0) {
                    $errors["refine_effect_pool.{$idx}.w"] = '权重不能为负数。';
                }
                if ($val < 0) {
                    $errors["refine_effect_pool.{$idx}.val"] = '数值不能为负数。';
                }
                if (! in_array($type, ['stat', 'skill_level'], true)) {
                    $errors["refine_effect_pool.{$idx}.type"] = '类型仅支持 属性/技能等级。';
                    continue;
                }
                if ($type === 'stat') {
                    $stat = trim((string) ($row['stat'] ?? ''));
                    if ($stat === '' || ! in_array($stat, $statAllowed, true)) {
                        $errors["refine_effect_pool.{$idx}.stat"] = '属性类型无效。';
                    }
                } else {
                    $skillId = trim((string) ($row['skill_id'] ?? ''));
                    if ($skillId === '') {
                        $errors["refine_effect_pool.{$idx}.skill_id"] = '技能不能为空。';
                    } elseif (! \App\Models\SkillCatalog::query()->where('id', $skillId)->where('is_enabled', true)->exists()) {
                        $errors["refine_effect_pool.{$idx}.skill_id"] = '技能不存在或未启用。';
                    }
                }
                $sumWeight += max(0, $w);
            }
            if ($sumWeight <= 0) {
                $errors['refine_effect_pool'] = '进阶词条池权重总和必须大于0。';
            }
        }

        $classes = $payload['classes'] ?? [];
        if (! is_array($classes) || $classes === []) {
            $errors['classes'] = '至少配置1个宗门。';
        } else {
            $seenClassIds = [];
            foreach ($classes as $index => $class) {
                if (! is_array($class)) {
                    $errors["classes.{$index}"] = '宗门配置格式错误。';
                    continue;
                }
                $classId = trim((string) ($class['id'] ?? ''));
                $className = trim((string) ($class['name'] ?? ''));
                if ($classId === '') {
                    $errors["classes.{$index}.id"] = '宗门ID不能为空。';
                } elseif (in_array($classId, $seenClassIds, true)) {
                    $errors["classes.{$index}.id"] = '宗门ID不能重复。';
                } else {
                    $seenClassIds[] = $classId;
                }
                if ($className === '') {
                    $errors["classes.{$index}.name"] = '宗门名称不能为空。';
                }
                if ((int) (($class['starter']['skill_points'] ?? 0)) < 0) {
                    $errors["classes.{$index}.starter.skill_points"] = '默认技能点不能为负数。';
                }

                $baseCombat = is_array($class['base_combat'] ?? null) ? $class['base_combat'] : [];
                if ((float) ($baseCombat['move_speed'] ?? 0) <= 0) {
                    $errors["classes.{$index}.base_combat.move_speed"] = '移速倍率必须大于0。';
                }
                if ((float) ($baseCombat['attack_range'] ?? 0) <= 0) {
                    $errors["classes.{$index}.base_combat.attack_range"] = '普攻距离必须大于0。';
                }
                if ((float) ($baseCombat['basic_attack_cd'] ?? 0) <= 0) {
                    $errors["classes.{$index}.base_combat.basic_attack_cd"] = '普攻间隔必须大于0。';
                }
                $source = trim((string) ($baseCombat['basic_attack_damage']['source'] ?? ''));
                if (! in_array($source, array_keys(AdminOptions::combatDamageSourceOptions()), true)) {
                    $errors["classes.{$index}.base_combat.basic_attack_damage.source"] = '普攻伤害来源非法。';
                }
            }
        }

        if ((float) ($payload['combat']['gcd_seconds'] ?? 0) <= 0) {
            $errors['combat.gcd_seconds'] = '公共施法间隔必须大于0。';
        }
        if ((float) ($payload['combat']['default_skill_coef_per_level'] ?? -1) < 0) {
            $errors['combat.default_skill_coef_per_level'] = '技能默认成长系数不能为负数。';
        }
        $shieldKeep = (float) ($payload['combat']['avoid_waste']['shield_keep_threshold_pct'] ?? -1);
        if ($shieldKeep < 0 || $shieldKeep > 1) {
            $errors['combat.avoid_waste.shield_keep_threshold_pct'] = '护盾保留阈值必须在0~1之间。';
        }

        $profiles = $payload['ai_profiles']['profiles'] ?? [];
        $profileIds = [];
        if (! is_array($profiles) || $profiles === []) {
            $errors['ai_profiles.profiles'] = '至少配置1套AI策略。';
        } else {
            $seenProfileIds = [];
            foreach ($profiles as $index => $profile) {
                if (! is_array($profile)) {
                    $errors["ai_profiles.profiles.{$index}"] = 'AI策略格式错误。';
                    continue;
                }
                $profileId = trim((string) ($profile['id'] ?? ''));
                if ($profileId === '') {
                    $errors["ai_profiles.profiles.{$index}.id"] = '策略ID不能为空。';
                } elseif (in_array($profileId, $seenProfileIds, true)) {
                    $errors["ai_profiles.profiles.{$index}.id"] = '策略ID不能重复。';
                } else {
                    $seenProfileIds[] = $profileId;
                    $profileIds[] = $profileId;
                }
                if (trim((string) ($profile['name'] ?? '')) === '') {
                    $errors["ai_profiles.profiles.{$index}.name"] = '策略名称不能为空。';
                }
            }
        }

        $rules = $payload['ai_profiles']['auto_switch']['rules_in_order'] ?? [];
        if (is_array($rules)) {
            foreach ($rules as $index => $rule) {
                if (! is_array($rule)) {
                    $errors["ai_profiles.auto_switch.rules_in_order.{$index}"] = '自动切换规则格式错误。';
                    continue;
                }
                if (trim((string) ($rule['if'] ?? '')) === '') {
                    $errors["ai_profiles.auto_switch.rules_in_order.{$index}.if"] = '自动切换条件不能为空。';
                }
                if (trim((string) ($rule['profile'] ?? '')) === '') {
                    $errors["ai_profiles.auto_switch.rules_in_order.{$index}.profile"] = '目标策略ID不能为空。';
                } elseif ($profileIds !== [] && ! in_array(trim((string) $rule['profile']), $profileIds, true)) {
                    $errors["ai_profiles.auto_switch.rules_in_order.{$index}.profile"] = '目标策略ID必须已在上方策略列表中配置。';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertChanceRange(array &$errors, string $key, float $value, string $label): void
    {
        if ($value < 0 || $value > 1) {
            $errors[$key] = "{$label}必须在 0~1 之间。";
        }
    }

    private function normalizeIdList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $itemId) {
            $id = trim((string) $itemId);
            if ($id === '') {
                continue;
            }
            $rows[] = $id;
        }

        return array_values(array_unique($rows));
    }

    private function normalizeItemRewardRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }
            $count = max(0, (int) ($row['count'] ?? 0));
            if ($count <= 0) {
                continue;
            }
            $out[$itemId] = (int) ($out[$itemId] ?? 0) + $count;
        }

        return $out;
    }

    private function normalizeRuntimeClasses(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'role' => trim((string) ($row['role'] ?? '')),
                'starter' => [
                    'skill_points' => max(0, (int) ($row['starter']['skill_points'] ?? 1)),
                ],
                'base_combat' => [
                    'move_speed' => (float) ($row['base_combat']['move_speed'] ?? 1),
                    'attack_range' => (float) ($row['base_combat']['attack_range'] ?? 1.8),
                    'basic_attack_cd' => (float) ($row['base_combat']['basic_attack_cd'] ?? 1.2),
                    'basic_attack_damage' => [
                        'source' => trim((string) ($row['base_combat']['basic_attack_damage']['source'] ?? 'WD')),
                        'coef' => (float) ($row['base_combat']['basic_attack_damage']['coef'] ?? 0.7),
                    ],
                    'notes' => trim((string) ($row['base_combat']['notes'] ?? '')),
                ],
            ];
        }

        return array_values($rows);
    }

    private function normalizeAiProfiles(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'priorities' => $this->decodeJsonObjectText($row['priorities_json'] ?? null),
                'cast_conditions' => $this->decodeJsonObjectText($row['cast_conditions_json'] ?? null),
            ];
        }

        return array_values($rows);
    }

    private function normalizeAiRules(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $condition = trim((string) ($row['if'] ?? ''));
            $profile = trim((string) ($row['profile'] ?? ''));
            if ($condition === '' || $profile === '') {
                continue;
            }

            $rows[] = [
                'if' => $condition,
                'profile' => $profile,
            ];
        }

        return array_values($rows);
    }

    private function decodeJsonObjectText(mixed $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    private function normalizeRefineEffectPool(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['type'] ?? ''));
            if (! in_array($type, ['stat', 'skill_level'], true)) {
                continue;
            }
            $item = [
                'w' => max(0, (int) ($row['w'] ?? 0)),
                'type' => $type,
                'val' => max(0, (int) ($row['val'] ?? 0)),
            ];
            if ($type === 'stat') {
                $stat = trim((string) ($row['stat'] ?? ''));
                if ($stat === '') {
                    continue;
                }
                $item['stat'] = $stat;
            } else {
                $skillId = trim((string) ($row['skill_id'] ?? ''));
                if ($skillId === '') {
                    continue;
                }
                $item['skill_id'] = $skillId;
            }
            $rows[] = $item;
        }

        return array_values($rows);
    }

    private function toFormState(array $payload): array
    {
        foreach (['white', 'blue', 'gold'] as $rarity) {
            $refineItems = $payload['economy']['refine_cost_by_rarity'][$rarity]['items'] ?? [];
            $refineRows = [];
            if (is_array($refineItems)) {
                foreach ($refineItems as $itemId => $count) {
                    $id = trim((string) $itemId);
                    if ($id === '') {
                        continue;
                    }
                    $refineRows[] = [
                        'item_id' => $id,
                        'count' => max(0, (int) $count),
                    ];
                }
            }
            $payload['economy']['refine_cost_by_rarity'][$rarity]['items_rows'] = $refineRows;

            $items = $payload['economy']['salvage_reward_by_rarity'][$rarity]['items'] ?? [];
            $rows = [];
            if (is_array($items)) {
                foreach ($items as $itemId => $count) {
                    $id = trim((string) $itemId);
                    if ($id === '') {
                        continue;
                    }
                    $rows[] = [
                        'item_id' => $id,
                        'count' => max(0, (int) $count),
                    ];
                }
            }
            $payload['economy']['salvage_reward_by_rarity'][$rarity]['items_rows'] = $rows;
        }

        $classes = $payload['classes'] ?? [];
        if (is_array($classes)) {
            foreach ($classes as $index => $class) {
                if (! is_array($class)) {
                    continue;
                }
                $payload['classes'][$index]['starter']['skill_points'] = max(0, (int) ($class['starter']['skill_points'] ?? 1));
            }
        }

        $profiles = $payload['ai_profiles']['profiles'] ?? [];
        if (is_array($profiles)) {
            foreach ($profiles as $index => $profile) {
                if (! is_array($profile)) {
                    continue;
                }
                $payload['ai_profiles']['profiles'][$index]['priorities_json'] = $this->prettyJson($profile['priorities'] ?? []);
                $payload['ai_profiles']['profiles'][$index]['cast_conditions_json'] = $this->prettyJson($profile['cast_conditions'] ?? []);
            }
        }

        return $payload;
    }

    private function prettyJson(mixed $value): string
    {
        if (! is_array($value) || $value === []) {
            return '';
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }

    private function itemOptions(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->whereIn('type', ['item', 'material', 'blueprint', 'blueprint_fragment', 'currency'])
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    private function statOptions(): array
    {
        return [
            'HP' => '生命',
            'ATK' => '攻击',
            'DEF' => '防御',
            'CRIT_PERCENT' => '暴击',
            'QI' => '气',
            'LOOT_BONUS_PERCENT' => '掉落',
        ];
    }

    private function skillOptions(): array
    {
        return \App\Models\SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    private function gemOptions(): array
    {
        return Item::query()
            ->where('is_enabled', true)
            ->where('type', 'gem')
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
