<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonsterResource\Pages;
use App\Models\Monster;
use App\Support\AdminOptions;
use App\Support\MonsterDropSupport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\In;

class MonsterResource extends Resource
{
    protected static ?string $model = Monster::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = '怪物库';

    protected static ?string $pluralModelLabel = '怪物';

    protected static ?string $modelLabel = '怪物';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('monster_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('基础字段')
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('怪物 ID')
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
                                            ->rule(new In(['normal', 'elite', 'boss']))
                                            ->helperText('怪物类型会影响难度怪池可选范围。'),
                                        FileUpload::make('icon')
                                            ->label('图标')
                                            ->disk('public')
                                            ->directory('config/monster-icons')
                                            ->image()
                                            ->imagePreviewHeight('120'),
                                        Toggle::make('is_enabled')
                                            ->label('启用')
                                            ->default(true),
                                        TextInput::make('sort_order')
                                            ->label('排序')
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->default(0),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('属性参数')
                            ->schema([
                                Section::make('数值')
                                    ->schema([
                                        TextInput::make('hp')->label('生命')->integer()->minValue(1)->required(),
                                        TextInput::make('atk')->label('攻击')->integer()->minValue(0)->required(),
                                        TextInput::make('def')->label('防御')->integer()->minValue(0)->required(),
                                        TextInput::make('exp')->label('经验')->integer()->minValue(0)->required(),
                                        TextInput::make('dex_gold')->label('图鉴金币')->integer()->minValue(0)->required(),
                                        TextInput::make('drop_bonus_percent')->label('掉落加成（%）')->integer()->minValue(0)->maxValue(200)->required()->default(0),
                                    ])
                                    ->columns(3),
                                Section::make('行为参数')
                                    ->schema([
                                        TextInput::make('speed')->label('移速')->integer()->minValue(0)->required(),
                                        TextInput::make('radius')->label('半径')->integer()->minValue(0)->required(),
                                        TextInput::make('aggro_range')->label('警戒范围')->integer()->minValue(0)->required(),
                                        TextInput::make('attack_range')->label('攻击范围')->integer()->minValue(0)->required(),
                                        TextInput::make('attack_interval')->label('攻速间隔（秒）')->numeric()->minValue(0.2)->maxValue(10)->required(),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('掉落列表')
                            ->schema([
                                Section::make('怪物掉落')
                                    ->description('怪物被击杀后的掉落统一从这里读取。关卡和难度不再维护掉落。')
                                    ->schema([
                                        Repeater::make('drops')
                                            ->label('掉落配置')
                                            ->table([
                                                TableColumn::make('掉落物品'),
                                                TableColumn::make('最小数量'),
                                                TableColumn::make('最大数量'),
                                                TableColumn::make('掉落概率'),
                                                TableColumn::make('启用'),
                                            ])
                                            ->schema([
                                                Select::make('item_id')
                                                    ->label('掉落物品')
                                                    ->options(fn (): array => AdminOptions::itemOptions())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->columnSpan(4),
                                                TextInput::make('count_min')
                                                    ->label('最小数量')
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->required()
                                                    ->default(1)
                                                    ->columnSpan(2),
                                                TextInput::make('count_max')
                                                    ->label('最大数量')
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->required()
                                                    ->default(1)
                                                    ->columnSpan(2),
                                                TextInput::make('drop_rate')
                                                    ->label('掉落概率')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(1)
                                                    ->step(0.0001)
                                                    ->helperText('留空默认为 1。')
                                                    ->columnSpan(2),
                                                Toggle::make('is_enabled')
                                                    ->label('启用')
                                                    ->default(true)
                                                    ->columnSpan(2),
                                            ])
                                            ->columns(12)
                                            ->defaultItems(0)
                                            ->default([])
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? MonsterDropSupport::itemName((string) $state['item_id']) : null)
                                            ->addActionLabel('新增掉落项')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('怪物 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('kind')->label('类型')->formatStateUsing(fn (string $state): string => static::kindOptions()[$state] ?? $state)->sortable(),
                TextColumn::make('hp')->label('生命')->numeric()->sortable(),
                TextColumn::make('atk')->label('攻击')->numeric()->sortable(),
                TextColumn::make('def')->label('防御')->numeric()->sortable(),
                TextColumn::make('drops')->label('掉落摘要')->state(fn (Monster $record): string => MonsterDropSupport::summary($record->drops))->wrap(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->label('类型')->options(static::kindOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
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
            'index' => Pages\ListMonsters::route('/'),
            'create' => Pages\CreateMonster::route('/create'),
            'edit' => Pages\EditMonster::route('/{record}/edit'),
        ];
    }

    public static function dropsForForm(mixed $stored): array
    {
        return MonsterDropSupport::dropsForForm($stored);
    }

    public static function normalizeAndValidateDropsOrFail(mixed $raw): array
    {
        return MonsterDropSupport::normalizeDropsOrFail($raw);
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
