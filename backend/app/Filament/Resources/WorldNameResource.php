<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorldNameResource\Pages;
use App\Models\WorldName;
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

class WorldNameResource extends Resource
{
    protected static ?string $model = WorldName::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = '世界观命名';
    protected static ?string $modelLabel = '世界观命名';
    protected static ?string $pluralModelLabel = '世界观命名';
    protected static ?string $navigationGroup = '世界观与主线';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('基础信息')
                ->schema([
                    TextInput::make('name_id')->label('命名 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    Select::make('category')->label('分类')->required()->options(AdminOptions::worldNameCategoryOptions()),
                    TextInput::make('sub_category')->label('子分类')->maxLength(64)->default(''),
                    TextInput::make('display_name')->label('显示名')->required()->maxLength(255),
                    TextInput::make('source_text')->label('出处')->maxLength(255),
                    Toggle::make('from_original')->label('沿用原典')->default(false),
                ])
                ->columns(3),
            Section::make('命名说明')
                ->schema([
                    Textarea::make('naming_note')->label('命名备注')->rows(4)->columnSpanFull(),
                    MultiSelect::make('visual_tags')->label('视觉关键词')->options([
                        'forest' => '森林', 'stone' => '岩石', 'fire' => '火焰', 'ice' => '寒霜', 'beast' => '异兽', 'ritual' => '祭祀', 'seal' => '封印',
                    ])->searchable()->preload(),
                    MultiSelect::make('system_usage')->label('系统用途')->options([
                        'map' => '地图显示', 'boss' => 'Boss 显示', 'item' => '物品命名', 'set' => '套装命名', 'dungeon' => '副本命名',
                    ])->searchable()->preload(),
                ])
                ->columns(2),
            Section::make('图片资源与状态')
                ->schema([
                    FileUpload::make('icon_path')->label('图标')->disk('public')->directory('config/world-names/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('image_path')->label('插图')->disk('public')->directory('config/world-names/images')->image()->imagePreviewHeight('140'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_id')->label('命名 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('显示名')->searchable()->sortable(),
                TextColumn::make('category')->label('分类')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::worldNameCategoryOptions(), $state))->sortable(),
                TextColumn::make('sub_category')->label('子分类')->toggleable(),
                ToggleColumn::make('from_original')->label('原典'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->label('分类')->options(AdminOptions::worldNameCategoryOptions()),
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
            'index' => Pages\ListWorldNames::route('/'),
            'create' => Pages\CreateWorldName::route('/create'),
            'edit' => Pages\EditWorldName::route('/{record}/edit'),
        ];
    }
}
