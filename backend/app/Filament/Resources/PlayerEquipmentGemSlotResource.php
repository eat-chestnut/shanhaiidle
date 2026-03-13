<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerEquipmentGemSlotResource\Pages;
use App\Models\PlayerEquipmentGemSlot;
use App\Support\AdminOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlayerEquipmentGemSlotResource extends Resource
{
    protected static ?string $model = PlayerEquipmentGemSlot::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '玩家宝石孔实例';

    protected static ?string $modelLabel = '玩家宝石孔实例';

    protected static ?string $pluralModelLabel = '玩家宝石孔实例';

    protected static string | \UnitEnum | null $navigationGroup = '玩家实例';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('宝石孔实例')
                ->description('孔位映射固定：1->3星->attr_only，2->6星->attr_only，3->8星->skill_only，4->10星->skill_only。未解锁孔位不能镶嵌宝石。attr_only 只能放属性宝石，skill_only 只能放技能宝石。')
                ->schema([
                    TextInput::make('instance_id')
                        ->label('实例 ID')
                        ->maxLength(64)
                        ->required()
                        ->helperText('必须关联 player_equipment_instances.instance_id。'),
                    Select::make('slot_index')
                        ->label('孔位序号')
                        ->options([
                            1 => '第1孔',
                            2 => '第2孔',
                            3 => '第3孔',
                            4 => '第4孔',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, int|string|null $state): void {
                            $mapping = PlayerEquipmentGemSlot::SLOT_RULE_MAP[(int) ($state ?? 0)] ?? null;
                            $set('required_star', $mapping['required_star'] ?? null);
                            $set('slot_group', $mapping['slot_group'] ?? null);
                        })
                        ->helperText('固定只支持 1 / 2 / 3 / 4。'),
                    TextInput::make('required_star')
                        ->label('解锁星级')
                        ->integer()
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定映射 3 / 6 / 8 / 10。'),
                    Select::make('slot_group')
                        ->label('孔位分组')
                        ->options(PlayerEquipmentGemSlot::SLOT_GROUP_OPTIONS)
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('固定映射 attr_only / skill_only。'),
                    Toggle::make('is_unlocked')
                        ->label('是否解锁')
                        ->default(false),
                    Select::make('gem_item_id')
                        ->label('宝石物品 ID')
                        ->options(AdminOptions::itemOptions(fn (Builder $query): Builder => $query->where('main_type', 'gem')))
                        ->searchable()
                        ->preload()
                        ->helperText('未解锁时必须为空；attr_only 只能选 attr_gem，skill_only 只能选 skill_gem。'),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instance_id')->label('实例 ID')->searchable()->sortable(),
                TextColumn::make('slot_index')->label('孔位序号')->sortable(),
                TextColumn::make('slot_group')->label('孔位分组')->badge()->sortable(),
                TextColumn::make('required_star')->label('解锁星级')->sortable(),
                ToggleColumn::make('is_unlocked')->label('是否解锁')->sortable(),
                TextColumn::make('gem_item_id')->label('宝石物品 ID')->searchable()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot_index')->label('孔位序号')->options([
                    1 => '第1孔',
                    2 => '第2孔',
                    3 => '第3孔',
                    4 => '第4孔',
                ]),
                Tables\Filters\SelectFilter::make('slot_group')->label('孔位分组')->options(PlayerEquipmentGemSlot::SLOT_GROUP_OPTIONS),
                Tables\Filters\TernaryFilter::make('is_unlocked')->label('是否解锁'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerEquipmentGemSlots::route('/'),
            'create' => Pages\CreatePlayerEquipmentGemSlot::route('/create'),
            'edit' => Pages\EditPlayerEquipmentGemSlot::route('/{record}/edit'),
        ];
    }
}
