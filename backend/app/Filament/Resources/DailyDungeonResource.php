<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyDungeonResource\Pages;
use App\Models\DailyDungeon;
use App\Support\AdminOptions;
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

class DailyDungeonResource extends Resource
{
    protected static ?string $model = DailyDungeon::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = '日常副本';

    protected static ?string $modelLabel = '日常副本';

    protected static ?string $pluralModelLabel = '日常副本';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('dungeon_id')->label('副本 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('title')->label('标题')->required()->maxLength(255),
                    TextInput::make('display_name')->label('展示名')->required()->maxLength(255),
                    Select::make('dungeon_type')->label('副本类型')->options(AdminOptions::dungeonTypeOptions())->required(),
                    TextInput::make('icon')->label('图标')->maxLength(255),
                    Textarea::make('summary')->label('摘要')->rows(3)->columnSpanFull(),
                    TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                ])
                ->columns(4),
            Section::make('开放规则')
                ->schema([
                    TextInput::make('unlock_level')->label('开放等级')->required()->integer()->minValue(1)->default(1),
                    Select::make('entry_cost_item_id')
                        ->label('进入消耗物品')
                        ->options(fn (): array => AdminOptions::itemOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('首版可留空，仅保留结构。'),
                    TextInput::make('entry_cost_count')->label('进入消耗数量')->required()->integer()->minValue(0)->default(0),
                    TextInput::make('daily_limit')->label('每日次数')->required()->integer()->minValue(0)->default(2),
                    Toggle::make('sweep_enabled')->label('可扫荡')->default(false),
                ])
                ->columns(5),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dungeon_id')->label('副本 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名')->searchable(),
                TextColumn::make('dungeon_type')
                    ->label('副本类型')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::dungeonTypeOptions(), $state))
                    ->sortable(),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('entry_cost_item_id')
                    ->label('进入消耗物品')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? AdminOptions::itemName($state) : '—'),
                TextColumn::make('daily_limit')->label('每日次数')->sortable(),
                IconColumn::make('sweep_enabled')->label('扫荡')->boolean(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dungeon_type')->label('副本类型')->options(AdminOptions::dungeonTypeOptions()),
                Tables\Filters\TernaryFilter::make('sweep_enabled')->label('扫荡开关'),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyDungeons::route('/'),
            'edit' => Pages\EditDailyDungeon::route('/{record}/edit'),
        ];
    }
}
