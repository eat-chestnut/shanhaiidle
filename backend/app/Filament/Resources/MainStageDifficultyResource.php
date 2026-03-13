<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MainStageDifficultyResource\Pages;
use App\Models\MainStageDifficulty;
use App\Support\AdminOptions;
use App\Support\MainStageModuleSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MainStageDifficultyResource extends Resource
{
    protected static ?string $model = MainStageDifficulty::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '主线难度';

    protected static ?string $modelLabel = '主线难度';

    protected static ?string $pluralModelLabel = '主线难度';

    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('难度基础')
                ->description('这里只挂载怪物池、Boss 和奖励组关系，不录入怪物细节。')
                ->schema([
                    TextInput::make('difficulty_id')->label('难度 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('chapter_id')->label('所属章节')
                        ->options(fn (): array => AdminOptions::mainStageCombatChapterOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('difficulty_code')->label('难度编码')->options(MainStageModuleSupport::difficultyCodeOptions())->required(),
                    TextInput::make('difficulty_name')->label('难度名称')->required()->maxLength(64),
                    TextInput::make('sort_order')->label('排序')->required()->integer()->default(0)->minValue(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])->columns(3),
            Section::make('挂载关系')
                ->schema([
                    TextInput::make('normal_monster_pool_id')->label('普通怪池 ID')->required()->maxLength(160)->helperText('填写正式怪物池 ID，不在这里维护怪物细节。'),
                    TextInput::make('elite_monster_pool_id')->label('精英怪池 ID')->required()->maxLength(160),
                    TextInput::make('boss_id')->label('Boss ID')->required()->maxLength(160)->helperText('填写正式 Boss monster_id。'),
                    TextInput::make('drop_preview_group_id')->label('掉落预览组 ID')->required()->maxLength(160),
                    TextInput::make('first_clear_reward_group_id')->label('首通奖励组 ID')->required()->maxLength(160),
                    Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                ])->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('difficulty_id')->label('难度 ID')->searchable()->sortable(),
                TextColumn::make('chapter.chapter_name')->label('章节')->sortable()->searchable(),
                TextColumn::make('difficulty_code')->label('难度编码')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(MainStageModuleSupport::difficultyCodeOptions(), $state))->sortable(),
                TextColumn::make('difficulty_name')->label('难度名称')->searchable(),
                TextColumn::make('boss_id')->label('Boss ID')->toggleable(),
                TextColumn::make('drop_preview_group_id')->label('掉落预览组')->toggleable(),
                TextColumn::make('first_clear_reward_group_id')->label('首通奖励组')->toggleable(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('chapter_id')->label('所属章节')->options(fn (): array => AdminOptions::mainStageCombatChapterOptions()),
                Tables\Filters\SelectFilter::make('difficulty_code')->label('难度编码')->options(MainStageModuleSupport::difficultyCodeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
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
            'index' => Pages\ListMainStageDifficulties::route('/'),
            'create' => Pages\CreateMainStageDifficulty::route('/create'),
            'edit' => Pages\EditMainStageDifficulty::route('/{record}/edit'),
        ];
    }
}
