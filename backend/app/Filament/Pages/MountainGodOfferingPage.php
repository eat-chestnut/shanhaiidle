<?php

namespace App\Filament\Pages;

use App\Services\MountainGodOfferingService;
use App\Support\AdminOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class MountainGodOfferingPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = '南山山神';

    protected static ?string $title = '南山山神';

    protected static ?int $navigationSort = 40;

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    protected string $view = 'filament.pages.mountain-god-offering-page';

    public ?array $data = [];

    public function mount(): void
    {
        MountainGodOfferingService::ensureDefaultSetting();
        $this->form->fill(MountainGodOfferingService::loadConfig());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('山神基础')
                    ->schema([
                        TextInput::make('god_id')->label('山神 ID')->required()->maxLength(64),
                        TextInput::make('name')->label('名称')->required()->maxLength(255),
                        Select::make('unlock_stage_id')->label('解锁主线').required()->options(AdminOptions::stageOptions())->searchable()->preload(),
                        Textarea::make('description')->label('说明')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('供奉项')
                    ->schema([
                        Repeater::make('offerings')
                            ->label('供奉项')
                            ->table([
                                TableColumn::make('供奉项'),
                                TableColumn::make('兑换成本'),
                                TableColumn::make('供奉奖励'),
                            ])
                            ->schema([
                                TextInput::make('offering_id')->label('供奉项 ID')->required()->maxLength(64)->columnSpan(3),
                                TextInput::make('name')->label('名称')->required()->maxLength(255)->columnSpan(3),
                                Select::make('offering_item_id')->label('祭品物品')->required()->options(AdminOptions::itemOptions())->searchable()->preload()->columnSpan(4),
                                Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                                TextInput::make('daily_limit')->label('每日上限')->integer()->required()->minValue(1)->default(1)->columnSpan(1),
                                TextInput::make('sort_order')->label('排序')->integer()->required()->minValue(0)->default(0)->columnSpan(2),
                                TextInput::make('exchange_cost.gold')->label('兑换金币')->integer()->minValue(0)->default(0)->columnSpan(2),
                                TextInput::make('exchange_cost.sect_contribution')->label('兑换宗门贡献')->integer()->minValue(0)->default(0)->columnSpan(2),
                                Section::make('供奉奖励')
                                    ->schema([
                                        TextInput::make('rewards.gold')->label('金币')->integer()->minValue(0)->default(0),
                                        TextInput::make('rewards.spirit_stone')->label('灵石')->integer()->minValue(0)->default(0),
                                        TextInput::make('rewards.sect_contribution')->label('宗门贡献')->integer()->minValue(0)->default(0),
                                        TextInput::make('rewards.skill_points')->label('技能点')->integer()->minValue(0)->default(0),
                                        Repeater::make('rewards.items')
                                            ->label('额外物品')
                                            ->table([
                                                TableColumn::make('物品'),
                                                TableColumn::make('数量'),
                                            ])
                                            ->schema([
                                                Select::make('item_id')->label('物品')->options(AdminOptions::itemOptions())->searchable()->preload()->required()->columnSpan(8),
                                                TextInput::make('count')->label('数量')->integer()->required()->minValue(1)->default(1)->columnSpan(4),
                                            ])
                                            ->columns(12)
                                            ->defaultItems(0)
                                            ->default([])
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->addActionLabel('新增奖励物品')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->default([])
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? (string) $state['name'] : null)
                            ->reorderable(false)
                            ->reorderableWithButtons(false)
                            ->reorderableWithDragAndDrop(false)
                            ->addActionLabel('新增供奉项')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        MountainGodOfferingService::saveConfig($this->form->getState());

        Notification::make()
            ->title('南山山神配置已保存')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('导出山神配置')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    Artisan::call('game:export-mountain-god-offerings');

                    Notification::make()
                        ->title('已导出 mountain_god_v1.json')
                        ->success()
                        ->send();
                }),
        ];
    }
}
