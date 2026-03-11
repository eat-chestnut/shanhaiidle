<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipTemplateResource\Pages;
use App\Models\EquipTemplate;
use App\Models\EquipmentSet;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipTemplateResource extends Resource
{
    protected static ?string $model = EquipTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = '装备模板';

    protected static ?string $pluralModelLabel = '装备模板';

    protected static ?string $modelLabel = '装备模板';

    protected static ?string $navigationGroup = '装备成长';

    public static function form(Form $form): Form
    {
        return $form
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
                                            ->columnSpan(4),
                                        Select::make('equip_type')
                                            ->label('装备归类')
                                            ->required()
                                            ->default('set')
                                            ->options(AdminOptions::equipTypeOptions())
                                            ->searchable()
                                            ->preload()
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
                                            ->columnSpan(3),
                                        Select::make('set_stage')
                                            ->label('套装阶段')
                                            ->options(AdminOptions::setStageOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(3),
                                    ]),
                                Section::make('资源与状态')
                                    ->columns(12)
                                    ->schema([
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
                                        TextInput::make('sort_order')
                                            ->label('排序')
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->default(0)
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
                                            ->helperText('同系列普通装与高品质装共享同一打造系列。')
                                            ->columnSpan(3),
                                        Select::make('upgrade_from_template_id')
                                            ->label('升品来源模板')
                                            ->options(fn (): array => AdminOptions::equipTemplateOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(6),
                                        Select::make('upgrade_to_template_id')
                                            ->label('可升到模板')
                                            ->options(fn (): array => AdminOptions::equipTemplateOptions())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpan(6),
                                        Select::make('blueprint_item_id')
                                            ->label('图纸物品')
                                            ->options(fn (): array => AdminOptions::itemOptions(
                                                fn ($query) => $query->where('type', 'blueprint')
                                            ))
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
            'index' => Pages\ListEquipTemplates::route('/'),
            'create' => Pages\CreateEquipTemplate::route('/create'),
            'edit' => Pages\EditEquipTemplate::route('/{record}/edit'),
        ];
    }

    public static function normalizeFormData(array $data): array
    {
        $data['white_stats'] = static::normalizeStatEntries($data['white_stats'] ?? []);
        $data['star_growth'] = static::normalizeStatEntries($data['star_growth'] ?? []);
        $data['socket_rule_ref'] = filled($data['socket_rule_ref'] ?? null)
            ? trim((string) $data['socket_rule_ref'])
            : 'fixed_star_3_6_8_10';

        foreach ([
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

        $data['set_stage'] = filled($data['set_stage'] ?? null) ? (int) $data['set_stage'] : null;

        return $data;
    }

    public static function normalizeStatEntries(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        $indexes = [];

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

            if (array_key_exists($stat, $indexes)) {
                $rows[$indexes[$stat]] = $normalized;

                continue;
            }

            $indexes[$stat] = count($rows);
            $rows[] = $normalized;
        }

        return array_values($rows);
    }

    protected static function statEntryRepeater(
        string $name,
        string $label,
        string $valueLabel,
        string $addActionLabel,
        int $minItems = 0,
    ): Repeater {
        $repeater = Repeater::make($name)
            ->label($label)
            ->default([])
            ->columns(12)
            ->reorderableWithButtons()
            ->itemLabel(function (array $state) use ($valueLabel): ?string {
                $stat = trim((string) ($state['stat'] ?? ''));
                if ($stat === '') {
                    return null;
                }

                return sprintf(
                    '%s %s %d',
                    AdminOptions::optionLabel(AdminOptions::statOptions(), $stat),
                    $valueLabel,
                    max(0, (int) ($state['value'] ?? 0)),
                );
            })
            ->schema([
                Select::make('stat')
                    ->label('属性')
                    ->options(AdminOptions::statOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(7),
                TextInput::make('value')
                    ->label($valueLabel)
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
