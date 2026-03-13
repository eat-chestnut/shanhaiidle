<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GemCatalogResource\Pages;
use App\Models\Gem;
use App\Support\AdminOptions;
use App\Support\GemModuleSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class GemCatalogResource extends Resource
{
    protected static ?string $model = Gem::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '宝石配置';

    protected static ?string $modelLabel = '宝石';

    protected static ?string $pluralModelLabel = '宝石配置';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('宝石成品统一由 items.item_id 承接；gems 只维护宝石配置，不处理掉落、镶嵌、合成与开孔逻辑。')
                ->schema([
                    TextInput::make('gem_id')
                        ->label('宝石 ID')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true, column: 'gem_id')
                        ->disabled(fn (?Gem $record): bool => $record !== null)
                        ->helperText('宝石业务唯一 ID，建议创建后保持稳定。'),
                    Select::make('item_id')
                        ->label('成品物品')
                        ->options(fn (Get $get): array => GemModuleSupport::itemOptions((string) $get('gem_type')))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->unique(ignoreRecord: true, column: 'item_id')
                        ->helperText('只允许选择 `main_type = gem` 且 `sub_type` 与当前 gem_type 一致的 item；后续掉落、礼包、商城都应通过该 item_id 引用宝石。'),
                    TextInput::make('gem_name')
                        ->label('内部名称')
                        ->required()
                        ->maxLength(255)
                        ->helperText('配置内部使用的名称。'),
                    TextInput::make('display_name')
                        ->label('展示名称')
                        ->required()
                        ->maxLength(255)
                        ->helperText('前端展示名称。'),
                    Select::make('gem_type')
                        ->label('宝石类型')
                        ->options(Gem::GEM_TYPE_OPTIONS)
                        ->required()
                        ->default('attr_gem')
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('slot_group', GemModuleSupport::defaultSlotGroupForGemType($state));
                            $set('stat_key', null);
                            $set('item_id', null);
                        })
                        ->helperText('首版仅支持 attr_gem / skill_gem 两类。'),
                    TextInput::make('icon')
                        ->label('图标')
                        ->maxLength(255)
                        ->helperText('可填写资源路径；为空时由前端自行兜底。'),
                    Textarea::make('summary')
                        ->label('效果简述')
                        ->rows(3)
                        ->helperText('用于前端展示的简短效果说明。')
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
                ->columns(3),
            Section::make('效果配置')
                ->description('直接配置 stat_key + value_type + value，不依赖公式自动生成。')
                ->schema([
                    Select::make('stat_key')
                        ->label('作用 Key')
                        ->options(fn (Get $get): array => GemModuleSupport::statKeyOptions((string) $get('gem_type')))
                        ->searchable()
                        ->required()
                        ->helperText('属性宝石与技能宝石使用不同的正式 key，禁止填写随意中文文本。'),
                    Select::make('value_type')
                        ->label('数值类型')
                        ->options(Gem::VALUE_TYPE_OPTIONS)
                        ->required()
                        ->default('flat')
                        ->helperText('flat = 固定值；percent = 百分比。'),
                    TextInput::make('value')
                        ->label('数值')
                        ->numeric()
                        ->step(0.0001)
                        ->required()
                        ->helperText('直接填写宝石实际数值，不从公式推导。'),
                ])
                ->columns(3),
            Section::make('展示与使用')
                ->description('slot_group 用于表达属性孔 / 技能孔的使用边界，本模块不处理开孔或镶嵌逻辑。')
                ->schema([
                    Select::make('quality')
                        ->label('玩法品质')
                        ->options(AdminOptions::qualityOptions())
                        ->required()
                        ->default('white')
                        ->helperText('首版保留 white / blue / purple / gold / red 五档。'),
                    Select::make('rarity')
                        ->label('展示稀有度')
                        ->options(AdminOptions::rarityOptions())
                        ->required()
                        ->default('white')
                        ->helperText('用于 UI 展示层级，可与 quality 同值。'),
                    Select::make('slot_group')
                        ->label('孔位分组')
                        ->options(Gem::SLOT_GROUP_OPTIONS)
                        ->required()
                        ->default('attr_only')
                        ->helperText('attr_gem 只能用 attr_only；skill_gem 只能用 skill_only。'),
                    TextInput::make('unlock_level')
                        ->label('解锁等级')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1)
                        ->helperText('用于表示可获得或可使用的最低/推荐等级。'),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gem_id')->label('宝石 ID')->searchable()->sortable(),
                TextColumn::make('item_id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('gem_type')->label('宝石类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(Gem::GEM_TYPE_OPTIONS, $state))->badge()->sortable(),
                TextColumn::make('stat_key')->label('作用 Key')->searchable()->sortable(),
                TextColumn::make('value_type')->label('数值类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(Gem::VALUE_TYPE_OPTIONS, $state))->badge(),
                TextColumn::make('value')
                    ->label('数值')
                    ->formatStateUsing(fn (int|float|string|null $state): int|float => GemModuleSupport::normalizeNumericValue((float) $state)),
                TextColumn::make('quality')->label('玩法品质')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::qualityOptions(), $state))->badge()->sortable(),
                TextColumn::make('slot_group')->label('孔位分组')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(Gem::SLOT_GROUP_OPTIONS, $state))->badge()->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gem_type')->label('宝石类型')->options(Gem::GEM_TYPE_OPTIONS),
                Tables\Filters\SelectFilter::make('quality')->label('玩法品质')->options(AdminOptions::qualityOptions()),
                Tables\Filters\SelectFilter::make('slot_group')->label('孔位分组')->options(Gem::SLOT_GROUP_OPTIONS),
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
            'index' => Pages\ListGemCatalogs::route('/'),
            'create' => Pages\CreateGemCatalog::route('/create'),
            'edit' => Pages\EditGemCatalog::route('/{record}/edit'),
        ];
    }
}
