<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GemCatalogResource\Pages;
use App\Models\Item;
use App\Support\AdminOptions;
use App\Support\GemEffectRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GemCatalogResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '宝石目录';

    protected static ?string $modelLabel = '宝石';

    protected static ?string $pluralModelLabel = '宝石目录';

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'gem');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('type')->default('gem'),
            Hidden::make('effect_type')->default(GemEffectRegistry::effectTypeForGemType('attr')),
            Section::make('基础信息')
                ->schema([
                    TextInput::make('id')->label('宝石 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                    Select::make('sub_type')
                        ->label('宝石类型')
                        ->options(AdminOptions::gemTypeOptions())
                        ->required()
                        ->default('attr')
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('effect_type', GemEffectRegistry::effectTypeForGemType($state));
                            if ($state === 'skill') {
                                $set('target_scope', null);

                                return;
                            }

                            $set('target_scope', GemEffectRegistry::defaultTargetScope('attr'));
                        }),
                    Select::make('rarity')->label('稀有度')->required()->options(AdminOptions::rarityOptions())->default('white'),
                    TextInput::make('drop_unlock_level')->label('掉落开放等级')->integer()->minValue(1)->required()->default(1),
                ])
                ->columns(3),
            Section::make('效果配置')
                ->description('按宝石类型填写结构化效果，不再手输程序键名。')
                ->schema([
                    Section::make('属性效果')
                        ->schema([
                            Select::make('effect_form.stat')
                                ->label('属性')
                                ->options(GemEffectRegistry::pureStatOptions())
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('sub_type') !== 'skill'),
                            TextInput::make('effect_form.value')
                                ->label('数值')
                                ->numeric()
                                ->step(0.0001)
                                ->required(fn (Get $get): bool => $get('sub_type') !== 'skill'),
                            Select::make('effect_form.value_type')
                                ->label('数值类型')
                                ->options(AdminOptions::valueModeOptions())
                                ->required(fn (Get $get): bool => $get('sub_type') !== 'skill')
                                ->default('flat'),
                        ])
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('sub_type') !== 'skill'),
                    Section::make('技能效果')
                        ->schema([
                            Select::make('target_scope')
                                ->label('作用技能')
                                ->options(fn (): array => GemEffectRegistry::skillTargetOptions())
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get): bool => $get('sub_type') === 'skill'),
                            Select::make('effect_form.effect_template_code')
                                ->label('效果模板')
                                ->options(GemEffectRegistry::skillTemplateOptions())
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required(fn (Get $get): bool => $get('sub_type') === 'skill'),
                            ...static::skillParamInputs(),
                        ])
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('sub_type') === 'skill'),
                    Select::make('socket_limit')->label('可镶嵌孔位')->multiple()->options([
                        '1' => '第1孔',
                        '2' => '第2孔',
                        '3' => '第3孔',
                        '4' => '第4孔',
                        'attr' => '属性孔',
                        'skill' => '技能孔',
                    ])->searchable()->preload()->helperText('第1/2孔通常为属性孔，第3/4孔通常为技能孔。')->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('图片与状态')
                ->schema([
                    FileUpload::make('icon')->label('图标')->disk('public')->directory('config/gem-icons')->image()->imagePreviewHeight('120'),
                    Toggle::make('can_compose')->label('可合成')->default(true),
                    Toggle::make('can_reforge')->label('可洗炼')->default(false),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('宝石 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('sub_type')->label('大类')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::gemTypeOptions(), $state)),
                TextColumn::make('rarity')->label('稀有度')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::rarityOptions(), $state)),
                TextColumn::make('effect_payload')
                    ->label('效果')
                    ->formatStateUsing(fn ($state, Item $record): string => GemEffectRegistry::effectSummary(
                        is_array($state) ? $state : [],
                        (string) ($record->target_scope ?? '')
                    )),
                TextColumn::make('drop_unlock_level')->label('掉落等级'),
                ToggleColumn::make('can_compose')->label('合成'),
                ToggleColumn::make('can_reforge')->label('洗炼'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sub_type')->label('宝石大类')->options(AdminOptions::gemTypeOptions()),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGemCatalogs::route('/'),
            'create' => Pages\CreateGemCatalog::route('/create'),
            'edit' => Pages\EditGemCatalog::route('/{record}/edit'),
        ];
    }

    protected static function skillParamInputs(): array
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
                ->visible(fn (Get $get): bool => $get('sub_type') === 'skill' && in_array((string) $get('effect_form.effect_template_code'), $templateCodes, true));
        }

        return $inputs;
    }
}
