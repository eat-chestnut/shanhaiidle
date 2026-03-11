<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StarterGiftResource\Pages;
use App\Models\StarterGift;
use App\Support\AdminOptions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StarterGiftResource extends Resource
{
    protected static ?string $model = StarterGift::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = '新手礼包';
    protected static ?string $modelLabel = '新手礼包';
    protected static ?string $pluralModelLabel = '新手礼包';
    protected static ?string $navigationGroup = '世界观与主线';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('礼包基础信息')
                ->schema([
                    TextInput::make('gift_id')->label('礼包 ID')->required()->maxLength(120)->unique(ignoreRecord: true),
                    TextInput::make('unlock_level')->label('解锁等级')->integer()->minValue(1)->required()->default(1),
                    TextInput::make('gift_name')->label('礼包名称')->required()->maxLength(255),
                    TextInput::make('gift_role')->label('礼包定位')->required()->maxLength(120),
                    Toggle::make('must_claim')->label('必须领取')->default(true),
                    Toggle::make('is_free')->label('免费')->default(true),
                ])
                ->columns(3),
            Section::make('开启文案与奖励')
                ->schema([
                    Textarea::make('open_copy')->label('开启文案')->rows(4)->columnSpanFull(),
                    Repeater::make('rewards')
                        ->label('奖励列表')
                        ->helperText('奖励条目统一从物品库选择，物品名称随物品自动带出，不允许运营手敲物品 ID 或名称。')
                        ->schema([
                            Select::make('reward_type')
                                ->label('奖励类型')
                                ->options(AdminOptions::starterGiftRewardTypeOptions())
                                ->default('fixed')
                                ->required()
                                ->native(false)
                                ->afterStateHydrated(function (?string $state, Set $set, Get $get): void {
                                    if (blank($state)) {
                                        $set('reward_type', $get('optional_group') ? 'choice' : 'fixed');
                                    }
                                })
                                ->columnSpan(2),
                            Select::make('item_id')
                                ->label('物品')
                                ->options(fn (): array => AdminOptions::itemOptions())
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('item_name', AdminOptions::itemName($state));
                                })
                                ->columnSpan(6),
                            Hidden::make('item_name')
                                ->dehydrateStateUsing(fn (Get $get): string => AdminOptions::itemName((string) $get('item_id'))),
                            TextInput::make('count')
                                ->label('数量')
                                ->integer()
                                ->minValue(1)
                                ->required()
                                ->default(1)
                                ->columnSpan(1),
                            TextInput::make('optional_group')
                                ->label('可选组')
                                ->maxLength(120)
                                ->visible(fn (Get $get): bool => $get('reward_type') === 'choice')
                                ->helperText('仅自选奖励需要填写，例如 weapon_choice_20。')
                                ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => $get('reward_type') === 'choice' ? blank($state) ? null : trim($state) : null)
                                ->columnSpan(2),
                            TextInput::make('sort_order')
                                ->label('排序')
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->columnSpan(1),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel('新增奖励')
                        ->default([])
                        ->columnSpanFull(),
                ]),
            Section::make('图片与状态')
                ->schema([
                    FileUpload::make('icon_path')->label('礼包图标')->disk('public')->directory('config/starter-gifts/icons')->image()->imagePreviewHeight('100'),
                    FileUpload::make('banner_path')->label('礼包横幅')->disk('public')->directory('config/starter-gifts/banners')->image()->imagePreviewHeight('140'),
                    TextInput::make('sort_order')->label('排序')->integer()->minValue(0)->required()->default(0),
                    Toggle::make('is_enabled')->label('启用')->default(true),
                ])
                ->columns(4),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gift_id')->label('礼包 ID')->searchable()->sortable(),
                TextColumn::make('gift_name')->label('礼包名称')->searchable()->sortable(),
                TextColumn::make('gift_role')->label('礼包定位')->sortable(),
                TextColumn::make('unlock_level')->label('解锁等级')->sortable(),
                ToggleColumn::make('must_claim')->label('必领'),
                ToggleColumn::make('is_free')->label('免费'),
                ToggleColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('must_claim')->label('必须领取'),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
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
            'index' => Pages\ListStarterGifts::route('/'),
            'create' => Pages\CreateStarterGift::route('/create'),
            'edit' => Pages\EditStarterGift::route('/{record}/edit'),
        ];
    }
}
