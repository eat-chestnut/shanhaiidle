<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopGoodsResource\Pages;
use App\Models\ShopGood;
use App\Support\AdminOptions;
use App\Support\ShopGoodsSupport;
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

class ShopGoodsResource extends Resource
{
    protected static ?string $model = ShopGood::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '商城商品';

    protected static ?string $modelLabel = '商城商品';

    protected static ?string $pluralModelLabel = '商城商品';

    protected static string | \UnitEnum | null $navigationGroup = '商城与运营';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('商城模块只维护商品定义、展示分类、价格、解锁等级、限购与上下架，不承接支付、活动时段和礼包内容定义。')
                ->schema([
                    TextInput::make('goods_id')
                        ->label('商品 ID')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?ShopGood $record): bool => $record !== null)
                        ->helperText('业务唯一 ID，建议一经创建后保持稳定。'),
                    TextInput::make('title')
                        ->label('内部名称')
                        ->required()
                        ->maxLength(255)
                        ->helperText('运营和后台使用的内部名称。'),
                    TextInput::make('display_name')
                        ->label('展示名称')
                        ->required()
                        ->maxLength(255)
                        ->helperText('前端商城卡片和详情页展示名称。'),
                    Select::make('shop_tab')
                        ->label('商城页签')
                        ->options(AdminOptions::shopTabOptions())
                        ->default('daily')
                        ->required()
                        ->helperText('固定为 daily / growth / sect / special。'),
                    Select::make('goods_type')
                        ->label('商品类型')
                        ->options(AdminOptions::shopGoodsTypeOptions())
                        ->default('direct_item')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('reward_item_id', null);
                            if ($state === 'gift_pack') {
                                $set('reward_count', 1);
                            }
                        })
                        ->helperText('商城商品只能配置一个 reward_item_id；多奖励请改卖礼包 item。'),
                    Textarea::make('desc')
                        ->label('商品描述')
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('icon')
                        ->label('图标')
                        ->maxLength(255)
                        ->helperText('为空时，前端可回退展示 reward_item_id 对应 item 图标。'),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_enabled')
                        ->label('上架启用')
                        ->default(true),
                    Textarea::make('remark')
                        ->label('备注')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('发放内容')
                ->description('一个商品只卖一个 reward_item_id。若需要多奖励，请先在礼包模块定义礼包 item，再将 goods_type 设为 gift_pack。')
                ->schema([
                    Select::make('reward_item_id')
                        ->label('发放物品')
                        ->options(fn (Get $get): array => AdminOptions::shopRewardItemOptions((string) $get('goods_type')))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('direct_item 仅允许普通 item；gift_pack 仅允许礼包 item。'),
                    TextInput::make('reward_count')
                        ->label('发放数量')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1)
                        ->helperText('礼包商品通常为 1。'),
                ])
                ->columns(2),
            Section::make('价格与限制')
                ->description('价格统一通过 price_item_id + price_amount 表达；商城首版不做限时活动商品逻辑。')
                ->schema([
                    Select::make('price_item_id')
                        ->label('价格货币')
                        ->options(AdminOptions::shopPriceItemOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('当前购买链路接通 cur_gold / cur_premium_jade / cur_contribution。'),
                    TextInput::make('price_amount')
                        ->label('现价')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    TextInput::make('original_price_amount')
                        ->label('原价')
                        ->integer()
                        ->minValue(0)
                        ->nullable()
                        ->helperText('可为空；若填写，不能小于现价。'),
                    TextInput::make('unlock_level')
                        ->label('解锁等级')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    Select::make('buy_limit_type')
                        ->label('限购类型')
                        ->options(AdminOptions::shopBuyLimitTypeOptions())
                        ->required()
                        ->default('none')
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('buy_limit_value', $state === 'none' ? 0 : 1);
                        })
                        ->helperText('固定为 none / daily / weekly / lifetime。'),
                    TextInput::make('buy_limit_value')
                        ->label('限购次数')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0)
                        ->helperText(fn (Get $get): string => (string) $get('buy_limit_type') === 'none'
                            ? '不限购时保持 0。'
                            : '填写该限购类型下的可购买次数。'),
                    Toggle::make('is_recommended')
                        ->label('推荐商品')
                        ->default(false),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('goods_id')->label('商品 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('shop_tab')
                    ->label('商城页签')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::shopTabOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('goods_type')
                    ->label('商品类型')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::shopGoodsTypeOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('reward_item_id')->label('发放 item_id')->searchable(),
                TextColumn::make('price_item_id')->label('价格 item_id')->searchable(),
                TextColumn::make('price_amount')->label('现价')->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                TextColumn::make('buy_limit_type')
                    ->label('限购类型')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::shopBuyLimitTypeOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('buy_limit_value')->label('限购次数')->sortable(),
                IconColumn::make('is_recommended')->label('推荐')->boolean()->sortable(),
                IconColumn::make('is_enabled')->label('上架')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_tab')->label('商城页签')->options(AdminOptions::shopTabOptions()),
                Tables\Filters\SelectFilter::make('goods_type')->label('商品类型')->options(AdminOptions::shopGoodsTypeOptions()),
                Tables\Filters\SelectFilter::make('price_item_id')->label('价格货币')->options(AdminOptions::shopPriceItemOptions()),
                Tables\Filters\SelectFilter::make('buy_limit_type')->label('限购类型')->options(AdminOptions::shopBuyLimitTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_recommended')->label('推荐商品'),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('上架状态'),
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
            'index' => Pages\ListShopGoods::route('/'),
            'create' => Pages\CreateShopGood::route('/create'),
            'edit' => Pages\EditShopGood::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeFormDataOrFail(array $data): array
    {
        $goods = ShopGoodsSupport::normalizeRow($data);
        ShopGoodsSupport::validateRowOrFail($goods);

        return $goods;
    }
}
