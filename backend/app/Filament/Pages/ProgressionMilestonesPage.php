<?php

namespace App\Filament\Pages;

use App\Services\ProgressionMilestonesService;
use App\Support\AdminOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class ProgressionMilestonesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-gift-top';

    protected static ?string $navigationLabel = '成长里程碑';

    protected static ?string $title = '成长里程碑';

    protected static ?int $navigationSort = 25;

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    protected string $view = 'filament.pages.progression-milestones-page';

    public ?array $data = [];

    public function mount(): void
    {
        ProgressionMilestonesService::ensureDefaultSetting();
        $this->form->fill(ProgressionMilestonesService::loadConfig());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('里程碑节点')
                    ->description('玩家侧会按卡片展示图片、标题、开放内容和领取礼包按钮。每个节点只挂一个奖励物品，多个奖励请在物品库先做礼包道具。')
                    ->schema([
                        Repeater::make('milestones')
                            ->hiddenLabel()
                            ->default(ProgressionMilestonesService::defaultConfig()['milestones'])
                            ->schema([
                                Section::make('基础信息')
                                    ->schema([
                                        TextInput::make('level')
                                            ->label('等级')
                                            ->integer()
                                            ->minValue(1)
                                            ->required()
                                            ->columnSpan(1),
                                        TextInput::make('milestone_key')
                                            ->label('标识')
                                            ->required()
                                            ->maxLength(64)
                                            ->columnSpan(1),
                                        TextInput::make('title')
                                            ->label('标题')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('image')
                                            ->label('图片')
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        Toggle::make('is_enabled')
                                            ->label('启用')
                                            ->default(true)
                                            ->columnSpan(1),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                                Textarea::make('summary')
                                    ->label('简述')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpanFull(),
                                Section::make('里程碑奖励')
                                    ->schema([
                                        Select::make('reward_item_id')
                                            ->label('奖励物品')
                                            ->options(AdminOptions::itemOptions())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpan(2),
                                        TextInput::make('reward_count')
                                            ->label('奖励数量')
                                            ->integer()
                                            ->required()
                                            ->minValue(1)
                                            ->default(1)
                                            ->columnSpan(1),
                                        Toggle::make('claim_once')
                                            ->label('仅可领取一次')
                                            ->default(true)
                                            ->columnSpan(1),
                                        TextInput::make('sort')
                                            ->label('排序')
                                            ->integer()
                                            ->required()
                                            ->minValue(0)
                                            ->default(0)
                                            ->columnSpan(1),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                                Section::make('开放内容')
                                    ->schema([
                                        Repeater::make('unlock_contents')
                                            ->hiddenLabel()
                                            ->table([
                                                Repeater\TableColumn::make('类型'),
                                                Repeater\TableColumn::make('显示文本'),
                                            ])
                                            ->schema([
                                                Select::make('type')
                                                    ->required()
                                                    ->options(ProgressionMilestonesService::unlockContentTypeOptions())
                                                    ->columnSpan(1),
                                                TextInput::make('content')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(3),
                                            ])
                                            ->columns(4)
                                            ->defaultItems(0)
                                            ->default([])
                                            ->itemLabel(fn (array $state): ?string => filled($state['content'] ?? null) ? (string) $state['content'] : null)
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->addActionLabel('新增开放内容')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? sprintf('Lv%s %s', (string) ($state['level'] ?? ''), (string) $state['title']) : null)
                            ->collapsible()
                            ->reorderable(false)
                            ->reorderableWithButtons(false)
                            ->reorderableWithDragAndDrop(false)
                            ->addActionLabel('新增里程碑节点')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        ProgressionMilestonesService::saveConfig($this->form->getState());

        Notification::make()
            ->title('成长里程碑已保存')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('导出成长里程碑')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    Artisan::call('game:export-progression-milestones');

                    Notification::make()
                        ->title('已导出 progression_milestones_v1.json')
                        ->success()
                        ->send();
                }),
        ];
    }
}
