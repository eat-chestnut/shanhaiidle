<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MainStageChapterResource\Pages;
use App\Models\MainStageChapter;
use App\Support\AdminOptions;
use App\Support\MainStageModuleSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MainStageChapterResource extends Resource
{
    protected static ?string $model = MainStageChapter::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = '主线章节';

    protected static ?string $modelLabel = '主线章节';

    protected static ?string $pluralModelLabel = '主线章节';

    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('章节基础')
                ->description('序章、正式主线章、终章都在这里维护。功能章不挂战斗难度。')
                ->schema([
                    TextInput::make('chapter_id')->label('章节 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('chapter_name')->label('章节名称')->required()->maxLength(255),
                    Select::make('chapter_type')->label('章节类型')->required()->options(MainStageModuleSupport::chapterTypeOptions()),
                    Select::make('chapter_flow_type')->label('流程类型')->required()->options(MainStageModuleSupport::chapterFlowTypeOptions()),
                    Toggle::make('is_functional_chapter')->label('功能章')->default(false),
                    Toggle::make('has_combat')->label('含战斗')->default(false),
                    Toggle::make('has_sect_selection')->label('含宗门选择')->default(false),
                    Toggle::make('has_shanshen_ritual')->label('含山神祭祀')->default(false),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->required()->integer()->default(0)->minValue(0),
                ])->columns(5),
            Section::make('推进与展示')
                ->schema([
                    TextInput::make('suggested_level_min')->label('建议等级下限')->required()->integer()->minValue(1)->default(1),
                    TextInput::make('suggested_level_max')->label('建议等级上限')->required()->integer()->minValue(1)->default(1),
                    TextInput::make('suggested_power')->label('建议战力')->required()->integer()->minValue(0)->default(0),
                    TextInput::make('unlock_level')->label('解锁等级')->required()->integer()->minValue(1)->default(1),
                    Select::make('unlock_prev_chapter_id')->label('前置章节')
                        ->options(fn (?MainStageChapter $record): array => AdminOptions::mainStageChapterOptions($record?->chapter_id))
                        ->searchable()
                        ->preload()
                        ->placeholder('无前置'),
                    TextInput::make('mountain_name')->label('主要山名')->maxLength(255),
                    TextInput::make('boss_display_name')->label('Boss 展示名')->maxLength(255),
                    Textarea::make('remark')->label('备注')->rows(3)->columnSpanFull(),
                ])->columns(5),
            Section::make('功能章行为')
                ->description('仅功能章使用。正式战斗章通常保持为空。')
                ->schema([
                    Toggle::make('sect_selection_enabled')->label('启用宗门选择')->default(false),
                    TextInput::make('sect_selection_pool_id')->label('宗门选择池 ID')->maxLength(120),
                    Toggle::make('shanshen_ritual_enabled')->label('启用山神祭祀')->default(false),
                    TextInput::make('next_version_teaser_title')->label('下版本伏笔标题')->maxLength(255),
                    TextInput::make('next_world_key')->label('下版本世界 Key')->maxLength(120),
                    Textarea::make('teaser_desc')->label('伏笔说明')->rows(3)->columnSpanFull(),
                ])->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chapter_id')->label('章节 ID')->searchable()->sortable(),
                TextColumn::make('chapter_name')->label('章节名称')->searchable()->sortable(),
                TextColumn::make('chapter_type')->label('章节类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(MainStageModuleSupport::chapterTypeOptions(), $state))->sortable(),
                IconColumn::make('is_functional_chapter')->label('功能章')->boolean(),
                IconColumn::make('has_combat')->label('战斗章')->boolean(),
                TextColumn::make('level_range')->label('建议等级')->state(fn (MainStageChapter $record): string => $record->suggested_level_min . '-' . $record->suggested_level_max),
                TextColumn::make('suggested_power')->label('建议战力')->sortable(),
                TextColumn::make('mountain_name')->label('主要山名')->toggleable(),
                TextColumn::make('boss_display_name')->label('Boss 展示名')->toggleable(),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('chapter_type')->label('章节类型')->options(MainStageModuleSupport::chapterTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_functional_chapter')->label('功能章'),
                Tables\Filters\TernaryFilter::make('has_combat')->label('战斗章'),
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
            'index' => Pages\ListMainStageChapters::route('/'),
            'create' => Pages\CreateMainStageChapter::route('/create'),
            'edit' => Pages\EditMainStageChapter::route('/{record}/edit'),
        ];
    }
}
