<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipSlotResource\Pages;
use App\Models\EquipSlot;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipSlotResource extends Resource
{
    protected static ?string $model = EquipSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = '装备槽位';

    protected static ?string $modelLabel = '装备槽位';

    protected static ?string $pluralModelLabel = '装备槽位';

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('slot_id')->label('槽位ID')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('slot_name')->label('槽位名称')->required()->maxLength(64),
            Select::make('slot_type')->label('槽位类型')->options([
                'equipment' => '主装备',
                'accessory' => '饰品',
                'special' => '特殊',
            ])->default('equipment')->required(),
            TextInput::make('unlock_level')->label('解锁等级')->integer()->minValue(1)->required()->default(1),
            TextInput::make('equip_limit')->label('可装备数量')->integer()->minValue(1)->required()->default(1),
            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
            Toggle::make('is_set_slot')->label('套装位')->default(false),
            Toggle::make('can_drop_blue_gear')->label('可掉蓝装')->default(false),
            Toggle::make('can_craft')->label('可打造')->default(true),
            Toggle::make('can_exchange')->label('可兑换')->default(false),
            Toggle::make('can_star_up')->label('可升星')->default(true),
            Toggle::make('can_rank_up')->label('可升阶')->default(true),
            Toggle::make('is_enabled')->label('启用')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_id')->label('槽位ID')->searchable()->sortable(),
                TextColumn::make('slot_name')->label('名称')->searchable()->sortable(),
                TextColumn::make('equip_limit')->label('数量')->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                ToggleColumn::make('is_set_slot')->label('套装位'),
                ToggleColumn::make('can_drop_blue_gear')->label('蓝装'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipSlots::route('/'),
            'create' => Pages\CreateEquipSlot::route('/create'),
            'edit' => Pages\EditEquipSlot::route('/{record}/edit'),
        ];
    }
}
