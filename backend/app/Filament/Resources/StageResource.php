<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StageResource\Pages;
use App\Models\Stage;
use App\Support\AdminOptions;
use App\Support\StageConfigSupport;
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
use Illuminate\Validation\ValidationException;

class StageResource extends Resource
{
    protected static ?string $model = Stage::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = '地图关卡';

    protected static ?string $pluralModelLabel = '关卡';

    protected static ?string $modelLabel = '关卡';

    protected static string | \UnitEnum | null $navigationGroup = '掉落与副本';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('stage_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础')
                            ->schema([
                                Section::make('基础信息')
                                    ->description('关卡只维护基础资料和难度列表，不再承载掉落与奖励配置。')
                                    ->schema([
                                        TextInput::make('id')
                                            ->label('关卡 ID')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(ignoreRecord: true)
                                            ->disabled(fn (?Stage $record): bool => $record !== null),
                                        TextInput::make('name')
                                            ->label('关卡名称')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('unlock_min_level')
                                            ->label('解锁等级')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->default(1),
                                        TextInput::make('sort_order')
                                            ->label('排序')
                                            ->required()
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0),
                                        Toggle::make('is_enabled')
                                            ->label('启用')
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('难度')
                            ->schema([
                                Section::make('难度列表')
                                    ->description('每个难度项内部直接维护刷怪参数和怪物池。关卡顶层不再维护刷怪参数、怪物池和掉落。')
                                    ->schema([
                                        Repeater::make('difficulties')
                                            ->hiddenLabel()
                                            ->default(StageConfigSupport::defaultDifficulties())
                                            ->defaultItems(0)
                                            ->minItems(1)
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->addActionLabel('新增难度')
                                            ->itemLabel(fn (array $state): ?string => filled($state['difficulty_name'] ?? null) ? (string) $state['difficulty_name'] : '难度')
                                            ->schema([
                                                TextInput::make('difficulty_name')
                                                    ->label('难度名')
                                                    ->required()
                                                    ->maxLength(32),
                                                TextInput::make('recommended_power')
                                                    ->label('建议战力')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->default(0),
                                                TextInput::make('spawn_interval')
                                                    ->label('刷新间隔（秒）')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0.1)
                                                    ->default(1.6),
                                                TextInput::make('onscreen_limit')
                                                    ->label('同屏上限')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->default(10),
                                                TextInput::make('spawn_radius')
                                                    ->label('刷怪半径')
                                                    ->required()
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->default(220),
                                                Section::make('怪物配置')
                                                    ->schema([
                                                        static::monsterPoolRepeater('normal_monsters', '普通怪池', 'normal'),
                                                        static::monsterPoolRepeater('elite_monsters', '精英怪池', 'elite'),
                                                        static::monsterPoolRepeater('boss_monsters', 'Boss池', 'boss'),
                                                    ])->columnSpanFull(),
                                            ])
                                            ->columns(5)
                                            ->columnSpanFull()
                                            ->collapsed(),
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
                TextColumn::make('id')
                    ->label('关卡 ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('关卡名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unlock_min_level')
                    ->label('解锁等级')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('difficulties')
                    ->label('难度摘要')
                    ->state(fn (Stage $record): string => StageConfigSupport::difficultySummary($record->difficulties))
                    ->wrap(),
                ToggleColumn::make('is_enabled')
                    ->label('启用')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('启用'),
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
            'index' => Pages\ListStages::route('/'),
            'create' => Pages\CreateStage::route('/create'),
            'edit' => Pages\EditStage::route('/{record}/edit'),
        ];
    }

    public static function difficultiesForForm(mixed $stored): array
    {
        return StageConfigSupport::difficultiesForForm($stored);
    }

    public static function normalizeDifficultiesInput(mixed $raw): array
    {
        return StageConfigSupport::normalizeDifficulties($raw);
    }

    public static function validateDifficultiesOrFail(array $rows): void
    {
        StageConfigSupport::validateDifficultiesOrFail($rows);
    }

    public static function normalizeAndValidateDifficultiesOrFail(mixed $raw): array
    {
        $rows = StageConfigSupport::normalizeDifficulties($raw);
        StageConfigSupport::validateDifficultiesOrFail($rows);

        return $rows;
    }

    /**
     * @throws ValidationException
     */
    private static function monsterPoolRepeater(string $field, string $label, string $kind): Repeater
    {
        return Repeater::make($field)
            ->label($label)
            ->table([
                TableColumn::make('怪物'),
                TableColumn::make('权重'),
            ])
            ->schema([
                Select::make('monster_id')
                    ->label('怪物')
                    ->options(fn (): array => AdminOptions::monsterOptions($kind))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(8),
                TextInput::make('weight')
                    ->label('权重')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->default(100)
                    ->columnSpan(4),
            ])
            ->columns(12)
            ->defaultItems(0)
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['monster_id'] ?? null) ? AdminOptions::monsterName((string) $state['monster_id']) : null)
            ->addActionLabel('新增怪物')
            ->columnSpanFull();
    }
}
