<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TalismanResource\Pages;
use App\Models\Talisman;
use App\Models\TalismanStarLink;
use App\Models\TalismanTier;
use App\Models\TalismanTierUpgradeCost;
use App\Support\AdminOptions;
use App\Support\TalismanModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TalismanResource extends Resource
{
    protected static ?string $model = Talisman::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = '护符配置';

    protected static ?string $modelLabel = '护符';

    protected static ?string $pluralModelLabel = '护符配置';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('基础信息')
                    ->description('护符成品统一由 items.item_id 承接；recommended_sect 仅做推荐，不做限制。')
                    ->schema([
                        TextInput::make('talisman_id')
                            ->label('护符 ID')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true, column: 'talisman_id')
                            ->disabled(fn (?Talisman $record): bool => $record !== null)
                            ->helperText('护符业务唯一 ID，建议创建后保持稳定。'),
                        Select::make('item_id')
                            ->label('成品物品')
                            ->options(fn (Get $get): array => TalismanModuleSupport::carrierItemOptions((string) $get('talisman_type')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true, column: 'item_id')
                            ->disabled(fn (?Talisman $record): bool => $record !== null)
                            ->helperText('只允许选择 `main_type = talisman` 且 `sub_type` 与当前 talisman_type 一致的 item。'),
                        TextInput::make('talisman_name')
                            ->label('内部名称')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('display_name')
                            ->label('展示名称')
                            ->required()
                            ->maxLength(255),
                        Select::make('talisman_type')
                            ->label('护符类型')
                            ->options(Talisman::TALISMAN_TYPE_OPTIONS)
                            ->required()
                            ->default('common_talisman')
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('item_id', null);
                                if ($state === 'common_talisman') {
                                    $set('recommended_sect', 'none');
                                }
                            })
                            ->helperText('首版仅支持 common_talisman / sect_talisman。'),
                        Select::make('recommended_sect')
                            ->label('推荐宗门')
                            ->options(Talisman::RECOMMENDED_SECT_OPTIONS)
                            ->required()
                            ->default('none')
                            ->helperText('仅作推荐展示，不做宗门强制限制。'),
                        TextInput::make('icon')
                            ->label('图标')
                            ->maxLength(255),
                        Textarea::make('summary')
                            ->label('效果简述')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('quality')
                            ->label('玩法品质')
                            ->options(AdminOptions::qualityOptions())
                            ->required()
                            ->default('blue'),
                        Select::make('rarity')
                            ->label('展示稀有度')
                            ->options(AdminOptions::rarityOptions())
                            ->required()
                            ->default('blue'),
                        TextInput::make('unlock_level')
                            ->label('解锁等级')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->default(40),
                        TextInput::make('sort_order')
                            ->label('排序')
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->default(0),
                        Toggle::make('is_enabled')
                            ->label('启用')
                            ->default(true),
                        Textarea::make('remark')
                            ->label('备注')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(4),
                Section::make('阶级配置')
                    ->description('护符固定 3 阶：1 / 2 / 3。首版以常驻型为主，允许少量 on_skill_cast / on_hit / low_hp 触发型。')
                    ->schema([
                        static::tiersRepeater(),
                    ]),
                Section::make('升阶消耗')
                    ->description('仅配置 1->2 与 2->3 的升阶消耗。支持多条记录表达多种材料，3 阶不再有升级消耗。')
                    ->schema([
                        static::upgradeCostsRepeater(),
                    ]),
                Section::make('星级连锁')
                    ->description('关键规则：护符星级连锁所要求的“全身装备达到 X 星”，指所有参与统计的装备位都至少达到该星级。护符位本身不参与该统计。每个阶级都应配置 6 / 8 / 9 / 10 星连锁。')
                    ->schema([
                        static::starLinksRepeater(),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('talisman_id')->label('护符 ID')->searchable()->sortable(),
                TextColumn::make('item_id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('talisman_type')
                    ->label('护符类型')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(Talisman::TALISMAN_TYPE_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('recommended_sect')
                    ->label('推荐宗门')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(Talisman::RECOMMENDED_SECT_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('quality')
                    ->label('玩法品质')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::qualityOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                IconColumn::make('is_enabled')->label('启用')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('talisman_type')->label('护符类型')->options(Talisman::TALISMAN_TYPE_OPTIONS),
                Tables\Filters\SelectFilter::make('recommended_sect')->label('推荐宗门')->options(Talisman::RECOMMENDED_SECT_OPTIONS),
                Tables\Filters\SelectFilter::make('quality')->label('玩法品质')->options(AdminOptions::qualityOptions()),
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
            'index' => Pages\ListTalismans::route('/'),
            'create' => Pages\CreateTalisman::route('/create'),
            'edit' => Pages\EditTalisman::route('/{record}/edit'),
        ];
    }

    public static function tiersForForm(Talisman $record): array
    {
        return $record->tiers()
            ->orderBy('tier_no')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TalismanTier $tier): array => [
                'talisman_id' => (string) $tier->talisman_id,
                'tier_no' => (int) $tier->tier_no,
                'tier_name' => (string) $tier->tier_name,
                'effect_key' => (string) $tier->effect_key,
                'value_type' => (string) $tier->value_type,
                'value' => TalismanModuleSupport::normalizeNumericValue((float) $tier->value),
                'trigger_rule' => (string) $tier->trigger_rule,
                'cooldown_sec' => (int) $tier->cooldown_sec,
                'summary' => $tier->summary !== null ? (string) $tier->summary : null,
                'sort_order' => (int) $tier->sort_order,
                'is_enabled' => (bool) $tier->is_enabled,
                'remark' => $tier->remark !== null ? (string) $tier->remark : null,
            ])
            ->all();
    }

    public static function upgradeCostsForForm(Talisman $record): array
    {
        return $record->upgradeCosts()
            ->orderBy('tier_no')
            ->orderBy('target_tier_no')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TalismanTierUpgradeCost $cost): array => [
                'talisman_id' => (string) $cost->talisman_id,
                'tier_no' => (int) $cost->tier_no,
                'target_tier_no' => (int) $cost->target_tier_no,
                'item_id' => (string) $cost->item_id,
                'count' => (int) $cost->count,
                'sort_order' => (int) $cost->sort_order,
                'is_enabled' => (bool) $cost->is_enabled,
                'remark' => $cost->remark !== null ? (string) $cost->remark : null,
            ])
            ->all();
    }

    public static function starLinksForForm(Talisman $record): array
    {
        return $record->starLinks()
            ->orderBy('tier_no')
            ->orderBy('required_equipment_star')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TalismanStarLink $link): array => [
                'talisman_id' => (string) $link->talisman_id,
                'tier_no' => (int) $link->tier_no,
                'required_equipment_star' => (int) $link->required_equipment_star,
                'effect_key' => (string) $link->effect_key,
                'value_type' => (string) $link->value_type,
                'value' => TalismanModuleSupport::normalizeNumericValue((float) $link->value),
                'summary' => $link->summary !== null ? (string) $link->summary : null,
                'sort_order' => (int) $link->sort_order,
                'is_enabled' => (bool) $link->is_enabled,
                'remark' => $link->remark !== null ? (string) $link->remark : null,
            ])
            ->all();
    }

    /**
     * @return array{talisman: array<string, mixed>, tiers: array<int, array<string, mixed>>, upgrade_costs: array<int, array<string, mixed>>, star_links: array<int, array<string, mixed>>}
     */
    public static function normalizeFormDataOrFail(array $data): array
    {
        return TalismanModuleSupport::normalizeSingleTalismanFormOrFail($data);
    }

    public static function syncRelations(Talisman $record, array $tiers, array $upgradeCosts, array $starLinks): void
    {
        $record->tiers()->delete();
        $record->upgradeCosts()->delete();
        $record->starLinks()->delete();

        foreach ($tiers as $tier) {
            $record->tiers()->create($tier);
        }

        foreach ($upgradeCosts as $cost) {
            $record->upgradeCosts()->create($cost);
        }

        foreach ($starLinks as $link) {
            $record->starLinks()->create($link);
        }
    }

    private static function tiersRepeater(): Repeater
    {
        return Repeater::make('tiers')
            ->label('阶级条目')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->itemLabel(fn (array $state): ?string => filled($state['tier_no'] ?? null) ? sprintf('%s阶', (string) $state['tier_no']) : '阶级')
            ->schema([
                Select::make('tier_no')
                    ->label('阶级')
                    ->options(self::tierOptions())
                    ->required()
                    ->helperText('固定只允许 1 / 2 / 3。'),
                TextInput::make('tier_name')
                    ->label('阶级名称')
                    ->required()
                    ->maxLength(255),
                Select::make('effect_key')
                    ->label('效果 Key')
                    ->options(Talisman::EFFECT_KEY_OPTIONS)
                    ->searchable()
                    ->required(),
                Select::make('value_type')
                    ->label('数值类型')
                    ->options(Talisman::VALUE_TYPE_OPTIONS)
                    ->required()
                    ->default('flat'),
                TextInput::make('value')
                    ->label('数值')
                    ->numeric()
                    ->step(0.0001)
                    ->required(),
                Select::make('trigger_rule')
                    ->label('触发规则')
                    ->options(TalismanTier::TRIGGER_RULE_OPTIONS)
                    ->required()
                    ->default('passive_always'),
                TextInput::make('cooldown_sec')
                    ->label('冷却秒数')
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->default(0)
                    ->helperText('常驻型建议填 0。'),
                Textarea::make('summary')
                    ->label('效果说明')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_enabled')
                    ->label('启用')
                    ->default(true),
                Textarea::make('remark')
                    ->label('备注')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(4)
            ->columnSpanFull();
    }

    private static function upgradeCostsRepeater(): Repeater
    {
        return Repeater::make('upgrade_costs')
            ->label('升阶消耗')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->itemLabel(fn (array $state): ?string => filled($state['tier_no'] ?? null) && filled($state['target_tier_no'] ?? null)
                ? sprintf('%s -> %s', (string) $state['tier_no'], (string) $state['target_tier_no'])
                : '升阶消耗')
            ->schema([
                Select::make('tier_no')
                    ->label('当前阶级')
                    ->options(self::tierOptions([1, 2]))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                        $set('target_tier_no', $state !== null ? ((int) $state + 1) : null);
                    }),
                Select::make('target_tier_no')
                    ->label('目标阶级')
                    ->options(self::tierOptions([2, 3]))
                    ->required()
                    ->helperText('只允许 1->2、2->3。'),
                Select::make('item_id')
                    ->label('消耗物品')
                    ->options(fn (): array => TalismanModuleSupport::upgradeCostItemOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('当前仅允许选择 material 类型 item。'),
                TextInput::make('count')
                    ->label('数量')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->default(1),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_enabled')
                    ->label('启用')
                    ->default(true),
                Textarea::make('remark')
                    ->label('备注')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    private static function starLinksRepeater(): Repeater
    {
        return Repeater::make('star_links')
            ->label('星级连锁')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->itemLabel(fn (array $state): ?string => filled($state['tier_no'] ?? null) && filled($state['required_equipment_star'] ?? null)
                ? sprintf('%s阶｜%s星', (string) $state['tier_no'], (string) $state['required_equipment_star'])
                : '星级连锁')
            ->schema([
                Select::make('tier_no')
                    ->label('护符阶级')
                    ->options(self::tierOptions())
                    ->required(),
                Select::make('required_equipment_star')
                    ->label('要求装备星级')
                    ->options(self::starThresholdOptions())
                    ->required()
                    ->helperText('关键规则：护符星级连锁所要求的“全身装备达到 X 星”，指所有参与统计的装备位都至少达到该星级。护符位本身不参与该统计。'),
                Select::make('effect_key')
                    ->label('效果 Key')
                    ->options(Talisman::EFFECT_KEY_OPTIONS)
                    ->searchable()
                    ->required(),
                Select::make('value_type')
                    ->label('数值类型')
                    ->options(Talisman::VALUE_TYPE_OPTIONS)
                    ->required()
                    ->default('percent'),
                TextInput::make('value')
                    ->label('数值')
                    ->numeric()
                    ->step(0.0001)
                    ->required(),
                Textarea::make('summary')
                    ->label('效果说明')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_enabled')
                    ->label('启用')
                    ->default(true),
                Textarea::make('remark')
                    ->label('备注')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    /**
     * @param  array<int>|null  $tiers
     * @return array<int, string>
     */
    private static function tierOptions(?array $tiers = null): array
    {
        $tiers ??= Talisman::FIXED_TIER_NUMBERS;

        return collect($tiers)
            ->mapWithKeys(fn (int $tier): array => [$tier => sprintf('%s阶', $tier)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function starThresholdOptions(): array
    {
        return collect(Talisman::STAR_LINK_THRESHOLDS)
            ->mapWithKeys(fn (int $star): array => [$star => sprintf('%s星', $star)])
            ->all();
    }
}
