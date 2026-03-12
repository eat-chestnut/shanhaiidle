<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentSetSourceResource\Pages;
use App\Models\EquipmentSetSource;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipmentSetSourceResource extends Resource
{
    protected static ?string $model = EquipmentSetSource::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = '套装来源';
    protected static ?string $modelLabel = '套装来源';
    protected static ?string $pluralModelLabel = '套装来源';
    protected static string | \UnitEnum | null $navigationGroup = '世界观与主线';

    public static function normalizeFormData(array $data): array
    {
        $data['need_blueprint'] = (bool) ((int) ($data['need_blueprint'] ?? 0));
        $data['is_enabled'] = (bool) ((int) ($data['is_enabled'] ?? 1));
        $data['blueprint_source'] = $data['need_blueprint'] ? trim((string) ($data['blueprint_source'] ?? '')) : '';
        $data['source_maps'] = [];
        $data['source_bosses'] = [];
        $data['sub_materials'] = [];

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('这里只维护套装阶段概览、图纸需求和主材料标识。实际打造数量与真实成本统一在“打造配方”页维护。')
                ->schema([
                    TextInput::make('set_source_id')->label('来源 ID')->required()->maxLength(160)->unique(ignoreRecord: true),
                    Select::make('set_line_id')->label('套装线')->options(fn (): array => AdminOptions::setLineOptions())->searchable()->preload()->required(),
                    TextInput::make('set_name')->label('套装名称')->required()->maxLength(255),
                    Select::make('flow_tag')->label('流派')->options(AdminOptions::flowOptions())->searchable()->preload(),
                    Select::make('set_stage')->label('套装阶段')->options(AdminOptions::setStageOptions())->required(),
                    Radio::make('need_blueprint')
                        ->label('图纸需求')
                        ->options([1 => '需要图纸', 0 => '不需要图纸'])
                        ->default(0)
                        ->inline()
                        ->inlineLabel(false)
                        ->required(),
                    Select::make('main_mat_1')
                        ->label('主材料标识 1')
                        ->options(fn (): array => AdminOptions::itemOptions())
                        ->searchable()
                        ->preload()
                        ->helperText('这里只标记套装阶段的关键主材，具体需求数量由打造配方页维护。'),
                    Select::make('main_mat_2')
                        ->label('主材料标识 2')
                        ->options(fn (): array => AdminOptions::itemOptions())
                        ->searchable()
                        ->preload(),
                    Select::make('blueprint_source')
                        ->label('图纸物品')
                        ->options(fn (): array => AdminOptions::itemOptions(fn ($query) => $query->whereIn('type', ['blueprint', 'blueprint_fragment'])))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => (string) $get('need_blueprint') === '1')
                        ->helperText('如果该阶段需要图纸，这里只绑定图纸物品；图纸实际来源请在 Boss掉落 / 物品资料中维护。'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Radio::make('is_enabled')
                        ->label('启用')
                        ->options([1 => '启用', 0 => '停用'])
                        ->default(1)
                        ->inline()
                        ->inlineLabel(false)
                        ->required(),
                ])
                ->columns(3),
            Section::make('套装说明')
                ->description('来源地图、来源 Boss、材料出处不再在本页维护，统一回到物品和 Boss掉落页面。')
                ->schema([
                    Textarea::make('craft_desc')
                        ->label('套装说明')
                        ->rows(4)
                        ->placeholder('例如：60级青丘套以青丘主线与箕尾终章材料为核心，终章阶段建议优先完成主武器与项链。')
                        ->columnSpanFull(),
                ])
                ->columns(1),
            Section::make('说明与图片资源')
                ->schema([
                    FileUpload::make('icon_path')->label('套装图标')->disk('public')->directory('config/set-sources/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('image_path')->label('展示图')->disk('public')->directory('config/set-sources/images')->image()->imagePreviewHeight('100'),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('set_source_id')->label('来源 ID')->searchable()->sortable(),
                TextColumn::make('set_name')->label('套装名称')->searchable()->sortable(),
                TextColumn::make('set_stage')->label('阶段')->sortable(),
                TextColumn::make('flow_tag')->label('流派')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::flowOptions(), $state))->placeholder('—'),
                ToggleColumn::make('need_blueprint')->label('图纸'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('set_stage')->label('套装阶段')->options(AdminOptions::setStageOptions()),
                Tables\Filters\SelectFilter::make('flow_tag')->label('流派')->options(AdminOptions::flowOptions()),
                Tables\Filters\TernaryFilter::make('need_blueprint')->label('需要图纸'),
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
            'index' => Pages\ListEquipmentSetSources::route('/'),
            'create' => Pages\CreateEquipmentSetSource::route('/create'),
            'edit' => Pages\EditEquipmentSetSource::route('/{record}/edit'),
        ];
    }
}
