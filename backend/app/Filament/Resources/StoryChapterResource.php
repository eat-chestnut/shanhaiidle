<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryChapterResource\Pages;
use App\Models\StoryChapter;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StoryChapterResource extends Resource
{
    protected static ?string $model = StoryChapter::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = '章节文案';
    protected static ?string $modelLabel = '章节';
    protected static ?string $pluralModelLabel = '章节文案';
    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('维护章节编号、归属地图、等级段与启用状态。')
                ->schema([
                    TextInput::make('chapter_id')->label('章节 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('volume_name')->label('卷名')->required()->maxLength(120)->default('南山一经'),
                    TextInput::make('chapter_no')->label('章节序号')->integer()->minValue(0)->required()->default(0)->helperText('数值越小越靠前。'),
                    TextInput::make('chapter_name')->label('章节名称')->required()->maxLength(255),
                    Select::make('chapter_role')->label('章节定位')->required()->options(AdminOptions::chapterRoleOptions()),
                    Select::make('map_id')->label('关联地图')->options(fn (): array => AdminOptions::storyMapOptions())->searchable()->preload()->helperText('没有对应地图时可留空。'),
                    Select::make('boss_id')->label('关联 Boss')->options(fn (): array => AdminOptions::storyBossOptions())->searchable()->preload()->helperText('功能章节可不绑定 Boss。'),
                    TextInput::make('level_min')->label('等级下限')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('level_max')->label('等级上限')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(3),
            Section::make('章节文案')
                ->description('这部分文案会用于主线推进、Boss 出场和通关表现。')
                ->schema([
                    Textarea::make('intro_copy')->label('开场文案')->rows(4)->columnSpanFull(),
                    Textarea::make('objective_copy')->label('目标文案')->rows(3)->columnSpanFull(),
                    Textarea::make('boss_intro_copy')->label('Boss 出场文案')->rows(3)->columnSpanFull(),
                    Textarea::make('clear_copy')->label('结尾文案')->rows(3)->columnSpanFull(),
                    Textarea::make('next_hook_copy')->label('后续钩子文案')->rows(3)->columnSpanFull(),
                ])
                ->columns(1),
            Section::make('图片资源')
                ->description('暂时可直接上传或替换路径，方便后续补正式资源。')
                ->schema([
                    FileUpload::make('icon_path')->label('图标')->disk('public')->directory('config/story-chapters/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('banner_path')->label('横幅图')->disk('public')->directory('config/story-chapters/banners')->image()->imagePreviewHeight('100'),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chapter_id')->label('章节 ID')->searchable()->sortable(),
                TextColumn::make('chapter_name')->label('章节名称')->searchable()->sortable(),
                TextColumn::make('chapter_role')->label('章节定位')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::chapterRoleOptions(), $state))->sortable(),
                TextColumn::make('map_id')->label('关联地图')->placeholder('—')->toggleable(),
                TextColumn::make('boss_id')->label('关联 Boss')->placeholder('—')->toggleable(),
                TextColumn::make('level_range')->label('等级段')->state(fn (StoryChapter $record): string => $record->level_min . ' - ' . $record->level_max),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('updated_at')->label('更新时间')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('chapter_role')->label('章节定位')->options(AdminOptions::chapterRoleOptions()),
                Tables\Filters\SelectFilter::make('map_id')->label('关联地图')->options(fn (): array => AdminOptions::storyMapOptions()),
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
            'index' => Pages\ListStoryChapters::route('/'),
            'create' => Pages\CreateStoryChapter::route('/create'),
            'edit' => Pages\EditStoryChapter::route('/{record}/edit'),
        ];
    }
}
