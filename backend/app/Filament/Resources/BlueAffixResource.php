<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueAffixResource\Pages;
use App\Models\BlueAffix;
use App\Support\AdminOptions;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
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

class BlueAffixResource extends Resource
{
    protected static ?string $model = BlueAffix::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = '蓝词条池';

    protected static ?string $modelLabel = '蓝色词条';

    protected static ?string $pluralModelLabel = '蓝色词条池';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('蓝色词条只定义词条本体，不再在这里维护流派限制和抽取权重。抽取倾向统一在蓝装模板侧配置。')
                ->schema([
                    TextInput::make('affix_id')->label('词条 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->helperText('建议使用稳定英文 ID。'),
                    TextInput::make('affix_name')->label('词条名称')->required()->maxLength(128),
                    Select::make('stat')->label('属性')->required()->options(AdminOptions::statOptions())->searchable()->helperText('显示中文名，同时保留内部 Key。'),
                    Select::make('value_mode')->label('数值模式')->required()->options(AdminOptions::valueModeOptions())->default('flat'),
                ])
                ->columns(4),
            Section::make('适用范围')
                ->description('允许部位会作为蓝装模板配置词条时的过滤条件。')
                ->schema([
                    Select::make('slot_tags')->label('部位')->multiple()->options(AdminOptions::slotOptions())->searchable()->preload()->helperText('选择该词条可生效的装备部位。'),
                ])
                ->columns(1),
            Section::make('数值与启用')
                ->description('蓝词条是通用词条池，开放等级用于控制系统开放时点。')
                ->schema([
                    TextInput::make('min_value')->label('最小值')->integer()->required()->default(0)->minValue(0),
                    TextInput::make('max_value')->label('最大值')->integer()->required()->default(0)->minValue(0),
                    TextInput::make('unlock_level')->label('开放等级')->integer()->minValue(1)->required()->default(1)->helperText('按 1 / 5 / 10 / 15 / 20 级档配置'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Textarea::make('notes')->label('备注')->rows(3)->columnSpanFull(),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(5),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affix_id')->label('词条 ID')->searchable()->sortable(),
                TextColumn::make('affix_name')->label('名称')->searchable()->sortable(),
                TextColumn::make('stat')->label('属性')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::statOptions(), $state))->sortable(),
                TextColumn::make('slot_tags')
                    ->label('允许部位')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::slotOptions()[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('value_mode')->label('数值模式')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::valueModeOptions(), $state)),
                TextColumn::make('unlock_level')->label('开放等级')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stat')->label('属性')->options(AdminOptions::statOptions()),
                Tables\Filters\SelectFilter::make('value_mode')->label('数值模式')->options(AdminOptions::valueModeOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete'),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlueAffixes::route('/'),
            'create' => Pages\CreateBlueAffix::route('/create'),
            'edit' => Pages\EditBlueAffix::route('/{record}/edit'),
        ];
    }
}
