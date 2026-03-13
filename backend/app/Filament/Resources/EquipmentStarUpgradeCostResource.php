<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentStarUpgradeCostResource\Pages;
use App\Models\EquipmentStarRule;
use App\Models\EquipmentStarUpgradeCost;
use App\Support\AdminOptions;
use App\Support\EquipmentStarModuleSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipmentStarUpgradeCostResource extends Resource
{
    protected static ?string $model = EquipmentStarUpgradeCost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = '套装升星消耗';

    protected static ?string $modelLabel = '套装升星消耗';

    protected static ?string $pluralModelLabel = '套装升星消耗';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('升星消耗')
                ->description('同一档位、同一升星动作允许配置多条材料。升星核心材料优先使用星砂副本产物；本轮不做概率、失败、掉星或保底。')
                ->schema([
                    Select::make('set_level')
                        ->label('套装档位')
                        ->options(EquipmentStarRule::SET_LEVEL_OPTIONS)
                        ->required()
                        ->helperText('固定只支持 20 / 40 / 50 / 60 四档。'),
                    TextInput::make('from_star')
                        ->label('当前星级')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0)
                        ->live()
                        ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                            $fromStar = (int) ($state ?? 0);
                            $set('to_star', $fromStar + 1);
                        })
                        ->helperText('最小为 0。'),
                    TextInput::make('to_star')
                        ->label('目标星级')
                        ->integer()
                        ->required()
                        ->default(1)
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定等于 from_star + 1，且不能超过该档最高星。'),
                    Select::make('item_id')
                        ->label('消耗物品')
                        ->options(fn (): array => EquipmentStarModuleSupport::costItemOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('必须关联 `items.item_id`；可配置货币和升星材料。'),
                    TextInput::make('count')
                        ->label('消耗数量')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(1)
                        ->helperText('必须大于 0。'),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->default(true),
                    Textarea::make('remark')
                        ->label('备注')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('set_level')
                    ->label('套装档位')
                    ->formatStateUsing(fn (int|string|null $state): string => AdminOptions::optionLabel(EquipmentStarRule::SET_LEVEL_OPTIONS, $state === null ? null : (string) $state))
                    ->sortable(),
                TextColumn::make('from_star')->label('当前星级')->sortable(),
                TextColumn::make('to_star')->label('目标星级')->sortable(),
                TextColumn::make('item_id')
                    ->label('物品 ID')
                    ->searchable()
                    ->sortable()
                    ->description(fn (EquipmentStarUpgradeCost $record): string => AdminOptions::itemName($record->item_id)),
                TextColumn::make('count')->label('消耗数量')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('set_level')->label('套装档位')->options(EquipmentStarRule::SET_LEVEL_OPTIONS),
                Tables\Filters\SelectFilter::make('to_star')
                    ->label('目标星级')
                    ->options([
                        1 => '1星',
                        2 => '2星',
                        3 => '3星',
                        4 => '4星',
                        5 => '5星',
                        6 => '6星',
                        7 => '7星',
                        8 => '8星',
                        9 => '9星',
                        10 => '10星',
                    ]),
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
            ->modifyQueryUsing(fn ($query) => $query
                ->orderBy('set_level')
                ->orderBy('from_star')
                ->orderBy('to_star')
                ->orderBy('sort_order')
                ->orderBy('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentStarUpgradeCosts::route('/'),
            'create' => Pages\CreateEquipmentStarUpgradeCost::route('/create'),
            'edit' => Pages\EditEquipmentStarUpgradeCost::route('/{record}/edit'),
        ];
    }
}
