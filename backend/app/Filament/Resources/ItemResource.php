<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '物品主表';

    protected static ?string $pluralModelLabel = '物品主表';

    protected static ?string $modelLabel = '物品';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基础信息')
                ->description('维护全局统一物品锚点。item_id 会被怪物掉落、首通奖励、礼包与商城等模块引用。')
                ->schema([
                    TextInput::make('item_id')
                        ->label('物品 ID')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true, column: 'item_id')
                        ->disabled(fn (?Item $record): bool => $record !== null),
                    TextInput::make('item_name')->label('内部名称')->required()->maxLength(255),
                    TextInput::make('display_name')->label('展示名称')->required()->maxLength(255),
                    Select::make('main_type')
                        ->label('主类型')
                        ->options(AdminOptions::itemMainTypeOptions())
                        ->required()
                        ->live(),
                    Select::make('sub_type')
                        ->label('子类型')
                        ->options(fn (Get $get): array => AdminOptions::itemSubTypeOptionsByMainType((string) $get('main_type')))
                        ->searchable()
                        ->required(),
                    Select::make('quality')
                        ->label('玩法品质')
                        ->options(AdminOptions::qualityOptions())
                        ->required()
                        ->default('white'),
                    Select::make('rarity')
                        ->label('展示稀有度')
                        ->options(AdminOptions::rarityOptions())
                        ->required()
                        ->default('white'),
                    FileUpload::make('icon')
                        ->label('图标')
                        ->disk('public')
                        ->directory('config/item-icons')
                        ->image()
                        ->imagePreviewHeight('120'),
                    Textarea::make('desc')
                        ->label('描述')
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->required(),
                    Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                ])
                ->columns(4),
            Section::make('功能属性')
                ->description('用于统一背包堆叠、绑定、使用和等级限制等基础规则。')
                ->schema([
                    Toggle::make('is_stackable')->label('可堆叠')->default(true),
                    TextInput::make('max_stack')->label('最大堆叠')->integer()->minValue(1)->default(9999)->required(),
                    TextInput::make('required_level')->label('需求等级')->integer()->minValue(1)->default(1)->required(),
                    Select::make('bind_type')->label('绑定规则')->options(AdminOptions::itemBindTypeOptions())->required()->default('none'),
                    TextInput::make('sell_price')->label('出售价格')->integer()->minValue(0)->default(0)->required(),
                    Select::make('use_type')->label('使用类型')->options(AdminOptions::itemUseTypeOptions())->required()->default('none'),
                ])
                ->columns(3),
            Section::make('扩展展示')
                ->description('source_library 用于标记来源目录；rarity_frame_key 用于覆盖默认稀有度边框。')
                ->schema([
                    Select::make('source_library')
                        ->label('来源库')
                        ->options(AdminOptions::itemSourceLibraryOptions())
                        ->searchable()
                        ->required()
                        ->default('core_catalog'),
                    TextInput::make('rarity_frame_key')->label('稀有度边框覆盖')->maxLength(255),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('main_type')->label('主类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::itemMainTypeOptions(), $state))->sortable(),
                TextColumn::make('sub_type')->label('子类型')->formatStateUsing(fn (?string $state, Item $record): string => AdminOptions::itemSubTypeLabelByMainType((string) $record->main_type, $state))->toggleable(),
                TextColumn::make('quality')->label('玩法品质')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::qualityOptions(), $state))->badge()->sortable(),
                TextColumn::make('rarity')->label('展示稀有度')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state))->badge()->sortable(),
                IconColumn::make('is_stackable')->label('可堆叠')->boolean(),
                TextColumn::make('required_level')->label('需求等级')->sortable(),
                TextColumn::make('source_library')->label('来源库')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::itemSourceLibraryOptions(), $state))->toggleable(),
                IconColumn::make('is_enabled')->label('启用')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('main_type')->label('主类型')->options(AdminOptions::itemMainTypeOptions()),
                Tables\Filters\SelectFilter::make('sub_type')->label('子类型')->options(AdminOptions::allItemSubTypeOptions()),
                Tables\Filters\SelectFilter::make('quality')->label('玩法品质')->options(AdminOptions::qualityOptions()),
                Tables\Filters\SelectFilter::make('rarity')->label('展示稀有度')->options(AdminOptions::rarityOptions()),
                Tables\Filters\SelectFilter::make('source_library')->label('来源库')->options(AdminOptions::itemSourceLibraryOptions()),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
