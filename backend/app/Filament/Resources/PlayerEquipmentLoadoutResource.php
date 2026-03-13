<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerEquipmentLoadoutResource\Pages;
use App\Models\PlayerEquipmentLoadout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlayerEquipmentLoadoutResource extends Resource
{
    protected static ?string $model = PlayerEquipmentLoadout::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = '玩家穿戴映射';

    protected static ?string $modelLabel = '玩家穿戴映射';

    protected static ?string $pluralModelLabel = '玩家穿戴映射';

    protected static string | \UnitEnum | null $navigationGroup = '玩家实例';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('穿戴映射')
                ->description('每个玩家每个 slot_type 只能有 1 条当前映射，同一 instance_id 不能同时占多个穿戴位。双手镯 / 双戒指必须分位；套装件数统计与护符星级连锁统计都排除 talisman。')
                ->schema([
                    TextInput::make('player_id')
                        ->label('玩家 ID')
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    Select::make('slot_type')
                        ->label('穿戴位')
                        ->options(PlayerEquipmentLoadout::SLOT_TYPE_OPTIONS)
                        ->required()
                        ->helperText('固定穿戴位，必须明确区分 bracelet_1 / bracelet_2 / ring_1 / ring_2。'),
                    TextInput::make('instance_id')
                        ->label('实例 ID')
                        ->maxLength(64)
                        ->required()
                        ->helperText('必须关联 player_equipment_instances.instance_id 且属于同一个 player_id。'),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player_id')->label('玩家 ID')->sortable(),
                TextColumn::make('slot_type')->label('穿戴位')->badge()->sortable(),
                TextColumn::make('instance_id')->label('实例 ID')->searchable()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot_type')->label('穿戴位')->options(PlayerEquipmentLoadout::SLOT_TYPE_OPTIONS),
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
            'index' => Pages\ListPlayerEquipmentLoadouts::route('/'),
            'create' => Pages\CreatePlayerEquipmentLoadout::route('/create'),
            'edit' => Pages\EditPlayerEquipmentLoadout::route('/{record}/edit'),
        ];
    }
}
