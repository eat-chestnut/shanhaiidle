<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopGoodsResource\Pages;
use App\Models\ShopGood;
use App\Support\AdminOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                ->schema([
                    TextInput::make('goods_id')
                        ->label('商品 ID')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true),
                    Select::make('shop_type')
                        ->label('商城类型')
                        ->required()
                        ->options(AdminOptions::shopTypeOptions()),
                    TextInput::make('title')
                        ->label('标题')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subtitle')
                        ->label('副标题')
                        ->maxLength(255),
                    Textarea::make('desc')
                        ->label('说明')
                        ->rows(3)
                        ->columnSpanFull(),
                    FileUpload::make('icon')
                        ->label('图标')
                        ->disk('public')
                        ->directory('config/shop-icons')
                        ->image()
                        ->imagePreviewHeight('120'),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                ])
                ->columns(3),
            Section::make('奖励与价格')
                ->schema([
                    Select::make('reward_item_id')
                        ->label('奖励物品')
                        ->options(fn (): array => AdminOptions::itemOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('reward_count')
                        ->label('奖励数量')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    Select::make('cost_currency_type')
                        ->label('价格货币')
                        ->options(AdminOptions::shopCurrencyOptions())
                        ->required(),
                    TextInput::make('cost_amount')
                        ->label('价格')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                ])
                ->columns(4),
            Section::make('开放与限购')
                ->schema([
                    TextInput::make('unlock_level')
                        ->label('开放等级')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    TextInput::make('daily_limit')
                        ->label('每日限购')
                        ->integer()
                        ->minValue(1)
                        ->nullable(),
                    TextInput::make('weekly_limit')
                        ->label('每周限购')
                        ->integer()
                        ->minValue(1)
                        ->nullable(),
                    TextInput::make('lifetime_limit')
                        ->label('终身限购')
                        ->integer()
                        ->minValue(1)
                        ->nullable(),
                    DateTimePicker::make('starts_at')
                        ->label('开始时间')
                        ->seconds(false),
                    DateTimePicker::make('ends_at')
                        ->label('结束时间')
                        ->seconds(false)
                        ->afterOrEqual('starts_at'),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('goods_id')->label('商品 ID')->searchable()->sortable(),
                TextColumn::make('shop_type')->label('商城类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::shopTypeOptions(), $state))->sortable(),
                TextColumn::make('title')->label('标题')->searchable()->sortable(),
                TextColumn::make('reward_item_id')->label('奖励物品')->formatStateUsing(fn (?string $state): string => AdminOptions::itemName($state))->searchable(),
                TextColumn::make('reward_count')->label('奖励数量')->sortable(),
                TextColumn::make('cost_currency_type')->label('价格货币')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::shopCurrencyOptions(), $state))->sortable(),
                TextColumn::make('cost_amount')->label('价格')->sortable(),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('daily_limit')->label('每日限购')->placeholder('—'),
                TextColumn::make('weekly_limit')->label('每周限购')->placeholder('—'),
                TextColumn::make('lifetime_limit')->label('终身限购')->placeholder('—'),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_type')->label('商城类型')->options(AdminOptions::shopTypeOptions()),
                Tables\Filters\SelectFilter::make('cost_currency_type')->label('价格货币')->options(AdminOptions::shopCurrencyOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
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
}
