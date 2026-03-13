<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueAffixResource\Pages;
use App\Models\BlueAffix;
use App\Support\BlueAffixModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BlueAffixResource extends Resource
{
    protected static ?string $model = BlueAffix::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = '蓝词条';

    protected static ?string $modelLabel = '蓝色词条';

    protected static ?string $pluralModelLabel = '蓝色词条';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('蓝词条本体不是 item。本模块只做定义层，不处理提取、生成、洗练、重铸和掉落逻辑；护符不参与蓝词条体系。')
                ->schema([
                    TextInput::make('affix_id')
                        ->label('词条 ID')
                        ->helperText('业务唯一 ID。蓝词条按“单条记录 + level_band”拆分。')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true),
                    TextInput::make('affix_name')
                        ->label('内部名称')
                        ->helperText('用于后台维护的内部名称。')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('display_name')
                        ->label('显示名称')
                        ->helperText('前端展示名称。')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('summary')
                        ->label('摘要')
                        ->helperText('描述该词条效果，不参与数值逻辑。')
                        ->rows(2)
                        ->columnSpanFull(),
                    Select::make('level_band')
                        ->label('等级档')
                        ->helperText('仅允许 20 / 35 / 45。')
                        ->required()
                        ->options(BlueAffixModuleSupport::LEVEL_BAND_OPTIONS)
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
                        ->helperText('补充维护说明。')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('数值配置')
                ->description('蓝词条只维护 effect_key、数值范围和权重，不做流派限制、职业硬限制和词条组合嵌套。')
                ->schema([
                    Select::make('effect_key')
                        ->label('效果 Key')
                        ->helperText('只允许使用 blue_affixes_v1.json 中定义的 effect_key。')
                        ->required()
                        ->options(BlueAffixModuleSupport::EFFECT_KEY_OPTIONS)
                        ->searchable()
                        ->preload(),
                    Select::make('value_type')
                        ->label('数值类型')
                        ->helperText('固定值或百分比。')
                        ->required()
                        ->options(BlueAffixModuleSupport::VALUE_TYPE_OPTIONS),
                    TextInput::make('value_min')
                        ->label('最小值')
                        ->numeric()
                        ->required()
                        ->step(0.0001),
                    TextInput::make('value_max')
                        ->label('最大值')
                        ->numeric()
                        ->required()
                        ->step(0.0001),
                    TextInput::make('weight')
                        ->label('权重')
                        ->helperText('必须大于 0。')
                        ->integer()
                        ->required()
                        ->minValue(1),
                ])
                ->columns(5),
            Section::make('适用部位')
                ->description('部位规则通过独立表维护，不使用 JSON。护符明确排除，不允许配置 talisman。')
                ->schema([
                    Repeater::make('slot_rules')
                        ->hiddenLabel()
                        ->default([])
                        ->reorderable(false)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(false)
                        ->table([
                            TableColumn::make('部位'),
                            TableColumn::make('排序'),
                            TableColumn::make('启用'),
                        ])
                        ->schema([
                            Select::make('slot_type')
                                ->label('部位')
                                ->options(BlueAffixModuleSupport::SLOT_OPTIONS)
                                ->searchable()
                                ->required()
                                ->columnSpan(6),
                            TextInput::make('sort_order')
                                ->label('排序')
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->columnSpan(3),
                            Toggle::make('is_enabled')
                                ->label('启用')
                                ->default(true)
                                ->columnSpan(2),
                            TextInput::make('remark')
                                ->label('备注')
                                ->maxLength(255)
                                ->columnSpan(12),
                        ])
                        ->addActionLabel('新增适用部位')
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affix_id')->label('词条 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('显示名称')->searchable()->sortable(),
                TextColumn::make('effect_key')
                    ->label('效果 Key')
                    ->formatStateUsing(fn (?string $state): string => BlueAffixModuleSupport::EFFECT_KEY_OPTIONS[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('value_type')
                    ->label('数值类型')
                    ->formatStateUsing(fn (?string $state): string => BlueAffixModuleSupport::VALUE_TYPE_OPTIONS[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('value_min')
                    ->label('最小值')
                    ->formatStateUsing(fn (int|float|string|null $state): int|float => BlueAffixModuleSupport::normalizeNumericValue((float) $state))
                    ->sortable(),
                TextColumn::make('value_max')
                    ->label('最大值')
                    ->formatStateUsing(fn (int|float|string|null $state): int|float => BlueAffixModuleSupport::normalizeNumericValue((float) $state))
                    ->sortable(),
                TextColumn::make('weight')->label('权重')->sortable(),
                TextColumn::make('level_band')->label('等级档')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('effect_key')->label('效果 Key')->options(BlueAffixModuleSupport::EFFECT_KEY_OPTIONS),
                Tables\Filters\SelectFilter::make('level_band')->label('等级档')->options(BlueAffixModuleSupport::LEVEL_BAND_OPTIONS),
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
    public static function formDataForEdit(BlueAffix $record): array
    {
        $data = $record->attributesToArray();
        $data['value_min'] = BlueAffixModuleSupport::normalizeNumericValue((float) $record->value_min);
        $data['value_max'] = BlueAffixModuleSupport::normalizeNumericValue((float) $record->value_max);
        $data['slot_rules'] = $record->slotRules
            ->map(fn ($row): array => [
                'slot_type' => (string) $row->slot_type,
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : '',
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @return array{affix: array<string, mixed>, slot_rules: array<int, array<string, mixed>>}
     */
    public static function normalizeFormDataOrFail(array $data, ?BlueAffix $record = null): array
    {
        return BlueAffixModuleSupport::normalizeSingleAffixFormOrFail($data, $record);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncSlotRules(BlueAffix $record, array $rows): void
    {
        $record->slotRules()->delete();

        $record->slotRules()->createMany(
            collect($rows)
                ->map(fn (array $row): array => collect($row)->except('affix_id')->all())
                ->all(),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlueAffixes::route('/'),
            'create' => Pages\CreateBlueAffix::route('/create'),
            'edit' => Pages\EditBlueAffix::route('/{record}/edit'),
        ];
    }
}
