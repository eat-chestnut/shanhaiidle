<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipTemplateResource\Pages;
use App\Models\EquipTemplate;
use App\Models\EquipmentSet;
use App\Models\Item;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EquipTemplateResource extends Resource
{
    protected static ?string $model = EquipTemplate::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = '装备模板';

    protected static ?string $pluralModelLabel = '装备模板';

    protected static ?string $modelLabel = '装备模板';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('EquipTemplateTabs')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('模板基础信息')
                                    ->description('基础资料统一使用预设选项，避免录入裸 ID 或混杂技术字段。')
                                    ->columns(12)
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('模板ID')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(ignoreRecord: true)
                                            ->disabled(fn (?EquipTemplate $record): bool => $record !== null)
                                            ->columnSpan(4),
                                        TextInput::make('name')
                                            ->label('名称')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        Select::make('slot')
                                            ->label('部位')
                                            ->required()
                                            ->options(AdminOptions::slotOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->columnSpan(4),
                                        Select::make('equip_type')
                                            ->label('装备归类')
                                            ->required()
                                            ->default('set')
                                            ->options(AdminOptions::equipTypeOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->columnSpan(4),
                                        Select::make('rarity')
                                            ->label('稀有度')
                                            ->required()
                                            ->options(AdminOptions::rarityOptions())
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(4),
                                        TextInput::make('required_level')
                                            ->label('需求等级')
                                            ->integer()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->columnSpan(4),
                                        Select::make('set_id')
                                            ->label('所属套装配置')
                                            ->options(fn (): array => AdminOptions::equipmentSetOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if (blank($state)) {
                                                    $set('set_line_id', null);
                                                    $set('set_stage', null);
                                                    return;
                                                }

                                                $equipmentSet = EquipmentSet::query()->find($state);
                                                if (! $equipmentSet instanceof EquipmentSet) {
                                                    $set('set_line_id', null);
                                                    $set('set_stage', null);
                                                    return;
                                                }

                                                $set('set_line_id', $equipmentSet->set_line_id);
                                                $set('set_stage', $equipmentSet->stage);
                                            })
                                            ->columnSpan(6),
                                        Select::make('set_line_id')
                                            ->label('套装线')
                                            ->options(fn (): array => AdminOptions::setLineOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->live()
                                            ->disabled(fn (Get $get): bool => filled($get('set_id')))
                                            ->dehydrated(fn (Get $get): bool => blank($get('set_id')))
                                            ->helperText(fn (Get $get): string => filled($get('set_id'))
                                                ? '已随所属套装配置自动联动，不能单独修改。'
                                                : '未选择所属套装配置时，可单独指定套装线。')
                                            ->columnSpan(3),
                                        Select::make('set_stage')
                                            ->label('套装阶段')
                                            ->options(AdminOptions::setStageOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->live()
                                            ->disabled(fn (Get $get): bool => filled($get('set_id')))
                                            ->dehydrated(fn (Get $get): bool => blank($get('set_id')))
                                            ->helperText(fn (Get $get): string => filled($get('set_id'))
                                                ? '已随所属套装配置自动联动，不能单独修改。'
                                                : '仅支持 20级 / 40级 / 60级。')
                                            ->columnSpan(3),
                                    ]),
                                Section::make('资源与状态')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('sort_order')
                                            ->label('排序')
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->default(0),
                                        FileUpload::make('icon')
                                            ->label('图标')
                                            ->disk('public')
                                            ->directory('config/equip-template-icons')
                                            ->image()
                                            ->imagePreviewHeight('120')
                                            ->columnSpan(6),
                                        Toggle::make('is_enabled')
                                            ->label('启用')
                                            ->default(true)
                                            ->columnSpan(3),
                                    ]),
                            ]),
                        Tab::make('白字属性与成长')
                            ->schema([
                                Section::make('白色基础属性')
                                    ->description('每条属性统一按“选择属性 + 填写数值”录入。')
                                    ->schema([
                                        static::statEntryRepeater(
                                            name: 'white_stats',
                                            label: '白色基础属性',
                                            valueLabel: '数值',
                                            addActionLabel: '新增白色基础属性',
                                            minItems: 1,
                                        ),
                                    ]),
                                Section::make('每星成长')
                                    ->description('每星成长只维护属性与增量，不再录入旧主属性或 JSON 原文。')
                                    ->schema([
                                        static::statEntryRepeater(
                                            name: 'star_growth',
                                            label: '每星成长',
                                            valueLabel: '每星增量',
                                            addActionLabel: '新增成长项',
                                        ),
                                    ]),
                                Section::make('成长开关与孔位')
                                    ->columns(12)
                                    ->schema([
                                        Toggle::make('star_enabled')
                                            ->label('可升星')
                                            ->default(true)
                                            ->columnSpan(3),
                                        TextInput::make('star_cap')
                                            ->label('升星上限')
                                            ->integer()
                                            ->minValue(0)
                                            ->maxValue(10)
                                            ->default(10)
                                            ->required()
                                            ->columnSpan(3),
                                        Select::make('socket_rule_ref')
                                            ->label('孔位规则')
                                            ->options(static::socketRuleOptions())
                                            ->default('fixed_star_3_6_8_10')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->helperText('当前正式规则：3星第1孔、6星第2孔、8星第3孔、10星第4孔。')
                                            ->columnSpan(6),
                                        Toggle::make('can_attach_blue_affix')
                                            ->label('可附加蓝词条')
                                            ->default(false)
                                            ->columnSpan(3),
                                        Toggle::make('can_roll_purple_affix')
                                            ->label('可洗练紫词条')
                                            ->default(false)
                                            ->columnSpan(3),
                                    ]),
                            ]),
                        Tab::make('打造与升品')
                            ->schema([
                                Section::make('打造配置')
                                    ->description('所有模板、图纸、系列关联统一通过选择框维护。')
                                    ->columns(12)
                                    ->schema([
                                        Toggle::make('forge_enabled')
                                            ->label('可打造')
                                            ->default(false)
                                            ->columnSpan(3),
                                        Select::make('quality_tier')
                                            ->label('品质层级')
                                            ->options(static::qualityTierOptions())
                                            ->default('normal')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->columnSpan(3),
                                        Select::make('forge_tier')
                                            ->label('打造段位')
                                            ->options(static::forgeTierOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(3),
                                        Select::make('slot_group')
                                            ->label('部位组')
                                            ->options(AdminOptions::slotGroupOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(3),
                                        Select::make('theme_key')
                                            ->label('主题')
                                            ->options(AdminOptions::themeOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(3),
                                        Select::make('forge_family_id')
                                            ->label('打造系列')
                                            ->options(fn (): array => AdminOptions::forgeSeriesOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->live()
                                            ->helperText('同系列普通装与高品质装共享同一打造系列。')
                                            ->columnSpan(3),
                                        Select::make('upgrade_from_template_id')
                                            ->label('升品来源模板')
                                            ->options(fn (Get $get): array => static::resolveUpgradeFromTemplateOptions($get))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(6),
                                        Select::make('upgrade_to_template_id')
                                            ->label('可升到模板')
                                            ->options(fn (Get $get): array => static::resolveUpgradeToTemplateOptions($get))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(6),
                                        Select::make('blueprint_item_id')
                                            ->label('图纸物品')
                                            ->options(fn (Get $get): array => static::resolveBlueprintItemOptions($get))
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(6),
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
                TextColumn::make('id')->label('模板ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slot')
                    ->label('部位')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::slotOptions(), $state))
                    ->sortable(),
                TextColumn::make('equip_type')
                    ->label('装备归类')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::equipTypeOptions(), $state))
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('稀有度')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state))
                    ->sortable(),
                TextColumn::make('quality_tier')
                    ->label('品质层级')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(static::qualityTierOptions(), $state))
                    ->sortable(),
                TextColumn::make('required_level')->label('需求等级')->numeric()->sortable(),
                ToggleColumn::make('forge_enabled')->label('可打造')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot')->label('部位')->options(AdminOptions::slotOptions()),
                Tables\Filters\SelectFilter::make('equip_type')->label('装备归类')->options(AdminOptions::equipTypeOptions()),
                Tables\Filters\SelectFilter::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions()),
                Tables\Filters\SelectFilter::make('quality_tier')->label('品质层级')->options(static::qualityTierOptions()),
                Tables\Filters\SelectFilter::make('forge_tier')->label('打造段位')->options(static::forgeTierOptions()),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipTemplates::route('/'),
            'create' => Pages\CreateEquipTemplate::route('/create'),
            'edit' => Pages\EditEquipTemplate::route('/{record}/edit'),
        ];
    }

    public static function normalizeFormData(array $data): array
    {
        foreach ([
            'id',
            'slot',
            'equip_type',
            'quality_tier',
            'set_id',
            'set_line_id',
            'forge_tier',
            'slot_group',
            'theme_key',
            'forge_family_id',
            'upgrade_from_template_id',
            'upgrade_to_template_id',
            'blueprint_item_id',
            'icon',
        ] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        static::validateStatEntriesOrFail($data['white_stats'] ?? [], 'white_stats', '白色基础属性');
        static::validateStatEntriesOrFail($data['star_growth'] ?? [], 'star_growth', '每星成长');

        $data['white_stats'] = static::normalizeStatEntries($data['white_stats'] ?? []);
        $data['star_growth'] = static::normalizeStatEntries($data['star_growth'] ?? []);
        $data['socket_rule_ref'] = filled($data['socket_rule_ref'] ?? null)
            ? trim((string) $data['socket_rule_ref'])
            : 'fixed_star_3_6_8_10';
        $data['set_stage'] = filled($data['set_stage'] ?? null) ? (int) $data['set_stage'] : null;
        $data['required_level'] = filled($data['required_level'] ?? null) ? (int) $data['required_level'] : null;

        $data = static::normalizeSetRelationDataOrFail($data);
        static::validateUpgradeRelationsOrFail($data);

        return $data;
    }

    public static function normalizeStatEntries(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $rows = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stat = trim((string) ($row['stat'] ?? ''));
            if ($stat === '') {
                continue;
            }

            $normalized = [
                'stat' => $stat,
                'value' => max(0, (int) ($row['value'] ?? 0)),
            ];

            $rows[] = $normalized;
        }

        return array_values($rows);
    }

    protected static function validateStatEntriesOrFail(mixed $raw, string $field, string $fieldLabel): void
    {
        if (! is_array($raw)) {
            return;
        }

        $duplicates = [];
        $seen = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stat = trim((string) ($row['stat'] ?? ''));
            if ($stat === '') {
                continue;
            }

            if (isset($seen[$stat])) {
                $duplicates[$stat] = static::displayStatLabel($stat);
                continue;
            }

            $seen[$stat] = true;
        }

        if ($duplicates === []) {
            return;
        }

        throw ValidationException::withMessages([
            $field => sprintf('%s中存在重复属性：%s', $fieldLabel, implode('、', array_values($duplicates))),
        ]);
    }

    protected static function normalizeSetRelationDataOrFail(array $data): array
    {
        $allowedStages = array_map('intval', array_keys(AdminOptions::setStageOptions()));
        $setId = static::nullableString($data['set_id'] ?? null);
        $setLineId = static::nullableString($data['set_line_id'] ?? null);
        $setStage = isset($data['set_stage']) && $data['set_stage'] !== null ? (int) $data['set_stage'] : null;

        if ($setStage !== null && ! in_array($setStage, $allowedStages, true)) {
            throw ValidationException::withMessages([
                'set_stage' => '套装阶段只支持 20级 / 40级 / 60级。',
            ]);
        }

        if ($setId !== null) {
            $equipmentSet = EquipmentSet::query()->find($setId);
            if (! $equipmentSet instanceof EquipmentSet) {
                throw ValidationException::withMessages([
                    'set_id' => '所选套装配置不存在，请重新选择。',
                ]);
            }

            $data['set_id'] = $setId;
            $data['set_line_id'] = static::nullableString($equipmentSet->set_line_id);
            $data['set_stage'] = (int) $equipmentSet->stage;

            return $data;
        }

        if (($setLineId === null) xor ($setStage === null)) {
            throw ValidationException::withMessages([
                'set_line_id' => '未选择所属套装配置时，套装线和套装阶段必须同时填写，或同时留空。',
                'set_stage' => '未选择所属套装配置时，套装线和套装阶段必须同时填写，或同时留空。',
            ]);
        }

        $data['set_id'] = null;
        $data['set_line_id'] = $setLineId;
        $data['set_stage'] = $setStage;

        return $data;
    }

    protected static function validateUpgradeRelationsOrFail(array $data): void
    {
        $messages = [];
        $upgradeFrom = static::nullableString($data['upgrade_from_template_id'] ?? null);
        $upgradeTo = static::nullableString($data['upgrade_to_template_id'] ?? null);
        $blueprintItemId = static::nullableString($data['blueprint_item_id'] ?? null);

        if ($upgradeFrom !== null && ! array_key_exists($upgradeFrom, static::resolveUpgradeFromTemplateOptionsFromData($data))) {
            $messages['upgrade_from_template_id'] = '升品来源模板与当前模板的部位、归类或品质链不匹配，请重新选择。';
        }

        if ($upgradeTo !== null && ! array_key_exists($upgradeTo, static::resolveUpgradeToTemplateOptionsFromData($data))) {
            $messages['upgrade_to_template_id'] = '可升到模板与当前模板的部位、归类或品质链不匹配，请重新选择。';
        }

        if ($blueprintItemId !== null && ! array_key_exists($blueprintItemId, static::resolveBlueprintItemOptionsFromData($data))) {
            $messages['blueprint_item_id'] = '图纸物品与当前模板的部位或套装上下文不匹配，请重新选择。';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected static function resolveUpgradeFromTemplateOptions(Get $get): array
    {
        return static::resolveUpgradeFromTemplateOptionsFromData(static::extractRelationContextFromGet($get));
    }

    protected static function resolveUpgradeFromTemplateOptionsFromData(array $data): array
    {
        if (($data['quality_tier'] ?? null) !== 'high' || blank($data['slot'] ?? null) || blank($data['equip_type'] ?? null)) {
            return [];
        }

        $query = static::buildRelatedTemplateQuery($data)
            ->where('quality_tier', 'normal');

        static::applyUpgradeContextFilters($query, $data);

        return static::mapTemplateOptions($query);
    }

    protected static function resolveUpgradeToTemplateOptions(Get $get): array
    {
        return static::resolveUpgradeToTemplateOptionsFromData(static::extractRelationContextFromGet($get));
    }

    protected static function resolveUpgradeToTemplateOptionsFromData(array $data): array
    {
        if (($data['quality_tier'] ?? null) !== 'normal' || blank($data['slot'] ?? null) || blank($data['equip_type'] ?? null)) {
            return [];
        }

        $query = static::buildRelatedTemplateQuery($data)
            ->where('quality_tier', 'high');

        static::applyUpgradeContextFilters($query, $data);

        return static::mapTemplateOptions($query);
    }

    protected static function resolveBlueprintItemOptions(Get $get): array
    {
        return static::resolveBlueprintItemOptionsFromData(static::extractRelationContextFromGet($get));
    }

    protected static function resolveBlueprintItemOptionsFromData(array $data): array
    {
        if (blank($data['slot'] ?? null) || blank($data['equip_type'] ?? null)) {
            return [];
        }

        $candidateIds = static::resolveBlueprintCandidateIds($data);
        if ($candidateIds === []) {
            return [];
        }

        return Item::query()
            ->where('is_enabled', true)
            ->where('type', 'blueprint')
            ->whereIn('item_id', $candidateIds)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->mapWithKeys(fn (Item $item): array => [$item->item_id => (string) $item->display_name])
            ->all();
    }

    protected static function extractRelationContextFromGet(Get $get): array
    {
        return static::hydrateSetContext([
            'id' => static::nullableString($get('id')),
            'slot' => static::nullableString($get('slot')),
            'equip_type' => static::nullableString($get('equip_type')),
            'quality_tier' => static::nullableString($get('quality_tier')),
            'required_level' => filled($get('required_level')) ? (int) $get('required_level') : null,
            'set_id' => static::nullableString($get('set_id')),
            'set_line_id' => static::nullableString($get('set_line_id')),
            'set_stage' => filled($get('set_stage')) ? (int) $get('set_stage') : null,
            'forge_family_id' => static::nullableString($get('forge_family_id')),
        ]);
    }

    protected static function hydrateSetContext(array $data): array
    {
        $setId = static::nullableString($data['set_id'] ?? null);
        if ($setId === null) {
            return $data;
        }

        $equipmentSet = EquipmentSet::query()->find($setId);
        if (! $equipmentSet instanceof EquipmentSet) {
            return $data;
        }

        $data['set_line_id'] = static::nullableString($equipmentSet->set_line_id);
        $data['set_stage'] = (int) $equipmentSet->stage;

        return $data;
    }

    protected static function buildRelatedTemplateQuery(array $data): Builder
    {
        $query = EquipTemplate::query()
            ->where('is_enabled', true);

        if (filled($data['slot'] ?? null)) {
            $query->where('slot', (string) $data['slot']);
        }

        if (filled($data['equip_type'] ?? null)) {
            $query->where('equip_type', (string) $data['equip_type']);
        }

        $currentId = static::nullableString($data['id'] ?? null);
        if ($currentId !== null) {
            $query->where('id', '!=', $currentId);
        }

        return $query;
    }

    protected static function applyUpgradeContextFilters(Builder $query, array $data): void
    {
        if (filled($data['forge_family_id'] ?? null)) {
            $query->where('forge_family_id', (string) $data['forge_family_id']);
        }

        if (filled($data['set_line_id'] ?? null)) {
            $query->where('set_line_id', (string) $data['set_line_id']);
        }

        if (($data['set_stage'] ?? null) !== null) {
            $query->where('set_stage', (int) $data['set_stage']);
        }

        if (($data['required_level'] ?? null) !== null) {
            $query->where('required_level', (int) $data['required_level']);
        }
    }

    protected static function mapTemplateOptions(Builder $query): array
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (EquipTemplate $template): array => [$template->id => (string) $template->name])
            ->all();
    }

    protected static function resolveBlueprintCandidateIds(array $data): array
    {
        $query = EquipTemplate::query()
            ->where('is_enabled', true)
            ->whereNotNull('blueprint_item_id')
            ->where('blueprint_item_id', '!=', '');

        if (filled($data['slot'] ?? null)) {
            $query->where('slot', (string) $data['slot']);
        }

        if (filled($data['equip_type'] ?? null)) {
            $query->where('equip_type', (string) $data['equip_type']);
        }

        if (filled($data['forge_family_id'] ?? null)) {
            $query->where('forge_family_id', (string) $data['forge_family_id']);
        }

        if (filled($data['set_line_id'] ?? null)) {
            $query->where('set_line_id', (string) $data['set_line_id']);
        }

        if (($data['set_stage'] ?? null) !== null) {
            $query->where('set_stage', (int) $data['set_stage']);
        } elseif (($data['required_level'] ?? null) !== null) {
            $query->where('required_level', (int) $data['required_level']);
        }

        $candidateIds = $query
            ->orderBy('sort_order')
            ->pluck('blueprint_item_id')
            ->filter(fn (?string $value): bool => filled($value))
            ->unique()
            ->values()
            ->all();

        if ($candidateIds !== []) {
            return $candidateIds;
        }

        $derivedSetBlueprintId = static::resolveDerivedSetBlueprintId($data);
        if ($derivedSetBlueprintId !== null) {
            return [$derivedSetBlueprintId];
        }

        return [];
    }

    protected static function resolveDerivedSetBlueprintId(array $data): ?string
    {
        $setLineId = static::nullableString($data['set_line_id'] ?? null);
        $setStage = isset($data['set_stage']) && $data['set_stage'] !== null ? (int) $data['set_stage'] : null;

        if ($setLineId === null || ! in_array($setStage, [40, 60], true)) {
            return null;
        }

        $itemId = sprintf('bp_%s_%d', str_replace('setline_', 'set_', $setLineId), $setStage);

        return Item::query()
            ->where('is_enabled', true)
            ->where('type', 'blueprint')
            ->where('item_id', $itemId)
            ->value('item_id');
    }

    protected static function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    protected static function displayStatLabel(string $stat): string
    {
        return trim((string) preg_replace('/（[^）]*）/u', '', AdminOptions::optionLabel(AdminOptions::statOptions(), $stat)));
    }

    protected static function statEntryRepeater(
        string $name,
        string $label,
        string $valueLabel,
        string $addActionLabel,
        int $minItems = 0,
    ): Repeater {
        $repeater = Repeater::make($name)
            ->hiddenLabel()
            ->default([])
            ->columns(12)
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->table([
                TableColumn::make('属性'),
                TableColumn::make($valueLabel),
            ])
            ->schema([
                Select::make('stat')
                    ->options(AdminOptions::statOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(7),
                TextInput::make('value')
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->default(0)
                    ->columnSpan(5),
            ])
            ->addActionLabel($addActionLabel)
            ->columnSpanFull();

        if ($minItems > 0) {
            $repeater->minItems($minItems);
        }

        return $repeater;
    }

    protected static function qualityTierOptions(): array
    {
        return [
            'normal' => '普通',
            'high' => '高品质',
            'blue_drop' => '蓝装掉落',
        ];
    }

    protected static function forgeTierOptions(): array
    {
        return [
            'T1' => 'T1（20级）',
            'T2' => 'T2（40级）',
            'T3' => 'T3（50级）',
            'T4' => 'T4（60级）',
        ];
    }

    protected static function socketRuleOptions(): array
    {
        return [
            'fixed_star_3_6_8_10' => '固定开孔规则（3/6/8/10星）',
        ];
    }
}
