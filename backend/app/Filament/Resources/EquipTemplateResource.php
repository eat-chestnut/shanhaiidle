<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipTemplateResource\Pages;
use App\Models\EquipmentSet;
use App\Models\EquipTemplate;
use App\Models\SkillCatalog;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('EquipTemplateTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('模板ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?EquipTemplate $record): bool => $record !== null),
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('slot')
                                    ->label('装备类型')
                                    ->required()
                                    ->options(static::slotOptions()),
                                Select::make('equip_type')
                                    ->label('装备归类')
                                    ->required()
                                    ->default('set')
                                    ->options(static::equipTypeOptions()),
                                Select::make('rarity')
                                    ->label('稀有度')
                                    ->required()
                                    ->options(static::rarityOptions()),
                                TextInput::make('required_level')
                                    ->label('需求等级')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('flow_tag')
                                    ->label('流派标签')
                                    ->maxLength(64),
                                Select::make('set_id')
                                    ->label('所属套装配置')
                                    ->options(fn (): array => static::equipmentSetOptions())
                                    ->searchable()
                                    ->nullable(),
                                TextInput::make('set_line_id')
                                    ->label('套装线ID')
                                    ->maxLength(64),
                                Select::make('set_stage')
                                    ->label('套装阶段')
                                    ->options([
                                        20 => '20级',
                                        40 => '40级',
                                        60 => '60级',
                                    ])
                                    ->nullable(),
                                TextInput::make('icon')
                                    ->label('图标路径')
                                    ->maxLength(255)
                                    ->default(''),
                                Toggle::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                                TextInput::make('sort_order')
                                    ->label('排序')
                                    ->integer()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),
                            ])->columns(2),
                        Tab::make('白色属性与成长')
                            ->schema([
                                KeyValue::make('white_stats')
                                    ->label('白色基础属性')
                                    ->keyLabel('属性Key')
                                    ->valueLabel('数值')
                                    ->helperText('示例：ATK=20, DEF=5, HP=60'),
                                KeyValue::make('star_growth')
                                    ->label('每星成长')
                                    ->keyLabel('属性Key')
                                    ->valueLabel('每星增量')
                                    ->helperText('示例：ATK=4, DEF=1, HP=10'),
                                Toggle::make('star_enabled')
                                    ->label('可升星')
                                    ->default(true),
                                TextInput::make('star_cap')
                                    ->label('升星上限')
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(10)
                                    ->default(10)
                                    ->required(),
                                TextInput::make('socket_rule_ref')
                                    ->label('孔位规则引用')
                                    ->maxLength(64)
                                    ->helperText('例如 auto_star_3_6_8_10'),
                                Toggle::make('can_attach_blue_affix')
                                    ->label('可附加蓝词条')
                                    ->default(false),
                                Toggle::make('can_roll_purple_affix')
                                    ->label('可洗炼紫词条')
                                    ->default(false),
                            ])->columns(2),
                        Tab::make('打造与升品')
                            ->schema([
                                Toggle::make('forge_enabled')
                                    ->label('可打造')
                                    ->default(false),
                                Select::make('quality_tier')
                                    ->label('品质层级')
                                    ->options([
                                        'normal' => '普通',
                                        'high' => '高品质',
                                        'blue_drop' => '蓝装掉落',
                                    ])
                                    ->default('normal')
                                    ->required(),
                                Select::make('forge_tier')
                                    ->label('打造段位')
                                    ->options([
                                        'T1' => 'T1(1-19)',
                                        'T2' => 'T2(20-39)',
                                        'T3' => 'T3(40-59)',
                                        'T4' => 'T4(60-79)',
                                    ])
                                    ->nullable(),
                                Select::make('slot_group')
                                    ->label('部位组')
                                    ->options([
                                        'weapon' => '武器组',
                                        'armor' => '防具组',
                                        'cloak' => '披风组',
                                        'accessory' => '饰品组',
                                    ])
                                    ->nullable(),
                                Select::make('theme_key')
                                    ->label('主题')
                                    ->options([
                                        'nanshan' => '南山',
                                        'qingqiu' => '青丘',
                                        'kunlun' => '昆仑',
                                    ])
                                    ->nullable(),
                                TextInput::make('forge_family_id')
                                    ->label('打造家族ID')
                                    ->maxLength(64),
                                TextInput::make('upgrade_from_template_id')
                                    ->label('升品来源模板ID')
                                    ->maxLength(64),
                                TextInput::make('upgrade_to_template_id')
                                    ->label('可升到模板ID')
                                    ->maxLength(64),
                                TextInput::make('blueprint_item_id')
                                    ->label('图纸物品ID')
                                    ->maxLength(64),
                            ])->columns(2),
                        Tab::make('兼容字段')
                            ->schema([
                                Select::make('main_stat')
                                    ->label('旧主属性')
                                    ->options(static::statOptions())
                                    ->nullable(),
                                TextInput::make('main_min')
                                    ->label('旧最小值')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('main_max')
                                    ->label('旧最大值')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('unidentified_chance')
                                    ->label('未鉴定概率')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(1),
                                Repeater::make('effects')
                                    ->label('旧特效 effects')
                                    ->default([])
                                    ->schema([
                                        Select::make('type')
                                            ->label('特效类型')
                                            ->required()
                                            ->options(static::effectTypeOptions()),
                                        TextInput::make('val')
                                            ->label('数值')
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->default(0),
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
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('模板ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slot')
                    ->label('装备类型')
                    ->formatStateUsing(fn (string $state): string => static::slotOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('quality_tier')
                    ->label('品质层级')
                    ->formatStateUsing(fn (string $state): string => static::qualityTierOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('稀有度')
                    ->formatStateUsing(fn (string $state): string => static::rarityOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('required_level')->label('等级')->numeric()->sortable(),
                TextColumn::make('forge_tier')->label('打造段位')->toggleable(),
                TextColumn::make('forge_family_id')->label('家族ID')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('forge_enabled')->label('可打造')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot')->label('装备类型')->options(static::slotOptions()),
                Tables\Filters\SelectFilter::make('quality_tier')->label('品质层级')->options(static::qualityTierOptions()),
                Tables\Filters\SelectFilter::make('forge_tier')->label('打造段位')->options([
                    'T1' => 'T1',
                    'T2' => 'T2',
                    'T3' => 'T3',
                    'T4' => 'T4',
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
            'index' => Pages\ListEquipTemplates::route('/'),
            'create' => Pages\CreateEquipTemplate::route('/create'),
            'edit' => Pages\EditEquipTemplate::route('/{record}/edit'),
        ];
    }

    protected static function qualityTierOptions(): array
    {
        return [
            'normal' => '普通',
            'high' => '高品质',
            'blue_drop' => '蓝装掉落',
        ];
    }

    protected static function equipTypeOptions(): array
    {
        return [
            'set' => '套装位',
            'ring' => '戒指',
            'bracelet' => '手镯',
            'talisman' => '护身符',
        ];
    }

    protected static function rarityOptions(): array
    {
        return [
            'white' => '白',
            'blue' => '蓝',
            'purple' => '紫',
            'gold' => '金',
            'orange' => '橙',
        ];
    }

    protected static function slotOptions(): array
    {
        return [
            'main_weapon' => '主武器',
            'off_weapon' => '副武器',
            'armor' => '盔甲',
            'belt' => '腰带',
            'shoes' => '鞋子',
            'gloves' => '护手',
            'helm' => '头盔',
            'necklace' => '项链',
            'talisman' => '护身符',
            'ring' => '戒指',
            'bracelet' => '手镯',
        ];
    }

    protected static function statOptions(): array
    {
        return [
            'HP' => '生命',
            'ATK' => '攻击',
            'DEF' => '防御',
            'QI' => '气',
            'CRIT_PERCENT' => '暴击',
            'LOOT_BONUS_PERCENT' => '掉落',
            'WD' => '物伤',
            'SP' => '术伤',
        ];
    }

    protected static function effectTypeOptions(): array
    {
        return [
            'stat' => '属性加成',
            'skill_level' => '技能等级',
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

    protected static function equipmentSetOptions(): array
    {
        return EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
