<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiftPackResource\Pages;
use App\Models\GiftPack;
use App\Models\GiftPackItem;
use App\Support\AdminOptions;
use App\Support\GiftPackModuleSupport;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GiftPackResource extends Resource
{
    protected static ?string $model = GiftPack::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = '礼包配置';

    protected static ?string $modelLabel = '礼包';

    protected static ?string $pluralModelLabel = '礼包配置';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('gift_pack_tabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('礼包基础')
                                    ->description('礼包本体统一由 items.item_id 承载，礼包内容统一通过 item_id 引用。')
                                    ->schema([
                                        TextInput::make('pack_id')
                                            ->label('礼包 ID')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(ignoreRecord: true)
                                            ->disabled(fn (?GiftPack $record): bool => $record !== null),
                                        Select::make('item_id')
                                            ->label('礼包物品')
                                            ->options(fn (): array => AdminOptions::giftPackCarrierItemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->unique(ignoreRecord: true, column: 'item_id')
                                            ->disabled(fn (?GiftPack $record): bool => $record !== null)
                                            ->helperText('只允许选择 `main_type = gift_pack` 的 item。'),
                                        TextInput::make('pack_name')->label('礼包内部名称')->required()->maxLength(255),
                                        TextInput::make('display_name')->label('礼包展示名称')->required()->maxLength(255),
                                        Select::make('pack_type')->label('礼包类型')->options(AdminOptions::giftPackTypeOptions())->required(),
                                        Select::make('pack_mode')->label('礼包形式')->options(AdminOptions::giftPackModeOptions())->required()->live(),
                                        Select::make('open_mode')->label('开启方式')->options(AdminOptions::giftPackOpenModeOptions())->required(),
                                        TextInput::make('select_count_min')
                                            ->label('最少选择数')
                                            ->integer()
                                            ->minValue(0)
                                            ->nullable()
                                            ->helperText('fixed 可留空；select_one 会自动归一为 1。'),
                                        TextInput::make('select_count_max')
                                            ->label('最多选择数')
                                            ->integer()
                                            ->minValue(0)
                                            ->nullable()
                                            ->helperText('fixed 可留空；select_one 会自动归一为 1。'),
                                        Textarea::make('desc')->label('描述')->rows(3)->columnSpanFull(),
                                        TextInput::make('icon')->label('图标')->maxLength(255),
                                        Toggle::make('is_enabled')->label('启用')->default(true),
                                        TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->required(),
                                        Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('固定内容')
                            ->schema([
                                Section::make('固定内容')
                                    ->description('仅维护 `content_mode = fixed` 的礼包条目。')
                                    ->schema([
                                        static::packItemsRepeater('fixed_items', 'fixed'),
                                    ]),
                            ]),
                        Tab::make('自选内容')
                            ->schema([
                                Section::make('自选内容')
                                    ->description('仅维护 `content_mode = selectable` 的礼包条目。推荐宗门仅做展示，不做强制限制。')
                                    ->schema([
                                        static::packItemsRepeater('selectable_items', 'selectable'),
                                    ]),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pack_id')->label('礼包 ID')->searchable()->sortable(),
                TextColumn::make('item_id')->label('礼包物品')->searchable()->sortable(),
                TextColumn::make('display_name')->label('展示名称')->searchable()->sortable(),
                TextColumn::make('pack_type')->label('礼包类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::giftPackTypeOptions(), $state))->badge()->sortable(),
                TextColumn::make('pack_mode')->label('礼包形式')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::giftPackModeOptions(), $state))->badge()->sortable(),
                TextColumn::make('open_mode')->label('开启方式')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::giftPackOpenModeOptions(), $state))->sortable(),
                IconColumn::make('is_enabled')->label('启用')->boolean()->sortable(),
                TextColumn::make('content_items_count')->label('内容条目数')->counts('contentItems'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pack_type')->label('礼包类型')->options(AdminOptions::giftPackTypeOptions()),
                Tables\Filters\SelectFilter::make('pack_mode')->label('礼包形式')->options(AdminOptions::giftPackModeOptions()),
                Tables\Filters\SelectFilter::make('open_mode')->label('开启方式')->options(AdminOptions::giftPackOpenModeOptions()),
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
            'index' => Pages\ListGiftPacks::route('/'),
            'create' => Pages\CreateGiftPack::route('/create'),
            'edit' => Pages\EditGiftPack::route('/{record}/edit'),
        ];
    }

    public static function fixedItemsForForm(GiftPack $record): array
    {
        return static::contentItemsForForm($record, 'fixed');
    }

    public static function selectableItemsForForm(GiftPack $record): array
    {
        return static::contentItemsForForm($record, 'selectable');
    }

    public static function normalizePackItemsOrFail(mixed $raw, string $contentMode): array
    {
        return GiftPackModuleSupport::normalizePackItems($raw, $contentMode);
    }

    /**
     * @return array{pack: array<string, mixed>, fixed_items: array<int, array<string, mixed>>, selectable_items: array<int, array<string, mixed>>}
     */
    public static function normalizeFormDataOrFail(array $data): array
    {
        $fixedItems = static::normalizePackItemsOrFail($data['fixed_items'] ?? [], 'fixed');
        $selectableItems = static::normalizePackItemsOrFail($data['selectable_items'] ?? [], 'selectable');
        $pack = GiftPackModuleSupport::normalizePack($data);

        GiftPackModuleSupport::validatePackOrFail($pack, $fixedItems, $selectableItems);

        return [
            'pack' => $pack,
            'fixed_items' => $fixedItems,
            'selectable_items' => $selectableItems,
        ];
    }

    public static function syncRelations(GiftPack $record, array $fixedItems, array $selectableItems): void
    {
        $record->contentItems()->delete();

        foreach (array_merge($fixedItems, $selectableItems) as $row) {
            $record->contentItems()->create($row);
        }
    }

    private static function contentItemsForForm(GiftPack $record, string $contentMode): array
    {
        return $record->contentItems()
            ->where('content_mode', $contentMode)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (GiftPackItem $item): array => [
                'item_id' => (string) $item->item_id,
                'count_min' => (int) $item->count_min,
                'count_max' => (int) $item->count_max,
                'weight' => (int) $item->weight,
                'recommended_sect' => (string) $item->recommended_sect,
                'display_note' => $item->display_note !== null ? (string) $item->display_note : null,
                'sort_order' => (int) $item->sort_order,
                'is_enabled' => (bool) $item->is_enabled,
                'remark' => $item->remark !== null ? (string) $item->remark : null,
            ])->all();
    }

    private static function packItemsRepeater(string $name, string $contentMode): Repeater
    {
        $isSelectable = $contentMode === 'selectable';

        return Repeater::make($name)
            ->label($isSelectable ? '自选条目' : '固定条目')
            ->default([])
            ->reorderable(false)
            ->reorderableWithButtons(false)
            ->reorderableWithDragAndDrop(false)
            ->itemLabel(fn (array $state): ?string => filled($state['item_id'] ?? null) ? AdminOptions::itemName((string) $state['item_id']) : null)
            ->addActionLabel($isSelectable ? '新增自选条目' : '新增固定条目')
            ->schema([
                Select::make('item_id')
                    ->label('物品')
                    ->options(fn (): array => AdminOptions::giftPackContentItemOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(4),
                TextInput::make('count_min')->label('最小数量')->integer()->minValue(1)->default(1)->required()->columnSpan(2),
                TextInput::make('count_max')->label('最大数量')->integer()->minValue(1)->default(1)->required()->columnSpan(2),
                Select::make('recommended_sect')
                    ->label('推荐宗门')
                    ->options(AdminOptions::giftPackRecommendedSectOptions())
                    ->default('none')
                    ->visible($isSelectable)
                    ->columnSpan(2),
                TextInput::make('display_note')
                    ->label('展示说明')
                    ->maxLength(255)
                    ->visible($isSelectable)
                    ->columnSpan(2),
                TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->columnSpan(2),
                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(2),
                Textarea::make('remark')->label('备注')->rows(2)->columnSpanFull(),
            ])
            ->columns(12)
            ->columnSpanFull();
    }
}
