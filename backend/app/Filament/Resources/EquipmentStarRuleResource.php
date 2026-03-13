<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentStarRuleResource\Pages;
use App\Models\EquipmentStarRule;
use App\Support\AdminOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EquipmentStarRuleResource extends Resource
{
    protected static ?string $model = EquipmentStarRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = '套装升星规则';

    protected static ?string $modelLabel = '套装升星规则';

    protected static ?string $pluralModelLabel = '套装升星规则';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('升星上限规则')
                ->description('本轮只定义套装升星规则。20 / 40 / 50 / 60 档固定对应 3 / 6 / 8 / 10 星上限；首版为必成制，不做失败、掉星或保底。')
                ->schema([
                    Select::make('set_level')
                        ->label('套装档位')
                        ->options(EquipmentStarRule::SET_LEVEL_OPTIONS)
                        ->required()
                        ->unique(ignoreRecord: true, column: 'set_level')
                        ->live()
                        ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                            $set('max_star', EquipmentStarRule::MAX_STAR_BY_SET_LEVEL[(int) ($state ?? 0)] ?? null);
                        })
                        ->helperText('固定只支持 20 / 40 / 50 / 60 四档。'),
                    TextInput::make('max_star')
                        ->label('最高星级')
                        ->integer()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('20 / 40 / 50 / 60 分别固定对应 3 / 6 / 8 / 10 星。'),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label('排序')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Textarea::make('summary')
                        ->label('规则说明')
                        ->rows(3)
                        ->helperText('建议写清“该档最高可升到几星”。')
                        ->columnSpanFull(),
                    Textarea::make('remark')
                        ->label('备注')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('set_level')
                    ->label('套装档位')
                    ->formatStateUsing(fn (int|string|null $state): string => AdminOptions::optionLabel(EquipmentStarRule::SET_LEVEL_OPTIONS, $state === null ? null : (string) $state))
                    ->sortable(),
                TextColumn::make('max_star')->label('最高星级')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('summary')->label('规则说明')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('set_level')->label('套装档位')->options(EquipmentStarRule::SET_LEVEL_OPTIONS),
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
            'index' => Pages\ListEquipmentStarRules::route('/'),
            'create' => Pages\CreateEquipmentStarRule::route('/create'),
            'edit' => Pages\EditEquipmentStarRule::route('/{record}/edit'),
        ];
    }
}
