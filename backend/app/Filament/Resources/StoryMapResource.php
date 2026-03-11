<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryMapResource\Pages;
use App\Models\StoryMap;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StoryMapResource extends Resource
{
    protected static ?string $model = StoryMap::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = '地图基础';
    protected static ?string $modelLabel = '地图';
    protected static ?string $pluralModelLabel = '地图基础';
    protected static ?string $navigationGroup = '世界观与主线';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('map_id')->label('地图 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('map_name')->label('地图名称')->required()->maxLength(255),
                    Select::make('map_type')->label('地图类型')->required()->options(AdminOptions::mapTypeOptions()),
                    TextInput::make('map_order')->label('地图顺序')->integer()->minValue(0)->required()->default(0),
                    TextInput::make('volume_name')->label('卷名')->required()->maxLength(120)->default('南山一经'),
                    TextInput::make('source_text')->label('出处')->maxLength(255),
                    TextInput::make('level_min')->label('等级下限')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('level_max')->label('等级上限')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('recommend_power')->label('推荐战力')->integer()->minValue(0)->required()->default(0),
                    Select::make('chapter_id')->label('所属章节')->options(fn (): array => AdminOptions::storyChapterOptions())->searchable()->preload()->nullable(),
                    Select::make('boss_id')->label('关联 Boss')->options(fn (): array => AdminOptions::storyBossOptions())->searchable()->preload()->nullable(),
                ])
                ->columns(3),
            Section::make('主题与掉落池')
                ->schema([
                    MultiSelect::make('theme_tags')->label('主题标签')->options([
                        'forest' => '森林', 'ore' => '矿脉', 'poison' => '毒瘴', 'seal' => '封印', 'ritual' => '祭坛', 'water' => '水域', 'ancient' => '古迹',
                    ])->searchable()->preload(),
                    Textarea::make('atmosphere_desc')->label('氛围描述')->rows(3)->columnSpanFull(),
                    Textarea::make('unlock_condition')->label('解锁条件')->rows(2)->columnSpanFull(),
                    TextInput::make('normal_drop_pool')->label('普通掉落池')->maxLength(120),
                    TextInput::make('elite_drop_pool')->label('精英掉落池')->maxLength(120),
                    TextInput::make('boss_drop_pool')->label('Boss 掉落池')->maxLength(120),
                ])
                ->columns(3),
            Section::make('图片资源与状态')
                ->schema([
                    FileUpload::make('icon_path')->label('小图标')->disk('public')->directory('config/story-maps/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('banner_path')->label('横幅图')->disk('public')->directory('config/story-maps/banners')->image()->imagePreviewHeight('140'),
                    FileUpload::make('bg_path')->label('背景图')->disk('public')->directory('config/story-maps/backgrounds')->image()->imagePreviewHeight('140'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(5),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('map_id')->label('地图 ID')->searchable()->sortable(),
                TextColumn::make('map_name')->label('地图名称')->searchable()->sortable(),
                TextColumn::make('map_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::mapTypeOptions(), $state))->sortable(),
                TextColumn::make('chapter_id')->label('章节')->toggleable(),
                TextColumn::make('boss_id')->label('Boss')->toggleable(),
                TextColumn::make('level_min')->label('等级段')->formatStateUsing(fn ($state, StoryMap $record): string => $record->level_min . '-' . $record->level_max),
                TextColumn::make('recommend_power')->label('推荐战力')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('map_type')->label('地图类型')->options(AdminOptions::mapTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
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
            'index' => Pages\ListStoryMaps::route('/'),
            'create' => Pages\CreateStoryMap::route('/create'),
            'edit' => Pages\EditStoryMap::route('/{record}/edit'),
        ];
    }
}
