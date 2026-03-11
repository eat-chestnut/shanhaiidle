<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialDungeonResource\Pages;
use App\Models\MaterialDungeon;
use App\Support\AdminOptions;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MaterialDungeonResource extends Resource
{
    protected static ?string $model = MaterialDungeon::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = '材料副本';

    protected static ?string $modelLabel = '材料副本';

    protected static ?string $pluralModelLabel = '材料副本';

    protected static ?string $navigationGroup = '掉落与副本';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('dungeon_id')->label('副本 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                    Select::make('dungeon_type')->label('副本类型')->required()->options(AdminOptions::dungeonTypeOptions()),
                    TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('stamina_cost')->label('体力消耗')->integer()->minValue(0)->required()->default(10),
                    TextInput::make('daily_limit')->label('每日次数')->integer()->minValue(0)->required()->default(10),
                ])
                ->columns(3),
            Section::make('掉落与层级')
                ->schema([
                    KeyValue::make('layer_config')->label('层级配置')->keyLabel('层级')->valueLabel('配置值')->columnSpanFull(),
                    TagsInput::make('drop_pools')->label('掉落池标签')->placeholder('输入后回车')->helperText('用于关联前端副本页或掉落池。')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('说明与状态')
                ->schema([
                    Textarea::make('description')->label('描述')->rows(3)->columnSpanFull(),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dungeon_id')->label('副本 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('dungeon_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::dungeonTypeOptions(), $state))->sortable(),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('stamina_cost')->label('体力')->sortable(),
                TextColumn::make('daily_limit')->label('每日次数')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dungeon_type')->label('副本类型')->options(AdminOptions::dungeonTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
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
            'index' => Pages\ListMaterialDungeons::route('/'),
            'create' => Pages\CreateMaterialDungeon::route('/create'),
            'edit' => Pages\EditMaterialDungeon::route('/{record}/edit'),
        ];
    }
}
