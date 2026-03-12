<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryBossResource\Pages;
use App\Models\StoryBoss;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
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

class StoryBossResource extends Resource
{
    protected static ?string $model = StoryBoss::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Boss基础';
    protected static ?string $modelLabel = 'Boss';
    protected static ?string $pluralModelLabel = 'Boss基础';
    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('boss_id')->label('Boss ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('boss_name')->label('Boss 名称')->required()->maxLength(255),
                    Select::make('boss_type')->label('Boss 类型')->required()->options(AdminOptions::bossTypeOptions()),
                    Select::make('map_id')->label('所属地图')->options(fn (): array => AdminOptions::storyMapOptions())->searchable()->preload()->nullable(),
                    Select::make('chapter_id')->label('所属章节')->options(fn (): array => AdminOptions::storyChapterOptions())->searchable()->preload()->nullable(),
                    TextInput::make('source_text')->label('出处')->maxLength(255),
                    TextInput::make('recommend_level')->label('推荐等级')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('recommend_power')->label('推荐战力')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(4),
            Section::make('文案与标签')
                ->schema([
                    Textarea::make('lore_role')->label('世界观定位')->rows(3)->columnSpanFull(),
                    Select::make('visual_tags')->label('视觉关键词')->options([
                        'beast' => '异兽', 'ritual' => '祭祀', 'fire' => '火焰', 'ice' => '寒霜', 'water' => '水域', 'shadow' => '妖影', 'seal' => '封印',
                    ])->multiple()->searchable()->preload(),
                    Select::make('combat_tags')->label('战斗关键词')->options([
                        'melee' => '近战', 'ranged' => '远程', 'summon' => '召唤', 'poison' => '中毒', 'burn' => '灼烧', 'freeze' => '冻结', 'aoe' => '范围伤害',
                    ])->multiple()->searchable()->preload(),
                    Textarea::make('intro_copy')->label('出场文案')->rows(4)->columnSpanFull(),
                    Textarea::make('clear_copy')->label('击败文案')->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('图片资源与状态')
                ->schema([
                    FileUpload::make('icon_path')->label('头像图标')->disk('public')->directory('config/story-bosses/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('portrait_path')->label('立绘')->disk('public')->directory('config/story-bosses/portraits')->image()->imagePreviewHeight('140'),
                    FileUpload::make('banner_path')->label('Boss 横幅')->disk('public')->directory('config/story-bosses/banners')->image()->imagePreviewHeight('140'),
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
                TextColumn::make('boss_id')->label('Boss ID')->searchable()->sortable(),
                TextColumn::make('boss_name')->label('Boss 名称')->searchable()->sortable(),
                TextColumn::make('boss_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::bossTypeOptions(), $state))->sortable(),
                TextColumn::make('map_id')->label('地图')->toggleable(),
                TextColumn::make('recommend_level')->label('推荐等级')->sortable(),
                TextColumn::make('recommend_power')->label('推荐战力')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('boss_type')->label('Boss 类型')->options(AdminOptions::bossTypeOptions()),
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
            'index' => Pages\ListStoryBosses::route('/'),
            'create' => Pages\CreateStoryBoss::route('/create'),
            'edit' => Pages\EditStoryBoss::route('/{record}/edit'),
        ];
    }
}
