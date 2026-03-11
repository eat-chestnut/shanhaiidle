<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryMapDropResource\Pages;
use App\Models\StoryMapDrop;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
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

class StoryMapDropResource extends Resource
{
    protected static ?string $model = StoryMapDrop::class;
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = '地图掉落';
    protected static ?string $modelLabel = '地图掉落';
    protected static ?string $pluralModelLabel = '地图掉落';
    protected static ?string $navigationGroup = '世界观与主线';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('掉落基础信息')
                ->schema([
                    TextInput::make('drop_id')->label('掉落 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('map_id')->label('地图')->required()->options(fn (): array => AdminOptions::storyMapOptions())->searchable()->preload(),
                    Select::make('drop_tier')->label('掉落层级')->required()->options(AdminOptions::storyDropTierOptions()),
                    TextInput::make('item_id')->label('物品 ID')->required()->maxLength(120)->helperText('剧情资源或礼包道具可直接录入稳定 ID。'),
                    TextInput::make('item_name')->label('物品名称')->required()->maxLength(255),
                    Select::make('item_type')->label('物品类型')->required()->options(AdminOptions::storyDropItemTypeOptions()),
                    TextInput::make('count_min')->label('最少数量')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('count_max')->label('最多数量')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('probability')->label('概率')->numeric()->minValue(0)->maxValue(1)->step(0.0001)->required()->default(1),
                    Toggle::make('first_clear_only')->label('仅首通')->default(false),
                ])
                ->columns(5),
            Section::make('说明与图标')
                ->schema([
                    Textarea::make('source_desc')->label('来源说明')->rows(3)->columnSpanFull(),
                    FileUpload::make('icon_path')->label('图标')->disk('public')->directory('config/story-drops/icons')->image()->imagePreviewHeight('100'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('drop_id')->label('掉落 ID')->searchable()->sortable(),
                TextColumn::make('map_id')->label('地图')->searchable()->sortable(),
                TextColumn::make('drop_tier')->label('层级')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::storyDropTierOptions(), $state))->sortable(),
                TextColumn::make('item_name')->label('物品')->searchable(),
                TextColumn::make('item_type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::storyDropItemTypeOptions(), $state))->sortable(),
                TextColumn::make('probability')->label('概率')->sortable(),
                ToggleColumn::make('first_clear_only')->label('首通'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('drop_tier')->label('掉落层级')->options(AdminOptions::storyDropTierOptions()),
                Tables\Filters\SelectFilter::make('item_type')->label('物品类型')->options(AdminOptions::storyDropItemTypeOptions()),
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
            'index' => Pages\ListStoryMapDrops::route('/'),
            'create' => Pages\CreateStoryMapDrop::route('/create'),
            'edit' => Pages\EditStoryMapDrop::route('/{record}/edit'),
        ];
    }
}
