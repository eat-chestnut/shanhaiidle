<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurpleAffixResource\Pages;
use App\Models\PurpleAffix;
use App\Support\AdminOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PurpleAffixResource extends Resource
{
    protected static ?string $model = PurpleAffix::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '紫词条池';

    protected static ?string $modelLabel = '紫色词条';

    protected static ?string $pluralModelLabel = '紫色词条池';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('紫色词条用于洗练，录入规则与蓝词条保持一致。')
                ->schema([
                    TextInput::make('affix_id')->label('词条 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('affix_name')->label('词条名称')->required()->maxLength(128),
                    Select::make('stat')->label('属性')->required()->options(AdminOptions::statOptions())->searchable(),
                    Select::make('rarity_tier')->label('词条稀有阶')->options([
                        'purple' => '紫色',
                        'gold' => '金色',
                    ])->default('purple')->required(),
                ])
                ->columns(4),
            Section::make('适用范围')
                ->schema([
                    Select::make('slot_tags')->multiple()->label('部位')->options(AdminOptions::slotOptions())->searchable()->preload(),
                ])
                ->columns(1),
            Section::make('数值与启用')
                ->schema([
                    TextInput::make('min_value')->label('最小值')->integer()->required()->default(0)->minValue(0),
                    TextInput::make('max_value')->label('最大值')->integer()->required()->default(0)->minValue(0),
                    Select::make('value_mode')->label('数值模式')->options(AdminOptions::valueModeOptions())->default('flat')->required(),
                    TextInput::make('weight')->label('权重')->integer()->minValue(0)->required()->default(1),
                    TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(50),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affix_id')->label('词条 ID')->searchable()->sortable(),
                TextColumn::make('affix_name')->label('名称')->searchable(),
                TextColumn::make('stat')->label('属性')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::statOptions(), $state)),
                TextColumn::make('rarity_tier')->label('稀有阶')->formatStateUsing(fn (?string $state): string => $state === 'gold' ? '金色' : '紫色'),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                TextColumn::make('weight')->label('权重')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rarity_tier')->label('稀有阶')->options(['purple' => '紫色', 'gold' => '金色']),
                Tables\Filters\SelectFilter::make('stat')->label('属性')->options(AdminOptions::statOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
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
            'index' => Pages\ListPurpleAffixes::route('/'),
            'create' => Pages\CreatePurpleAffix::route('/create'),
            'edit' => Pages\EditPurpleAffix::route('/{record}/edit'),
        ];
    }
}
