<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '物品库';

    protected static ?string $pluralModelLabel = '物品';

    protected static ?string $modelLabel = '物品';

    protected static ?string $navigationGroup = '基础配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('ItemTabs')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('基础信息')
                        ->schema([
                            Section::make('基础字段')
                                ->schema([
                                    TextInput::make('id')->label('物品 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?Item $record): bool => $record !== null),
                                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                                    Select::make('type')->label('类型')->options(AdminOptions::itemTypeOptions())->required()->default('material'),
                                    Select::make('material_type')->label('材料大类')->options(AdminOptions::materialTypeOptions())->searchable()->nullable()->helperText('仅材料类物品需要填写。'),
                                    TextInput::make('sub_type')->label('子类型')->maxLength(64)->helperText('如 forge_base / equipment_blueprint / skill_gem。'),
                                    Select::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions())->required()->default('white'),
                                    FileUpload::make('icon')->label('图标')->disk('public')->directory('config/item-icons')->image()->imagePreviewHeight('120'),
                                    TextInput::make('stack_limit')->label('堆叠上限')->integer()->minValue(1)->default(9999)->required(),
                                    TextInput::make('drop_unlock_level')->label('掉落开放等级')->integer()->minValue(1)->default(1)->required(),
                                    MultiSelect::make('source_tags')->label('来源标签')->options(AdminOptions::materialTypeOptions())->searchable()->preload()->helperText('用于运维快速标记来源大类。'),
                                    MultiSelect::make('use_tags')->label('用途标签')->options(AdminOptions::materialTypeOptions())->searchable()->preload(),
                                    Textarea::make('desc')->label('描述')->rows(3)->columnSpanFull(),
                                    Textarea::make('trait')->label('特性备注')->rows(2)->columnSpanFull(),
                                ])
                                ->columns(3),
                        ]),
                    Tab::make('效果与规则')
                        ->schema([
                            Section::make('宝石/效果字段')
                                ->description('仅宝石或特殊道具使用，下方字段没有需求时可留空。')
                                ->schema([
                                    Select::make('gem_effect.stat')->label('宝石属性')->options(AdminOptions::statOptions())->required(fn (Get $get): bool => $get('type') === 'gem')->hidden(fn (Get $get): bool => $get('type') !== 'gem')->dehydrated(fn (Get $get): bool => $get('type') === 'gem')->searchable(),
                                    TextInput::make('gem_effect.val')->label('宝石数值')->integer()->required(fn (Get $get): bool => $get('type') === 'gem')->hidden(fn (Get $get): bool => $get('type') !== 'gem')->dehydrated(fn (Get $get): bool => $get('type') === 'gem'),
                                    Select::make('effect_type')->label('效果类型')->options(AdminOptions::gemEffectTypeOptions())->nullable(),
                                    Select::make('target_scope')->label('目标范围')->options(AdminOptions::gemTargetScopeOptions())->nullable(),
                                    KeyValue::make('effect_payload')->label('效果负载')->keyLabel('键')->valueLabel('值')->columnSpanFull(),
                                    MultiSelect::make('socket_limit')->label('孔位限制')->options([
                                        '1' => '第1孔',
                                        '2' => '第2孔',
                                        '3' => '第3孔',
                                        '4' => '第4孔',
                                        'attr' => '属性孔',
                                        'skill' => '技能孔',
                                    ])->searchable()->preload()->columnSpanFull(),
                                    Toggle::make('can_compose')->label('可合成')->default(false),
                                    Toggle::make('can_reforge')->label('可洗炼')->default(false),
                                ])
                                ->columns(3),
                        ]),
                    Tab::make('状态')
                        ->schema([
                            Section::make('启用与排序')
                                ->schema([
                                    Toggle::make('is_enabled')->label('启用')->default(true),
                                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                                ])
                                ->columns(2),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::itemTypeOptions(), $state))->sortable(),
                TextColumn::make('material_type')->label('材料大类')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::materialTypeOptions(), $state))->toggleable(),
                TextColumn::make('sub_type')->label('子类型')->toggleable(),
                TextColumn::make('rarity')->label('稀有度')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state))->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('类型')->options(AdminOptions::itemTypeOptions()),
                Tables\Filters\SelectFilter::make('material_type')->label('材料大类')->options(AdminOptions::materialTypeOptions()),
                Tables\Filters\SelectFilter::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
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
