<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonsterResource\Pages;
use App\Models\Monster;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\In;

class MonsterResource extends Resource
{
    protected static ?string $model = Monster::class;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = '怪物库';

    protected static ?string $pluralModelLabel = '怪物';

    protected static ?string $modelLabel = '怪物';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('MonsterTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                TextInput::make('id')
                                    ->label('怪物ID')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->disabled(fn (?Monster $record): bool => $record !== null),
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('kind')
                                    ->label('类型')
                                    ->required()
                                    ->options(static::kindOptions())
                                    ->rule(new In(['normal', 'elite', 'boss'])),
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
                        Tab::make('属性')
                            ->schema([
                                TextInput::make('hp')
                                    ->label('生命')
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('atk')
                                    ->label('攻击')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('def')
                                    ->label('防御')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('exp')
                                    ->label('经验')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('dex_gold')
                                    ->label('图鉴金币')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('drop_bonus_percent')
                                    ->label('掉落加成（%）')
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(200)
                                    ->required()
                                    ->default(0),
                            ])->columns(3),
                        Tab::make('行为参数')
                            ->schema([
                                TextInput::make('speed')
                                    ->label('移速')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('radius')
                                    ->label('半径')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('aggro_range')
                                    ->label('警戒范围')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('attack_range')
                                    ->label('攻击范围')
                                    ->integer()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('attack_interval')
                                    ->label('攻速间隔（秒）')
                                    ->numeric()
                                    ->minValue(0.2)
                                    ->maxValue(10)
                                    ->required(),
                            ])->columns(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('怪物ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('kind')
                    ->label('类型')
                    ->formatStateUsing(fn (string $state): string => static::kindOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('hp')->label('生命')->numeric()->sortable(),
                TextColumn::make('atk')->label('攻击')->numeric()->sortable(),
                TextColumn::make('def')->label('防御')->numeric()->sortable(),
                TextColumn::make('exp')->label('经验')->numeric()->sortable(),
                TextColumn::make('dex_gold')->label('图鉴金币')->numeric()->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->label('类型')
                    ->options(static::kindOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
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
            'index' => Pages\ListMonsters::route('/'),
            'create' => Pages\CreateMonster::route('/create'),
            'edit' => Pages\EditMonster::route('/{record}/edit'),
        ];
    }

    protected static function kindOptions(): array
    {
        return [
            'normal' => '普通',
            'elite' => '精英',
            'boss' => 'Boss',
        ];
    }
}
