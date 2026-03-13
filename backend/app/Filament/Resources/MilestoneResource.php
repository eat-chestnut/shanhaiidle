<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MilestoneResource\Pages;
use App\Models\Milestone;
use App\Support\AdminOptions;
use App\Support\MilestoneModuleSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestoneResource extends Resource
{
    protected static ?string $model = Milestone::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = '成长里程碑';

    protected static ?string $modelLabel = '里程碑';

    protected static ?string $pluralModelLabel = '成长里程碑';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('里程碑首版只支持等级达成和主线章节通关两类条件；每个节点只发单个 reward_item_id，多奖励请改发礼包 item。')
                ->schema([
                    TextInput::make('milestone_id')
                        ->label('里程碑 ID')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?Milestone $record): bool => $record !== null)
                        ->helperText('业务唯一 ID，建议创建后保持稳定。'),
                    TextInput::make('title')
                        ->label('内部名称')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('display_name')
                        ->label('展示名称')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('icon')
                        ->label('图标')
                        ->maxLength(255),
                    Textarea::make('summary')
                        ->label('里程碑说明')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->default(true),
                    Textarea::make('remark')
                        ->label('备注')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('触发条件')
                ->description('condition_type 固定为 player_level_reached / chapter_cleared。支持 pre_milestone_id 前置节点，不做复杂统计条件。')
                ->schema([
                    Select::make('condition_type')
                        ->label('条件类型')
                        ->options(AdminOptions::milestoneConditionTypeOptions())
                        ->required()
                        ->default('player_level_reached')
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('condition_value', $state === 'player_level_reached' ? 1 : null);
                        })
                        ->helperText('仅支持等级达成和主线章节通关两类条件。'),
                    TextInput::make('condition_value')
                        ->label('条件值')
                        ->visible(fn (Get $get): bool => (string) $get('condition_type') === 'player_level_reached')
                        ->dehydrated(fn (Get $get): bool => (string) $get('condition_type') === 'player_level_reached')
                        ->integer()
                        ->minValue(1)
                        ->required(fn (Get $get): bool => (string) $get('condition_type') === 'player_level_reached')
                        ->helperText('等级条件填写等级值，例如 5 / 10 / 20。'),
                    Select::make('condition_value')
                        ->label('条件值')
                        ->visible(fn (Get $get): bool => (string) $get('condition_type') === 'chapter_cleared')
                        ->dehydrated(fn (Get $get): bool => (string) $get('condition_type') === 'chapter_cleared')
                        ->options(AdminOptions::mainStageCombatChapterOptions())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => (string) $get('condition_type') === 'chapter_cleared')
                        ->helperText('章节条件填写主线章节 ID，例如 stage_01 / stage_03 / stage_08。'),
                    Select::make('pre_milestone_id')
                        ->label('前置节点')
                        ->options(fn (Get $get): array => AdminOptions::milestoneOptions((string) $get('milestone_id')))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('为空表示无前置节点；有前置时需先达成前置节点后才可解锁。'),
                ])
                ->columns(3),
            Section::make('奖励信息')
                ->description('奖励统一通过 reward_item_id 引用 items.item_id；一个里程碑只能发一个 reward_item_id。')
                ->schema([
                    Select::make('reward_item_id')
                        ->label('奖励物品')
                        ->options(AdminOptions::itemOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('若需要多奖励，请先定义礼包 item，再把 reward_item_id 指向该礼包 item。'),
                    TextInput::make('reward_count')
                        ->label('奖励数量')
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('milestone_id')->label('里程碑 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('condition_type')
                    ->label('条件类型')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::milestoneConditionTypeOptions(), $state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('condition_value')->label('条件值')->searchable(),
                TextColumn::make('pre_milestone_id')->label('前置节点')->searchable(),
                TextColumn::make('reward_item_id')->label('奖励 item_id')->searchable(),
                TextColumn::make('reward_count')->label('奖励数量')->sortable(),
                IconColumn::make('is_enabled')->label('启用')->boolean()->sortable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('condition_type')
                    ->label('条件类型')
                    ->options(AdminOptions::milestoneConditionTypeOptions()),
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
            'index' => Pages\ListMilestones::route('/'),
            'create' => Pages\CreateMilestone::route('/create'),
            'edit' => Pages\EditMilestone::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeFormDataOrFail(array $data, ?string $currentMilestoneId = null): array
    {
        $milestone = MilestoneModuleSupport::normalizeRow($data);
        MilestoneModuleSupport::validateRowOrFail($milestone, $currentMilestoneId);

        return $milestone;
    }
}
