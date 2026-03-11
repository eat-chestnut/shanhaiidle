<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryBossDropResource\Pages;
use App\Models\StoryBossDrop;
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

class StoryBossDropResource extends Resource
{
    protected static ?string $model = StoryBossDrop::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Boss掉落';
    protected static ?string $modelLabel = 'Boss掉落';
    protected static ?string $pluralModelLabel = 'Boss掉落';
    protected static ?string $navigationGroup = '世界观与主线';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('掉落基础信息')
                ->description('一条记录对应一个 Boss 的一类掉落。类型、物品类型都使用固定选项，避免手敲。')
                ->schema([
                    TextInput::make('boss_drop_id')->label('掉落 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('boss_id')->label('关联 Boss')->required()->options(fn (): array => AdminOptions::storyBossOptions())->searchable()->preload(),
                    Select::make('drop_type')->label('掉落类型')->required()->options(AdminOptions::storyBossDropTypeOptions()),
                    TextInput::make('item_id')->label('物品 ID')->required()->maxLength(120)->helperText('礼包道具或剧情资源可直接录入稳定 ID。'),
                    TextInput::make('item_name')->label('物品名称')->required()->maxLength(255),
                    Select::make('item_type')->label('物品类型')->required()->options(AdminOptions::storyDropItemTypeOptions()),
                    TextInput::make('count_min')->label('最少数量')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('count_max')->label('最多数量')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('probability')->label('概率')->numeric()->minValue(0)->maxValue(1)->step(0.0001)->required()->default(1),
                    Toggle::make('first_clear_only')->label('仅首通')->default(false),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(2),
            Section::make('说明与图标')
                ->description('用途说明供策划和运维快速判断资源价值。')
                ->schema([
                    Textarea::make('use_desc')->label('用途说明')->rows(3)->columnSpanFull(),
                    FileUpload::make('icon_path')->label('图标')->disk('public')->directory('config/story-boss-drops/icons')->image()->imagePreviewHeight('100'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('boss_drop_id')->label('掉落 ID')->searchable()->sortable(),
                TextColumn::make('boss_id')->label('Boss')->searchable()->sortable(),
                TextColumn::make('drop_type')->label('掉落类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::storyBossDropTypeOptions(), $state))->sortable(),
                TextColumn::make('item_name')->label('物品')->searchable(),
                TextColumn::make('item_type')->label('物品类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::storyDropItemTypeOptions(), $state))->sortable(),
                TextColumn::make('probability')->label('概率')->sortable(),
                ToggleColumn::make('first_clear_only')->label('首通'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('boss_id')->label('Boss')->options(fn (): array => AdminOptions::storyBossOptions()),
                Tables\Filters\SelectFilter::make('drop_type')->label('掉落类型')->options(AdminOptions::storyBossDropTypeOptions()),
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
            'index' => Pages\ListStoryBossDrops::route('/'),
            'create' => Pages\CreateStoryBossDrop::route('/create'),
            'edit' => Pages\EditStoryBossDrop::route('/{record}/edit'),
        ];
    }
}
