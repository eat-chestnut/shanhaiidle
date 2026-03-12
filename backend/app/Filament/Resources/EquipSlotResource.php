<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipSlotResource\Pages;
use App\Models\EquipSlot;
use App\Support\AdminOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipSlotResource extends Resource
{
    protected static ?string $model = EquipSlot::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = '装备槽位';

    protected static ?string $modelLabel = '装备槽位';

    protected static ?string $pluralModelLabel = '装备槽位';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('槽位基础信息')
                ->description('部位是固定枚举，选择后会自动带出中文名称。')
                ->schema([
                    Select::make('slot_id')
                        ->label('部位')
                        ->required()
                        ->options(AdminOptions::slotOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('slot_name', AdminOptions::slotOptions()[$state] ?? '');
                        }),
                    TextInput::make('slot_name')->label('显示名称')->required()->maxLength(64),
                    Select::make('slot_type')->label('槽位类型')->options([
                        'equipment' => '主装备',
                        'accessory' => '饰品',
                        'special' => '特殊位',
                    ])->default('equipment')->required(),
                    TextInput::make('unlock_level')->label('解锁等级')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('equip_limit')->label('可装备数量')->integer()->minValue(1)->required()->default(1)->helperText('戒指 / 手镯可填 2。'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(3),
            Section::make('槽位行为')
                ->schema([
                    Toggle::make('is_set_slot')->label('属于套装位')->default(false),
                    Toggle::make('can_drop_blue_gear')->label('允许掉落蓝装')->default(false),
                    Toggle::make('can_craft')->label('允许打造')->default(true),
                    Toggle::make('can_exchange')->label('允许兑换')->default(false),
                    Toggle::make('can_star_up')->label('允许升星')->default(true),
                    Toggle::make('can_rank_up')->label('允许升阶')->default(true),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_id')->label('部位')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::slotOptions(), $state))->searchable()->sortable(),
                TextColumn::make('slot_name')->label('显示名称')->searchable(),
                TextColumn::make('slot_type')->label('槽位类型')->sortable(),
                TextColumn::make('equip_limit')->label('数量')->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                ToggleColumn::make('is_set_slot')->label('套装位'),
                ToggleColumn::make('can_drop_blue_gear')->label('蓝装掉落'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot_id')->label('部位')->options(AdminOptions::slotOptions()),
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
            'index' => Pages\ListEquipSlots::route('/'),
            'create' => Pages\CreateEquipSlot::route('/create'),
            'edit' => Pages\EditEquipSlot::route('/{record}/edit'),
        ];
    }
}
