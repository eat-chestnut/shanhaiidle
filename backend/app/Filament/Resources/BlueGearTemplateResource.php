<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueGearTemplateResource\Pages;
use App\Models\BlueGearTemplate;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BlueGearTemplateResource extends Resource
{
    protected static ?string $model = BlueGearTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '蓝装模板';

    protected static ?string $modelLabel = '蓝装模板';

    protected static ?string $pluralModelLabel = '蓝装模板';

    protected static ?string $navigationGroup = '装备成长配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('template_id')->label('模板ID')->required()->maxLength(64)->unique(ignoreRecord: true),
            TextInput::make('name')->label('名称')->required()->maxLength(255),
            TextInput::make('blue_pool_id')->label('蓝装池ID')->maxLength(64),
            TextInput::make('slot_id')->label('部位')->required()->maxLength(64),
            TextInput::make('flow_tag')->label('流派标签')->maxLength(64),
            TextInput::make('required_level')->label('穿戴等级')->integer()->minValue(1)->required()->default(1),
            TextInput::make('affix_count')->label('随机蓝词条数量')->integer()->minValue(1)->required()->default(1),
            TagsInput::make('affix_pool_tags')->label('词条池标签'),
            KeyValue::make('white_stats')->label('白色主属性')->keyLabel('属性')->valueLabel('数值'),
            TextInput::make('icon')->label('图标')->maxLength(255),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template_id')->label('模板ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slot_id')->label('部位')->sortable(),
                TextColumn::make('required_level')->label('等级')->sortable(),
                TextColumn::make('affix_count')->label('词条数')->sortable(),
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
            'index' => Pages\ListBlueGearTemplates::route('/'),
            'create' => Pages\CreateBlueGearTemplate::route('/create'),
            'edit' => Pages\EditBlueGearTemplate::route('/{record}/edit'),
        ];
    }
}
