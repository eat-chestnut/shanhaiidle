<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerEquipmentInstanceResource\Pages;
use App\Models\PlayerEquipmentInstance;
use App\Support\AdminOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PlayerEquipmentInstanceResource extends Resource
{
    protected static ?string $model = PlayerEquipmentInstance::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = '玩家装备实例';

    protected static ?string $modelLabel = '玩家装备实例';

    protected static ?string $pluralModelLabel = '玩家装备实例';

    protected static string | \UnitEnum | null $navigationGroup = '玩家实例';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('装备实例信息')
                ->description('双手镯 / 双戒指必须分位（bracelet_1、bracelet_2、ring_1、ring_2）。套装件数统计不包含 talisman，护符星级连锁统计不包含 talisman。套装实例进阶后只更新 item_id / set_level / max_star，并保留当前 star。')
                ->schema([
                    TextInput::make('player_id')
                        ->label('玩家 ID')
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('instance_id')
                        ->label('实例 ID')
                        ->maxLength(64)
                        ->required()
                        ->unique(ignoreRecord: true, column: 'instance_id')
                        ->helperText('instance_id 为业务唯一 ID。'),
                    Select::make('item_id')
                        ->label('物品 ID')
                        ->options(AdminOptions::itemOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('必须关联 items.item_id，quality / rarity 必须继承该 item。'),
                    Select::make('equipment_source_type')
                        ->label('来源类型')
                        ->options(PlayerEquipmentInstance::SOURCE_TYPE_OPTIONS)
                        ->required()
                        ->helperText('首版仅支持 set_equipment / blue_equipment / common_equipment。'),
                    Select::make('slot_type')
                        ->label('穿戴位')
                        ->options(PlayerEquipmentInstance::SLOT_TYPE_OPTIONS)
                        ->required()
                        ->helperText('只允许固定穿戴位，不得合并双手镯 / 双戒指。'),
                    TextInput::make('set_id')
                        ->label('套装 ID')
                        ->maxLength(64)
                        ->helperText('套装装备必填；非套装装备必须为空。'),
                    Select::make('set_level')
                        ->label('套装档位')
                        ->options(PlayerEquipmentInstance::SET_LEVEL_OPTIONS)
                        ->helperText('套装装备仅允许 20 / 40 / 50 / 60；非套装必须为空。'),
                    TextInput::make('star')
                        ->label('当前星级')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->helperText('star 必须 >= 0 且不能超过 max_star。'),
                    TextInput::make('max_star')
                        ->label('星级上限')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->helperText('set_level=20/40/50/60 时 max_star 必须为 3/6/8/10。'),
                    Select::make('quality')
                        ->label('玩法品质')
                        ->options(AdminOptions::qualityOptions())
                        ->required(),
                    Select::make('rarity')
                        ->label('展示稀有度')
                        ->options(AdminOptions::rarityOptions())
                        ->required(),
                    Toggle::make('is_equipped')
                        ->label('已穿戴')
                        ->default(false),
                    Toggle::make('is_locked')
                        ->label('已锁定')
                        ->default(false),
                    DateTimePicker::make('obtained_at')
                        ->label('获得时间')
                        ->seconds(false)
                        ->native(false),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player_id')->label('玩家 ID')->sortable(),
                TextColumn::make('instance_id')->label('实例 ID')->searchable()->sortable(),
                TextColumn::make('item_id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('slot_type')->label('穿戴位')->badge()->sortable(),
                TextColumn::make('equipment_source_type')->label('来源类型')->badge()->sortable(),
                TextColumn::make('set_id')->label('套装 ID')->sortable(),
                TextColumn::make('set_level')->label('套装档位')->sortable(),
                TextColumn::make('star')->label('当前星级')->sortable(),
                TextColumn::make('max_star')->label('星级上限')->sortable(),
                ToggleColumn::make('is_equipped')->label('已穿戴')->sortable(),
                ToggleColumn::make('is_locked')->label('已锁定')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_source_type')
                    ->label('来源类型')
                    ->options(PlayerEquipmentInstance::SOURCE_TYPE_OPTIONS),
                Tables\Filters\SelectFilter::make('slot_type')
                    ->label('穿戴位')
                    ->options(PlayerEquipmentInstance::SLOT_TYPE_OPTIONS),
                Tables\Filters\SelectFilter::make('set_level')
                    ->label('套装档位')
                    ->options(PlayerEquipmentInstance::SET_LEVEL_OPTIONS),
                Tables\Filters\TernaryFilter::make('is_equipped')->label('已穿戴'),
                Tables\Filters\TernaryFilter::make('is_locked')->label('已锁定'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerEquipmentInstances::route('/'),
            'create' => Pages\CreatePlayerEquipmentInstance::route('/create'),
            'edit' => Pages\EditPlayerEquipmentInstance::route('/{record}/edit'),
        ];
    }
}
