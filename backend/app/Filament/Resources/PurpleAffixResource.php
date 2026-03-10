<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurpleAffixResource\Pages;
use App\Models\PurpleAffix;
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

class PurpleAffixResource extends Resource
{
    protected static ?string $model = PurpleAffix::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '紫词条池';

    protected static ?string $modelLabel = '紫词条';

    protected static ?string $pluralModelLabel = '紫词条池';

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('affix_id')->label('词条ID')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('affix_name')->label('词条名')->required()->maxLength(128),
            TextInput::make('stat')->label('属性Key')->required()->maxLength(64),
            TagsInput::make('slot_tags')->label('部位标签'),
            TagsInput::make('flow_tags')->label('流派标签'),
            Select::make('rarity_tier')->label('词条稀有阶')->options([
                'purple' => '紫',
                'gold' => '金',
            ])->default('purple')->required(),
            TextInput::make('min_value')->label('最小值')->integer()->required()->default(0),
            TextInput::make('max_value')->label('最大值')->integer()->required()->default(0),
            TextInput::make('value_mode')->label('数值模式')->default('flat')->required()->maxLength(16),
            TextInput::make('weight')->label('权重')->integer()->minValue(0)->required()->default(1),
            TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(50),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affix_id')->label('词条ID')->searchable()->sortable(),
                TextColumn::make('affix_name')->label('名称')->searchable(),
                TextColumn::make('stat')->label('属性'),
                TextColumn::make('rarity_tier')->label('阶级'),
                TextColumn::make('min_value')->label('最小'),
                TextColumn::make('max_value')->label('最大'),
                TextColumn::make('weight')->label('权重'),
                TextColumn::make('unlock_level')->label('开放等级'),
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
            'index' => Pages\ListPurpleAffixes::route('/'),
            'create' => Pages\CreatePurpleAffix::route('/create'),
            'edit' => Pages\EditPurpleAffix::route('/{record}/edit'),
        ];
    }
}
