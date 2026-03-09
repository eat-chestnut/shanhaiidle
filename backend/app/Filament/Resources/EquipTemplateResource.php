<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipTemplateResource\Pages;
use App\Models\EquipmentSet;
use App\Models\EquipTemplate;
use App\Models\SkillCatalog;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipTemplateResource extends Resource
{
    protected static ?string $model = EquipTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = '装备模板';

    protected static ?string $pluralModelLabel = '装备模板';

    protected static ?string $modelLabel = '装备模板';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('EquipTemplateTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('模板ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?EquipTemplate $record): bool => $record !== null),
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('slot')
                                    ->label('槽位')
                                    ->required()
                                    ->options(static::slotOptions()),
                                Select::make('rarity')
                                    ->label('稀有度')
                                    ->required()
                                    ->options(static::rarityOptions()),
                                Select::make('set_id')
                                    ->label('所属套装')
                                    ->options(fn (): array => static::equipmentSetOptions())
                                    ->searchable()
                                    ->nullable()
                                    ->rule('nullable|exists:equipment_sets,id'),
                                TextInput::make('icon')
                                    ->label('图标路径')
                                    ->maxLength(255)
                                    ->default(''),
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
                        Tab::make('主属性区间')
                            ->schema([
                                Select::make('main_stat')
                                    ->label('主属性')
                                    ->required()
                                    ->options(static::statOptions()),
                                TextInput::make('main_min')
                                    ->label('最小值')
                                    ->integer()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),
                                TextInput::make('main_max')
                                    ->label('最大值')
                                    ->integer()
                                    ->required()
                                    ->minValue(fn (Get $get): int => (int) ($get('main_min') ?? 0))
                                    ->rule('gte:main_min')
                                    ->default(0),
                                TextInput::make('unidentified_chance')
                                    ->label('未鉴定概率')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->rule('between:0,1'),
                            ])->columns(3),
                        Tab::make('特效 effects')
                            ->schema([
                                Repeater::make('effects')
                                    ->label('特效列表')
                                    ->default([])
                                    ->schema([
                                        Select::make('type')
                                            ->label('特效类型')
                                            ->required()
                                            ->options(static::effectTypeOptions()),
                                        TextInput::make('val')
                                            ->label('数值')
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->default(0),
                                        Select::make('stat')
                                            ->label('属性')
                                            ->options(static::statOptions())
                                            ->required(fn (Get $get): bool => $get('type') === 'stat')
                                            ->hidden(fn (Get $get): bool => $get('type') !== 'stat')
                                            ->dehydrated(fn (Get $get): bool => $get('type') === 'stat'),
                                        Select::make('skill_id')
                                            ->label('技能')
                                            ->options(fn (): array => static::skillOptions())
                                            ->searchable()
                                            ->required(fn (Get $get): bool => $get('type') === 'skill_level')
                                            ->rule('exists:skills_catalog,id')
                                            ->hidden(fn (Get $get): bool => $get('type') !== 'skill_level')
                                            ->dehydrated(fn (Get $get): bool => $get('type') === 'skill_level'),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('模板ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slot')
                    ->label('槽位')
                    ->formatStateUsing(fn (string $state): string => static::slotOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('rarity')
                    ->label('稀有度')
                    ->formatStateUsing(fn (string $state): string => static::rarityOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('main_stat')
                    ->label('主属性')
                    ->formatStateUsing(fn (string $state): string => static::statOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('main_range')
                    ->label('主属性区间')
                    ->state(fn (EquipTemplate $record): string => sprintf('%d~%d', (int) $record->main_min, (int) $record->main_max)),
                TextColumn::make('unidentified_chance')
                    ->label('未鉴定概率')
                    ->formatStateUsing(fn (mixed $state): string => sprintf('%.3f', (float) $state)),
                TextColumn::make('set_id')
                    ->label('套装')
                    ->formatStateUsing(fn (mixed $state): string => static::equipmentSetOptions()[(string) $state] ?? '—'),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
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
            'index' => Pages\ListEquipTemplates::route('/'),
            'create' => Pages\CreateEquipTemplate::route('/create'),
            'edit' => Pages\EditEquipTemplate::route('/{record}/edit'),
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

    protected static function slotOptions(): array
    {
        return [
            'weapon' => '武器',
            'helm' => '头盔',
            'armor' => '盔甲',
            'pants' => '护腿',
            'shoes' => '鞋子',
            'cloak' => '披风',
            'ring' => '戒指',
            'bracelet' => '手镯',
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

    protected static function effectTypeOptions(): array
    {
        return [
            'stat' => '属性加成',
            'skill_level' => '技能等级',
        ];
    }

    protected static function skillOptions(): array
    {
        return SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function equipmentSetOptions(): array
    {
        return EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }
}
