<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GemCatalogResource\Pages;
use App\Models\Item;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    protected static ?string $navigationGroup = '装备成长配置';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'gem');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('type')->default('gem'),
            TextInput::make('id')->label('宝石ID')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('name')->label('名称')->required()->maxLength(255),
            Select::make('sub_type')->label('宝石大类')->options([
                'attr' => '属性宝石',
                'skill' => '技能宝石',
            ])->required()->default('attr'),
            Select::make('rarity')->label('稀有度')->required()->options([
                'white' => '白',
                'blue' => '蓝',
                'purple' => '紫',
                'gold' => '金',
            ])->default('white'),
            Select::make('effect_type')->label('效果类型')->required()->options([
                'stat' => '属性',
                'skill_modifier' => '技能修饰',
            ])->default('stat'),
            TextInput::make('target_scope')->label('目标范围')->maxLength(64)->default('global'),
            KeyValue::make('effect_payload')->label('效果Payload')->keyLabel('键')->valueLabel('值'),
            TagsInput::make('socket_limit')->label('孔位限制')->placeholder('1/2/3/4 或 attr/skill'),
            TextInput::make('drop_unlock_level')->label('掉落开放等级')->integer()->minValue(1)->required()->default(1),
            TextInput::make('icon')->label('图标')->maxLength(255),
            Toggle::make('can_compose')->label('可合成')->default(true),
            Toggle::make('can_reforge')->label('可洗炼')->default(false),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('宝石ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('sub_type')->label('大类')->formatStateUsing(fn (?string $state): string => $state === 'skill' ? '技能宝石' : '属性宝石'),
                TextColumn::make('rarity')->label('稀有度'),
                TextColumn::make('effect_type')->label('效果类型'),
                TextColumn::make('drop_unlock_level')->label('掉落等级'),
                ToggleColumn::make('can_compose')->label('合成'),
                ToggleColumn::make('can_reforge')->label('洗炼'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
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
