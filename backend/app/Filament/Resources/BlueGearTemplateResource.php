<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueGearTemplateResource\Pages;
use App\Models\BlueGearTemplate;
use App\Support\AdminOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BlueGearTemplateResource extends Resource
{
    protected static ?string $model = BlueGearTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = '蓝装模板';

    protected static ?string $modelLabel = '蓝装模板';

    protected static ?string $pluralModelLabel = '蓝装模板';

    protected static ?string $navigationGroup = '装备成长';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('基础信息')
                ->description('蓝装模板只定义部位、等级和蓝词条规则，不再通过流派、池 ID、标签做间接限制。')
                ->schema([
                    TextInput::make('template_id')->label('模板 ID')->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                    Select::make('slot_id')
                        ->label('部位')
                        ->required()
                        ->options(AdminOptions::slotOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('affix_entries', []);
                        }),
                    TextInput::make('required_level')->label('穿戴等级')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('min_affix_count')->label('最少蓝词条数')->integer()->minValue(0)->required()->default(1),
                    TextInput::make('max_affix_count')->label('最多蓝词条数')->integer()->minValue(0)->required()->default(1),
                ])
                ->columns(3),
            Section::make('白色主属性')
                ->description('白色主属性使用标准结构维护，不允许运营手写属性名或直接填原始 JSON。')
                ->schema([
                    Repeater::make('white_stats')
                        ->label('白色主属性')
                        ->default([])
                        ->columns(12)
                        ->schema([
                            Select::make('stat')
                                ->label('属性')
                                ->options(AdminOptions::statOptions())
                                ->searchable()
                                ->required()
                                ->columnSpan(7),
                            TextInput::make('value')
                                ->label('数值')
                                ->integer()
                                ->minValue(0)
                                ->required()
                                ->default(0)
                                ->columnSpan(5),
                        ])
                        ->addActionLabel('新增白色主属性')
                        ->columnSpanFull(),
                ]),
            Section::make('蓝词条规则')
                ->description('随机蓝词条数量使用最少/最多区间定义。可抽蓝词条与权重完全由蓝装模板决定。')
                ->schema([
                    Repeater::make('affix_entries')
                        ->label('可抽蓝词条')
                        ->default([])
                        ->columns(12)
                        ->reorderableWithButtons(false)
                        ->reorderableWithDragAndDrop(true)
                        ->itemLabel(function (array $state): ?string {
                            $name = AdminOptions::blueAffixName((string) ($state['affix_id'] ?? ''));
                            $weight = max(0, (int) ($state['weight'] ?? 0));

                            return $name === '' ? null : sprintf('%s × 抽取权重%d', $name, $weight);
                        })
                        ->schema([
                            Select::make('affix_id')
                                ->label('蓝词条')
                                ->options(fn (Get $get): array => AdminOptions::blueAffixOptions((string) $get('../../slot_id')))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(9)
                                ->helperText('仅显示允许部位包含当前蓝装模板部位的蓝词条。'),
                            TextInput::make('weight')
                                ->label('抽取权重')
                                ->integer()
                                ->minValue(0)
                                ->required()
                                ->default(1)
                                ->columnSpan(3),
                            Hidden::make('sort_order')
                                ->default(0),
                        ])
                        ->addActionLabel('新增可抽蓝词条')
                        ->columnSpanFull(),
                ]),
            Section::make('图片资源与状态')
                ->schema([
                    FileUpload::make('icon')->label('图标')->disk('public')->directory('config/blue-gear-icons')->image()->imagePreviewHeight('120'),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->default(0)->required(),
                ])
                ->columns(3),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template_id')->label('模板 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slot_id')->label('部位')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::slotOptions(), $state))->sortable(),
                TextColumn::make('required_level')->label('等级')->sortable(),
                TextColumn::make('affix_range')
                    ->label('蓝词条数量')
                    ->state(fn (BlueGearTemplate $record): string => sprintf('%d ~ %d', $record->resolvedMinAffixCount(), $record->resolvedMaxAffixCount())),
                TextColumn::make('affix_entries')
                    ->label('已配蓝词条')
                    ->state(fn (BlueGearTemplate $record): int => count($record->resolvedAffixEntries()))
                    ->sortable(false),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('slot_id')->label('部位')->options(AdminOptions::slotOptions()),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlueGearTemplates::route('/'),
            'create' => Pages\CreateBlueGearTemplate::route('/create'),
            'edit' => Pages\EditBlueGearTemplate::route('/{record}/edit'),
        ];
    }
}
