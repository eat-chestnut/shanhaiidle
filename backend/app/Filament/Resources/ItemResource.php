<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Forms\Get;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '物品库';

    protected static ?string $pluralModelLabel = '物品';

    protected static ?string $modelLabel = '物品';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('ItemTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('物品ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?Item $record): bool => $record !== null),
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('type')
                                    ->label('类型')
                                    ->options(static::typeOptions())
                                    ->required()
                                    ->default('item'),
                                Select::make('rarity')
                                    ->label('稀有度')
                                    ->options(static::rarityOptions())
                                    ->required()
                                    ->default('white'),
                                TextInput::make('icon')
                                    ->label('图标路径')
                                    ->maxLength(255),
                                Toggle::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                                TextInput::make('sort_order')
                                    ->label('排序')
                                    ->integer()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),
                            ])->columns(2),
                        Tab::make('宝石效果')
                            ->schema([
                                Select::make('gem_effect.stat')
                                    ->label('效果属性')
                                    ->options(static::statOptions())
                                    ->required(fn (Get $get): bool => $get('type') === 'gem')
                                    ->hidden(fn (Get $get): bool => $get('type') !== 'gem')
                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'gem'),
                                TextInput::make('gem_effect.val')
                                    ->label('效果数值')
                                    ->integer()
                                    ->required(fn (Get $get): bool => $get('type') === 'gem')
                                    ->hidden(fn (Get $get): bool => $get('type') !== 'gem')
                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'gem'),
                                Textarea::make('trait')
                                    ->label('特性描述')
                                    ->rows(3)
                                    ->hidden(fn (Get $get): bool => $get('type') !== 'gem')
                                    ->dehydrated(fn (Get $get): bool => $get('type') === 'gem'),
                            ])->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('物品ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (string $state): string => static::typeOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('稀有度')
                    ->formatStateUsing(fn (string $state): string => static::rarityOptions()[$state] ?? $state)
                    ->sortable(),
                ToggleColumn::make('is_enabled')
                    ->label('启用')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options(static::typeOptions()),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }

    protected static function typeOptions(): array
    {
        return [
            'item' => '物品',
            'gem' => '宝石',
        ];
    }

    protected static function rarityOptions(): array
    {
        return [
            'white' => '白',
            'blue' => '蓝',
            'gold' => '金',
        ];
    }

    protected static function statOptions(): array
    {
        return [
            'HP' => '生命',
            'ATK' => '攻击',
            'DEF' => '防御',
            'QI' => '气',
            'CRIT_PERCENT' => '暴击',
            'LOOT_BONUS_PERCENT' => '掉落',
        ];
    }
}
