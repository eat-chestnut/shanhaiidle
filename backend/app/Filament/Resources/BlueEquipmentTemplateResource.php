<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueEquipmentTemplateResource\Pages;
use App\Models\BlueEquipmentTemplate;
use App\Support\AdminOptions;
use App\Support\BlueEquipmentTemplateModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BlueEquipmentTemplateResource extends Resource
{
    protected static ?string $model = BlueEquipmentTemplate::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '蓝装模板';

    protected static ?string $modelLabel = '蓝装模板';

    protected static ?string $pluralModelLabel = '蓝装模板';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('蓝装模板不是 item，result_item_id 必须挂到 items.item_id。护符没有蓝色装备；手镯 35级开始；戒指 45级开始。')
                ->schema([
                    TextInput::make('template_id')
                        ->label('模板 ID')
                        ->helperText('业务唯一 ID，用于承接蓝装模板本体。')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true),
                    TextInput::make('template_name')
                        ->label('模板名称')
                        ->helperText('后台内部识别名称，建议带“模板”字样。')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('display_name')
                        ->label('显示名称')
                        ->helperText('对应模板的展示名称，不代表 items 表中的成品记录。')
                        ->required()
                        ->maxLength(255),
                    Select::make('slot_type')
                        ->label('部位')
                        ->helperText('只允许当前蓝装模板支持的 10 个部位；护符不纳入蓝装模板。')
                        ->required()
                        ->options(BlueEquipmentTemplateModuleSupport::SLOT_OPTIONS)
                        ->searchable()
                        ->preload(),
                    Select::make('level_band')
                        ->label('等级档')
                        ->helperText('仅允许 20 / 35 / 45 / 50 / 60。手镯从 35级开始，戒指从 45级开始。')
                        ->required()
                        ->options(BlueEquipmentTemplateModuleSupport::LEVEL_BAND_OPTIONS)
                        ->searchable()
                        ->preload(),
                    Select::make('result_item_id')
                        ->label('成品 item_id')
                        ->helperText('必须选择 items.item_id，对应蓝装成品；模板本身不是 item。')
                        ->required()
                        ->options(BlueEquipmentTemplateModuleSupport::resultItemOptions())
                        ->searchable()
                        ->preload(),
                    Select::make('quality')
                        ->label('品质')
                        ->helperText('本轮固定为 blue。')
                        ->options(['blue' => '蓝色（blue）'])
                        ->default('blue')
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    Select::make('rarity')
                        ->label('稀有度')
                        ->helperText('本轮固定为 blue。')
                        ->options(['blue' => '蓝色（blue）'])
                        ->default('blue')
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    TextInput::make('unlock_level')
                        ->label('解锁等级')
                        ->helperText('通常与 level_band 保持一致。')
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('icon')
                        ->label('图标')
                        ->helperText('直接使用配置中的 icon 字符串，不上传文件。')
                        ->maxLength(255),
                    Textarea::make('summary')
                        ->label('摘要')
                        ->helperText('用于说明该蓝装模板的用途或定位。')
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->helperText('关闭后不会出现在导出文件中。')
                        ->default(true),
                    Textarea::make('remark')
                        ->label('备注')
                        ->helperText('补充维护说明，不参与逻辑。')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('白字属性')
                ->description('蓝装模板只定义白字属性，允许同一模板维护多条白字。当前不处理蓝词条定义、提取逻辑、掉落逻辑、打造执行和穿戴逻辑。')
                ->schema([
                    Repeater::make('base_stats')
                        ->hiddenLabel()
                        ->default([])
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->table([
                            TableColumn::make('属性 Key'),
                            TableColumn::make('数值类型'),
                            TableColumn::make('数值'),
                            TableColumn::make('排序'),
                            TableColumn::make('启用'),
                        ])
                        ->schema([
                            Select::make('stat_key')
                                ->label('属性 Key')
                                ->options(BlueEquipmentTemplateModuleSupport::STAT_KEY_OPTIONS)
                                ->searchable()
                                ->required()
                                ->columnSpan(4),
                            Select::make('value_type')
                                ->label('数值类型')
                                ->options(BlueEquipmentTemplateModuleSupport::VALUE_TYPE_OPTIONS)
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('value')
                                ->label('数值')
                                ->numeric()
                                ->required()
                                ->step(0.0001)
                                ->columnSpan(2),
                            TextInput::make('sort_order')
                                ->label('排序')
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->columnSpan(2),
                            Toggle::make('is_enabled')
                                ->label('启用')
                                ->default(true)
                                ->columnSpan(1),
                            TextInput::make('remark')
                                ->label('备注')
                                ->maxLength(255)
                                ->columnSpan(12),
                        ])
                        ->addActionLabel('新增白字属性')
                        ->columnSpanFull(),
                ]),
            Section::make('蓝词条数量规则')
                ->description('这里只维护蓝词条数量范围，不维护 `blue_affixes` / `blue_affix_slot_rules` 定义、权重或抽取逻辑。')
                ->schema([
                    TextInput::make('blue_affix_count_min')
                        ->label('最少蓝词条数')
                        ->helperText('必须小于等于最多蓝词条数。')
                        ->integer()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('blue_affix_count_max')
                        ->label('最多蓝词条数')
                        ->helperText('必须大于等于最少蓝词条数。')
                        ->integer()
                        ->minValue(0)
                        ->required(),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template_id')->label('模板 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('显示名称')->searchable()->sortable(),
                TextColumn::make('slot_type')
                    ->label('部位')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(BlueEquipmentTemplateModuleSupport::SLOT_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('level_band')->label('等级档')->sortable(),
                TextColumn::make('result_item_id')
                    ->label('成品 item_id')
                    ->searchable()
                    ->description(fn (BlueEquipmentTemplate $record): string => $record->resultItem?->display_name ?? ''),
                TextColumn::make('blue_affix_count_min')->label('最少蓝词条')->sortable(),
                TextColumn::make('blue_affix_count_max')->label('最多蓝词条')->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot_type')
                    ->label('部位')
                    ->options(BlueEquipmentTemplateModuleSupport::SLOT_OPTIONS),
                Tables\Filters\SelectFilter::make('level_band')
                    ->label('等级档')
                    ->options(BlueEquipmentTemplateModuleSupport::LEVEL_BAND_OPTIONS),
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

    /**
     * @return array<string, mixed>
     */
    public static function formDataForEdit(BlueEquipmentTemplate $record): array
    {
        $data = $record->attributesToArray();
        $data['base_stats'] = $record->baseStats
            ->map(fn ($row): array => [
                'stat_key' => (string) $row->stat_key,
                'value_type' => (string) $row->value_type,
                'value' => BlueEquipmentTemplateModuleSupport::normalizeNumericValue((float) $row->value),
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : '',
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @return array{template: array<string, mixed>, base_stats: array<int, array<string, mixed>>}
     */
    public static function normalizeFormDataOrFail(array $data, ?BlueEquipmentTemplate $record = null): array
    {
        return BlueEquipmentTemplateModuleSupport::normalizeSingleTemplateFormOrFail($data, $record);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncBaseStats(BlueEquipmentTemplate $record, array $rows): void
    {
        $record->baseStats()->delete();

        $record->baseStats()->createMany(
            collect($rows)
                ->map(fn (array $row): array => collect($row)->except('template_id')->all())
                ->all(),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlueEquipmentTemplates::route('/'),
            'create' => Pages\CreateBlueEquipmentTemplate::route('/create'),
            'edit' => Pages\EditBlueEquipmentTemplate::route('/{record}/edit'),
        ];
    }
}
