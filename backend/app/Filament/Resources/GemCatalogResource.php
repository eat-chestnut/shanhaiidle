<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GemCatalogResource\Pages;
use App\Models\Item;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GemCatalogResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '宝石目录';

    protected static ?string $modelLabel = '宝石';

    protected static ?string $pluralModelLabel = '宝石目录';

    protected static ?string $navigationGroup = '基础配置';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'gem');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('type')->default('gem'),
            Section::make('基础信息')
                ->schema([
                    TextInput::make('id')->label('宝石 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                    Select::make('sub_type')->label('宝石大类')->options(AdminOptions::gemTypeOptions())->required()->default('attr'),
                    Select::make('rarity')->label('稀有度')->required()->options(AdminOptions::rarityOptions())->default('white'),
                    Select::make('effect_type')->label('效果类型')->required()->options(AdminOptions::gemEffectTypeOptions())->default('stat'),
                    Select::make('target_scope')->label('目标范围')->options(AdminOptions::gemTargetScopeOptions())->default('global')->helperText('技能宝石可选指定技能范围。'),
                    TextInput::make('drop_unlock_level')->label('掉落开放等级')->integer()->minValue(1)->required()->default(1),
                ])
                ->columns(3),
            Section::make('效果配置')
                ->description('属性宝石和技能宝石都通过效果负载来描述具体加成。')
                ->schema([
                    KeyValue::make('effect_payload')->label('效果配置')->keyLabel('键')->valueLabel('值')->columnSpanFull(),
                    MultiSelect::make('socket_limit')->label('可镶嵌孔位')->options([
                        '1' => '第1孔',
                        '2' => '第2孔',
                        '3' => '第3孔',
                        '4' => '第4孔',
                        'attr' => '属性孔',
                        'skill' => '技能孔',
                    ])->searchable()->preload()->helperText('第1/2孔通常为属性孔，第3/4孔通常为技能孔。')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('图片与状态')
                ->schema([
                    FileUpload::make('icon')->label('图标')->disk('public')->directory('config/gem-icons')->image()->imagePreviewHeight('120'),
                    Toggle::make('can_compose')->label('可合成')->default(true),
                    Toggle::make('can_reforge')->label('可洗炼')->default(false),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('宝石 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('sub_type')->label('大类')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::gemTypeOptions(), $state)),
                TextColumn::make('rarity')->label('稀有度')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state)),
                TextColumn::make('effect_type')->label('效果类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::gemEffectTypeOptions(), $state)),
                TextColumn::make('drop_unlock_level')->label('掉落等级'),
                ToggleColumn::make('can_compose')->label('合成'),
                ToggleColumn::make('can_reforge')->label('洗炼'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sub_type')->label('宝石大类')->options(AdminOptions::gemTypeOptions()),
                Tables\Filters\SelectFilter::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
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
            'index' => Pages\ListGemCatalogs::route('/'),
            'create' => Pages\CreateGemCatalog::route('/create'),
            'edit' => Pages\EditGemCatalog::route('/{record}/edit'),
        ];
    }
}
