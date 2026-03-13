<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentStarSlotUnlockResource\Pages;
use App\Models\EquipmentStarSlotUnlock;
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

class EquipmentStarSlotUnlockResource extends Resource
{
    protected static ?string $model = EquipmentStarSlotUnlock::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = '套装宝石孔解锁';

    protected static ?string $modelLabel = '套装宝石孔解锁';

    protected static ?string $pluralModelLabel = '套装宝石孔解锁';

    protected static string | \UnitEnum | null $navigationGroup = '装备成长';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('孔位解锁规则')
                ->description('孔位解锁规则固定为 3 / 6 / 8 / 10 星解锁第 1 / 2 / 3 / 4 孔。前两个孔只能放属性宝石，后两个孔只能放技能宝石。')
                ->schema([
                    Select::make('required_star')
                        ->label('解锁所需星级')
                        ->options([
                            3 => '3星',
                            6 => '6星',
                            8 => '8星',
                            10 => '10星',
                        ])
                        ->required()
                        ->unique(ignoreRecord: true, column: 'required_star')
                        ->live()
                        ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                            $mapping = EquipmentStarSlotUnlock::STAR_SLOT_MAP[(int) ($state ?? 0)] ?? null;
                            $set('slot_index', $mapping['slot_index'] ?? null);
                            $set('slot_group', $mapping['slot_group'] ?? null);
                        })
                        ->helperText('固定只支持 3 / 6 / 8 / 10。'),
                    TextInput::make('slot_index')
                        ->label('孔位序号')
                        ->integer()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定映射为 1 / 2 / 3 / 4。'),
                    Select::make('slot_group')
                        ->label('孔位分组')
                        ->options(EquipmentStarSlotUnlock::SLOT_GROUP_OPTIONS)
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('前两个孔固定为 attr_only，后两个孔固定为 skill_only。'),
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
                        ->helperText('建议写清是属性孔还是技能孔。')
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
                TextColumn::make('required_star')->label('解锁星级')->sortable(),
                TextColumn::make('slot_index')->label('孔位序号')->sortable(),
                TextColumn::make('slot_group')
                    ->label('孔位分组')
                    ->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(EquipmentStarSlotUnlock::SLOT_GROUP_OPTIONS, $state))
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('required_star')->label('解锁星级')->options([
                    3 => '3星',
                    6 => '6星',
                    8 => '8星',
                    10 => '10星',
                ]),
                Tables\Filters\SelectFilter::make('slot_group')->label('孔位分组')->options(EquipmentStarSlotUnlock::SLOT_GROUP_OPTIONS),
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
            'index' => Pages\ListEquipmentStarSlotUnlocks::route('/'),
            'create' => Pages\CreateEquipmentStarSlotUnlock::route('/create'),
            'edit' => Pages\EditEquipmentStarSlotUnlock::route('/{record}/edit'),
        ];
    }
}
