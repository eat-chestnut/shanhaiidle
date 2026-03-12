<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Support\AdminOptions;
use App\Support\GemEffectRegistry;
use App\Support\ItemEffectRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '物品库';

    protected static ?string $pluralModelLabel = '物品';

    protected static ?string $modelLabel = '物品';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('ItemTabs')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('基础信息')
                        ->schema([
                            Section::make('基础字段')
                                ->schema([
                                    TextInput::make('id')->label('物品 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?Item $record): bool => $record !== null),
                                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                                    Select::make('type')
                                        ->label('类型')
                                        ->options(AdminOptions::itemTypeOptions())
                                        ->required()
                                        ->default('material')
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                            static::syncClassificationFields($set, $get, $state);
                                        }),
                                    Select::make('material_type')
                                        ->label('材料分类')
                                        ->options(fn (Get $get): array => AdminOptions::itemMaterialTypeOptions((string) $get('type')))
                                        ->default('craft')
                                        ->searchable()
                                        ->visible(fn (Get $get): bool => $get('type') === 'material')
                                        ->required(fn (Get $get): bool => $get('type') === 'material')
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                            static::syncClassificationFields($set, $get, (string) $get('type'), $state);
                                        }),
                                    Select::make('sub_type')
                                        ->label('业务分类')
                                        ->options(fn (Get $get): array => AdminOptions::itemSubTypeOptions((string) $get('type'), (string) $get('material_type')))
                                        ->default('forge_base')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                            static::syncEffectDrivenFields($set, $get, (string) $get('type'), $state);
                                        }),
                                    Select::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions())->required()->default('white'),
                                    FileUpload::make('icon')->label('图标')->disk('public')->directory('config/item-icons')->image()->imagePreviewHeight('120'),
                                    TextInput::make('stack_limit')->label('堆叠上限')->integer()->minValue(1)->default(9999)->required(),
                                    TextInput::make('drop_unlock_level')->label('掉落开放等级')->integer()->minValue(1)->default(1)->required(),
                                    Select::make('source_tags')->label('来源标签')->multiple()->options(AdminOptions::itemSourceTagOptions())->searchable()->preload(),
                                    Select::make('use_tags')->label('用途标签')->multiple()->options(AdminOptions::itemUseTagOptions())->searchable()->preload(),
                                    Textarea::make('desc')->label('描述')->rows(3)->columnSpanFull(),
                                    Textarea::make('trait')->label('特性备注')->rows(2)->columnSpanFull(),
                                ])
                                ->columns(3),
                        ]),
                    Tab::make('规则配置')
                        ->schema([
                            Section::make('宝石规则')
                                ->schema([
                                    Placeholder::make('gem_effect_type_display')
                                        ->label('效果类型')
                                        ->content(fn (Get $get): string => ItemEffectRegistry::effectTypeLabel((string) $get('type'), (string) $get('sub_type'))),
                                    Placeholder::make('gem_target_scope_display')
                                        ->label('目标范围')
                                        ->content('全局')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill'),
                                    Select::make('target_scope')
                                        ->label('目标技能')
                                        ->options(fn (): array => GemEffectRegistry::skillTargetOptions())
                                        ->searchable()
                                        ->preload()
                                        ->required(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') === 'skill')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') === 'skill'),
                                    Select::make('effect_form.stat')
                                        ->label('属性')
                                        ->options(GemEffectRegistry::pureStatOptions())
                                        ->searchable()
                                        ->required(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill'),
                                    TextInput::make('effect_form.value')
                                        ->label('数值')
                                        ->numeric()
                                        ->step(0.0001)
                                        ->required(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill'),
                                    Select::make('effect_form.value_type')
                                        ->label('数值类型')
                                        ->options(AdminOptions::valueModeOptions())
                                        ->required(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill')
                                        ->default('flat')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') !== 'skill'),
                                    Select::make('effect_form.effect_template_code')
                                        ->label('效果模板')
                                        ->options(GemEffectRegistry::skillTemplateOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') === 'skill')
                                        ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') === 'skill'),
                                    ...static::gemSkillParamInputs(),
                                    Select::make('socket_limit')->label('可镶嵌孔位')->options([
                                        '1' => '第1孔',
                                        '2' => '第2孔',
                                        '3' => '第3孔',
                                        '4' => '第4孔',
                                        'attr' => '属性孔',
                                        'skill' => '技能孔',
                                    ])->multiple()->searchable()->preload()->columnSpanFull()->visible(fn (Get $get): bool => $get('type') === 'gem'),
                                    Toggle::make('can_compose')->label('可合成')->default(false)->visible(fn (Get $get): bool => $get('type') === 'gem'),
                                    Toggle::make('can_reforge')->label('可洗炼')->default(false)->visible(fn (Get $get): bool => $get('type') === 'gem'),
                                ])
                                ->columns(3)
                                ->visible(fn (Get $get): bool => $get('type') === 'gem'),
                            Section::make('使用效果规则')
                                ->schema([
                                    Placeholder::make('use_effect_type_display')
                                        ->label('效果类型')
                                        ->content('使用效果'),
                                    Select::make('effect_form.use_effect_template_code')
                                        ->label('使用效果模板')
                                        ->options(ItemEffectRegistry::useEffectTemplateOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required(fn (Get $get): bool => ItemEffectRegistry::supportsUseEffect((string) $get('type'), (string) $get('sub_type'))),
                                    ...static::consumableEffectParamInputs(),
                                ])
                                ->columns(3)
                                ->visible(fn (Get $get): bool => ItemEffectRegistry::supportsUseEffect((string) $get('type'), (string) $get('sub_type'))),
                            Section::make('流转规则')
                                ->schema([
                                    Toggle::make('can_compose')->label('可合成')->default(false),
                                ])
                                ->columns(2)
                                ->visible(fn (Get $get): bool => $get('type') === 'blueprint_fragment'),
                            Section::make('当前类型无需额外规则')
                                ->schema([
                                    Placeholder::make('no_rule_hint')
                                        ->label('说明')
                                        ->content('当前物品类型只需要维护基础信息与状态，不需要配置效果或规则。'),
                                ])
                                ->visible(fn (Get $get): bool => ! ItemEffectRegistry::supportsRulesTab((string) $get('type'), (string) $get('sub_type'))),
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
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('物品 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::itemTypeOptions(), $state))->sortable(),
                TextColumn::make('material_type')->label('材料分类')->formatStateUsing(fn (?string $state): string => AdminOptions::itemMaterialTypeLabel($state))->toggleable(),
                TextColumn::make('sub_type')->label('业务分类')->formatStateUsing(fn (?string $state, Item $record): string => AdminOptions::itemSubTypeLabel((string) $record->type, $state, (string) ($record->material_type ?? '')))->toggleable(),
                TextColumn::make('effect_payload')
                    ->label('规则摘要')
                    ->formatStateUsing(fn ($state, Item $record): string => ItemEffectRegistry::effectSummary($record))
                    ->toggleable(),
                TextColumn::make('rarity')->label('稀有度')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state))->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('类型')->options(AdminOptions::itemTypeOptions()),
                Tables\Filters\SelectFilter::make('material_type')->label('材料分类')->options(AdminOptions::itemMaterialTypeOptions()),
                Tables\Filters\SelectFilter::make('sub_type')->label('业务分类')->options(AdminOptions::itemSubTypeOptions()),
                Tables\Filters\SelectFilter::make('rarity')->label('稀有度')->options(AdminOptions::rarityOptions()),
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

    protected static function gemSkillParamInputs(): array
    {
        $inputs = [];

        foreach (GemEffectRegistry::skillParamDefinitions() as $paramKey => $definition) {
            $templateCodes = [];
            foreach (GemEffectRegistry::skillTemplates() as $templateCode => $template) {
                if (array_key_exists($paramKey, $template['params'] ?? [])) {
                    $templateCodes[] = $templateCode;
                }
            }

            $inputs[] = TextInput::make("effect_form.{$paramKey}")
                ->label((string) $definition['label'])
                ->numeric()
                ->step($definition['step'] ?? 0.0001)
                ->minValue($definition['min'] ?? 0)
                ->visible(fn (Get $get): bool => $get('type') === 'gem' && $get('sub_type') === 'skill' && in_array((string) $get('effect_form.effect_template_code'), $templateCodes, true));
        }

        return $inputs;
    }

    protected static function consumableEffectParamInputs(): array
    {
        $inputs = [];

        foreach (ItemEffectRegistry::useEffectParamDefinitions() as $paramKey => $definition) {
            $templateCodes = [];
            foreach (ItemEffectRegistry::useEffectTemplates() as $templateCode => $template) {
                if (array_key_exists($paramKey, $template['params'] ?? [])) {
                    $templateCodes[] = $templateCode;
                }
            }

            $field = match ((string) ($definition['type'] ?? 'number')) {
                'item_select' => Select::make("effect_form.{$paramKey}")
                    ->label((string) $definition['label'])
                    ->options(fn (): array => AdminOptions::itemOptions())
                    ->searchable()
                    ->preload(),
                'dungeon_select' => Select::make("effect_form.{$paramKey}")
                    ->label((string) $definition['label'])
                    ->options(fn (): array => ItemEffectRegistry::dungeonOptions())
                    ->searchable()
                    ->preload(),
                default => TextInput::make("effect_form.{$paramKey}")
                    ->label((string) $definition['label'])
                    ->numeric()
                    ->step($definition['type'] === 'integer' ? 1 : ($definition['step'] ?? 0.0001))
                    ->minValue($definition['min'] ?? 0),
            };

            $inputs[] = $field->visible(fn (Get $get): bool => ItemEffectRegistry::supportsUseEffect((string) $get('type'), (string) $get('sub_type')) && in_array((string) $get('effect_form.use_effect_template_code'), $templateCodes, true));
        }

        return $inputs;
    }

    protected static function syncClassificationFields(Set $set, Get $get, ?string $type = null, ?string $materialType = null): void
    {
        $type = trim((string) ($type ?? $get('type') ?? 'material'));
        $materialType = $type === 'material'
            ? trim((string) ($materialType ?? $get('material_type') ?? AdminOptions::defaultMaterialTypeForRecord('material')))
            : (string) AdminOptions::defaultMaterialTypeForRecord($type);

        $set('material_type', $materialType);

        $subTypeOptions = AdminOptions::itemSubTypeOptions($type, $materialType);
        $currentSubType = trim((string) ($get('sub_type') ?? ''));
        if (! array_key_exists($currentSubType, $subTypeOptions)) {
            $currentSubType = (string) AdminOptions::defaultSubTypeForRecord($type, $materialType);
            $set('sub_type', $currentSubType);
        }

        static::syncEffectDrivenFields($set, $get, $type, $currentSubType);
    }

    protected static function syncEffectDrivenFields(Set $set, Get $get, ?string $type = null, ?string $subType = null): void
    {
        $type = trim((string) ($type ?? $get('type') ?? 'material'));
        $subType = trim((string) ($subType ?? $get('sub_type') ?? ''));

        if ($type === 'gem') {
            $set('effect_type', GemEffectRegistry::effectTypeForGemType($subType));
            $set('target_scope', $subType === 'skill' ? null : GemEffectRegistry::defaultTargetScope('attr'));

            return;
        }

        if (ItemEffectRegistry::supportsUseEffect($type, $subType)) {
            $set('effect_type', ItemEffectRegistry::USE_EFFECT_TYPE);
            $set('target_scope', null);

            return;
        }

        $set('effect_type', null);
        $set('target_scope', null);
    }
}
