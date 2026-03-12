<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialDungeonDropGroupResource\Pages;
use App\Models\MaterialDungeonDropGroup;
use App\Support\AdminOptions;
use App\Support\MaterialDungeonSupport;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MaterialDungeonDropGroupResource extends Resource
{
    protected static ?string $model = MaterialDungeonDropGroup::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '材料副本掉落组';

    protected static ?string $modelLabel = '材料副本掉落组';

    protected static ?string $pluralModelLabel = '材料副本掉落组';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('name')->label('掉落组名称')->required()->maxLength(255),
                    Textarea::make('description')->label('说明')->rows(3)->columnSpanFull(),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(2),
            Section::make('掉落条目')
                ->description('实际战斗结算会读取这里。每条掉落统一从物品库选择，不手填物品 ID 或标签。')
                ->schema([
                    Repeater::make('rewards')
                        ->label('掉落内容')
                        ->table([
                            TableColumn::make('掉落物品'),
                            TableColumn::make('品质'),
                            TableColumn::make('图标'),
                            TableColumn::make('最少数量'),
                            TableColumn::make('最多数量'),
                            TableColumn::make('概率'),
                        ])
                        ->schema([
                            Select::make('item_id')
                                ->label('掉落物品')
                                ->options(fn (): array => AdminOptions::itemOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpan(5),
                            Placeholder::make('rarity_preview')
                                ->label('品质')
                                ->content(fn (Get $get): string => MaterialDungeonSupport::itemRarityLabel((string) $get('item_id')))
                                ->columnSpan(2),
                            Placeholder::make('icon_preview')
                                ->label('图标')
                                ->content(fn (Get $get): string => filled(MaterialDungeonSupport::itemIcon((string) $get('item_id'))) ? '已配置图标' : '未配置')
                                ->columnSpan(2),
                            TextInput::make('count_min')
                                ->label('最少数量')
                                ->integer()
                                ->minValue(1)
                                ->required()
                                ->default(1)
                                ->columnSpan(1),
                            TextInput::make('count_max')
                                ->label('最多数量')
                                ->integer()
                                ->minValue(1)
                                ->required()
                                ->default(1)
                                ->columnSpan(1),
                            TextInput::make('probability')
                                ->label('概率')
                                ->numeric()
                                ->minValue(0.0001)
                                ->maxValue(1)
                                ->step(0.0001)
                                ->required()
                                ->default(1)
                                ->columnSpan(1),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->default([])
                        ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? MaterialDungeonSupport::itemName((string) $state['item_id']) : null)
                        ->reorderable(true)
                        ->reorderableWithButtons(true)
                        ->reorderableWithDragAndDrop(true)
                        ->addActionLabel('新增掉落条目')
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('掉落组名称')->searchable()->sortable(),
                TextColumn::make('rewards')
                    ->label('掉落内容')
                    ->getStateUsing(fn (MaterialDungeonDropGroup $record): string => static::summarizeRewards($record->rewards))
                    ->toggleable(),
                TextColumn::make('reward_count')
                    ->label('条目数')
                    ->getStateUsing(fn (MaterialDungeonDropGroup $record): int => count(is_array($record->rewards) ? $record->rewards : []))
                    ->sortable(false),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
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
            'index' => Pages\ListMaterialDungeonDropGroups::route('/'),
            'create' => Pages\CreateMaterialDungeonDropGroup::route('/create'),
            'edit' => Pages\EditMaterialDungeonDropGroup::route('/{record}/edit'),
        ];
    }

    protected static function summarizeRewards(mixed $state): string
    {
        return collect(is_array($state) ? $state : [])
            ->map(function (mixed $row): string {
                if (! is_array($row)) {
                    return '';
                }

                return MaterialDungeonSupport::itemName((string) ($row['item_id'] ?? ''));
            })
            ->filter()
            ->take(3)
            ->implode('、');
    }
}
