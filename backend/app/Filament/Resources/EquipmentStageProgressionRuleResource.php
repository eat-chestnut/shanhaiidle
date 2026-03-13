<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentStageProgressionRuleResource\Pages;
use App\Models\EquipmentStageProgressionRule;
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

class EquipmentStageProgressionRuleResource extends Resource
{
    protected static ?string $model = EquipmentStageProgressionRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationLabel = '套装档位进阶规则';

    protected static ?string $modelLabel = '套装档位进阶规则';

    protected static ?string $pluralModelLabel = '套装档位进阶规则';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('档位进阶规则')
                ->description('满当前档最高星才能进阶下一档，且进阶后保留当前星级。本轮只定义规则，不实现跨档打造或实例迁移逻辑。')
                ->schema([
                    Select::make('from_set_level')
                        ->label('当前档位')
                        ->options([
                            20 => '20级',
                            40 => '40级',
                            50 => '50级',
                        ])
                        ->required()
                        ->unique(ignoreRecord: true, column: 'from_set_level')
                        ->live()
                        ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                            $mapping = EquipmentStageProgressionRule::PROGRESSION_MAP[(int) ($state ?? 0)] ?? null;
                            $set('to_set_level', $mapping['to_set_level'] ?? null);
                            $set('required_max_star', $mapping['required_max_star'] ?? null);
                            $set('star_keep_mode', 'keep_current_star');
                        })
                        ->helperText('只允许 20 -> 40、40 -> 50、50 -> 60。'),
                    TextInput::make('to_set_level')
                        ->label('目标档位')
                        ->integer()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定由当前档位推导，不允许自定义额外进阶。'),
                    TextInput::make('required_max_star')
                        ->label('进阶所需满星')
                        ->integer()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('必须达到当前档最高星后才能进阶。'),
                    Select::make('star_keep_mode')
                        ->label('进阶后星级保留方式')
                        ->options(EquipmentStageProgressionRule::STAR_KEEP_MODE_OPTIONS)
                        ->required()
                        ->default('keep_current_star')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定为 keep_current_star，表示进阶后保留当前星级。'),
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
                        ->helperText('建议明确写出“满星才能进阶，进阶后保留当前星级”。')
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
                TextColumn::make('from_set_level')->label('当前档位')->sortable(),
                TextColumn::make('to_set_level')->label('目标档位')->sortable(),
                TextColumn::make('required_max_star')->label('所需满星')->sortable(),
                TextColumn::make('star_keep_mode')
                    ->label('保留方式')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(EquipmentStageProgressionRule::STAR_KEEP_MODE_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_set_level')->label('当前档位')->options([
                    20 => '20级',
                    40 => '40级',
                    50 => '50级',
                ]),
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
            'index' => Pages\ListEquipmentStageProgressionRules::route('/'),
            'create' => Pages\CreateEquipmentStageProgressionRule::route('/create'),
            'edit' => Pages\EditEquipmentStageProgressionRule::route('/{record}/edit'),
        ];
    }
}
