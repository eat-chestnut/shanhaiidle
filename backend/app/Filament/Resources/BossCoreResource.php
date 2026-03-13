<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BossCoreResource\Pages;
use App\Models\BossCore;
use App\Models\BossCoreEffect;
use App\Support\AdminOptions;
use App\Support\BossCoreModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BossCoreResource extends Resource
{
    protected static ?string $model = BossCore::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Boss核心';

    protected static ?string $modelLabel = 'Boss核心';

    protected static ?string $pluralModelLabel = 'Boss核心配置';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('boss_core_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('Boss 核心基础')
                                    ->description('Boss 核心独立于宝石模块，也独立于套装件数效果。本轮每个核心只维护 1 条主效果。')
                                    ->schema([
                                        TextInput::make('core_id')
                                            ->label('core_id')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(ignoreRecord: true)
                                            ->disabled(fn (?BossCore $record): bool => $record !== null),
                                        Select::make('item_id')
                                            ->label('item_id')
                                            ->options(fn (): array => BossCoreModuleSupport::carrierItemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->unique(ignoreRecord: true, column: 'item_id')
                                            ->disabled(fn (?BossCore $record): bool => $record !== null),
                                        TextInput::make('core_name')
                                            ->label('core_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('display_name')
                                            ->label('display_name')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('source_boss_id')
                                            ->label('source_boss_id')
                                            ->options(fn (): array => AdminOptions::monsterOptions('boss'))
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Select::make('recommended_sect')
                                            ->label('recommended_sect')
                                            ->options(BossCore::RECOMMENDED_SECT_OPTIONS)
                                            ->required()
                                            ->default('none'),
                                        Select::make('recommended_build')
                                            ->label('recommended_build')
                                            ->options(BossCore::RECOMMENDED_BUILD_OPTIONS)
                                            ->required()
                                            ->default('burst'),
                                        Select::make('quality')
                                            ->label('quality')
                                            ->options(AdminOptions::qualityOptions())
                                            ->required()
                                            ->default('gold'),
                                        Select::make('rarity')
                                            ->label('rarity')
                                            ->options(AdminOptions::rarityOptions())
                                            ->required()
                                            ->default('gold'),
                                        TextInput::make('icon')
                                            ->label('icon')
                                            ->maxLength(255),
                                        Textarea::make('summary')
                                            ->label('summary')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        TextInput::make('drop_rate_note')
                                            ->label('drop_rate_note')
                                            ->maxLength(255),
                                        TextInput::make('sort_order')
                                            ->label('sort_order')
                                            ->integer()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                        Toggle::make('is_enabled')
                                            ->label('is_enabled')
                                            ->default(true),
                                        Textarea::make('remark')
                                            ->label('remark')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('核心效果')
                            ->schema([
                                Section::make('boss_core_effects')
                                    ->description('首版每个 Boss 核心仅保留 1 条主效果。')
                                    ->schema([
                                        static::effectsRepeater(),
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
                TextColumn::make('core_id')->label('core_id')->searchable()->sortable(),
                TextColumn::make('item_id')->label('item_id')->searchable()->sortable(),
                TextColumn::make('display_name')->label('display_name')->searchable()->sortable(),
                TextColumn::make('source_boss_id')->label('source_boss_id')->searchable()->sortable(),
                TextColumn::make('recommended_sect')
                    ->label('recommended_sect')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(BossCore::RECOMMENDED_SECT_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('recommended_build')
                    ->label('recommended_build')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(BossCore::RECOMMENDED_BUILD_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('quality')
                    ->label('quality')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::qualityOptions(), $state))
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_enabled')->label('is_enabled')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('recommended_sect')->label('recommended_sect')->options(BossCore::RECOMMENDED_SECT_OPTIONS),
                Tables\Filters\SelectFilter::make('recommended_build')->label('recommended_build')->options(BossCore::RECOMMENDED_BUILD_OPTIONS),
                Tables\Filters\SelectFilter::make('quality')->label('quality')->options(AdminOptions::qualityOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('is_enabled'),
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
            'index' => Pages\ListBossCores::route('/'),
            'create' => Pages\CreateBossCore::route('/create'),
            'edit' => Pages\EditBossCore::route('/{record}/edit'),
        ];
    }

    public static function effectsForForm(BossCore $record): array
    {
        return $record->effects()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BossCoreEffect $row): array => [
                'effect_key' => (string) $row->effect_key,
                'value_type' => (string) $row->value_type,
                'value' => $row->value + 0,
                'summary' => $row->summary !== null ? (string) $row->summary : null,
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
                'remark' => $row->remark !== null ? (string) $row->remark : null,
            ])
            ->all();
    }

    /**
     * @return array{core: array<string, mixed>, effects: array<int, array<string, mixed>>}
     */
    public static function normalizeFormDataOrFail(array $data): array
    {
        return BossCoreModuleSupport::normalizeSingleCoreFormOrFail($data);
    }

    public static function syncRelations(BossCore $record, array $effects): void
    {
        $record->effects()->delete();

        foreach ($effects as $row) {
            $record->effects()->create($row);
        }
    }

    private static function effectsRepeater(): Repeater
    {
        return Repeater::make('effects')
            ->label('核心效果')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->addActionLabel('新增核心效果')
            ->schema([
                Select::make('effect_key')
                    ->label('effect_key')
                    ->options(BossCore::EFFECT_KEY_OPTIONS)
                    ->searchable()
                    ->required()
                    ->columnSpan(4),
                Select::make('value_type')
                    ->label('value_type')
                    ->options(BossCore::VALUE_TYPE_OPTIONS)
                    ->required()
                    ->columnSpan(2),
                TextInput::make('value')
                    ->label('value')
                    ->numeric()
                    ->required()
                    ->columnSpan(2),
                TextInput::make('sort_order')
                    ->label('sort_order')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->columnSpan(2),
                Toggle::make('is_enabled')
                    ->label('is_enabled')
                    ->default(true)
                    ->columnSpan(2),
                TextInput::make('summary')
                    ->label('summary')
                    ->maxLength(255)
                    ->columnSpan(10),
                Textarea::make('remark')
                    ->label('remark')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
