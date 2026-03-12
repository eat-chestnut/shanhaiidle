<?php

namespace App\Filament\Pages;

use App\Services\SectTaskRulesService;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class SectTaskRulesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = '宗门任务';

    protected static ?string $title = '宗门任务';

    protected static ?int $navigationSort = 30;

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    protected string $view = 'filament.pages.sect-task-rules-page';

    public ?array $data = [];

    public function mount(): void
    {
        SectTaskRulesService::ensureDefaultSetting();
        $this->form->fill(SectTaskRulesService::loadConfig());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('SectTaskRulesTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('每日任务')
                            ->schema([
                                $this->taskRepeater('daily_tasks', '每日任务', '新增每日任务'),
                            ]),
                        Tab::make('里程碑')
                            ->schema([
                                $this->taskRepeater('milestone_tasks', '里程碑任务', '新增里程碑任务'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SectTaskRulesService::saveConfig($this->form->getState());

        Notification::make()
            ->title('宗门任务规则已保存')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('导出宗门任务')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    Artisan::call('game:export-sect-task-rules');

                    Notification::make()
                        ->title('已导出 sect_tasks_v1.json')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function taskRepeater(string $field, string $label, string $addLabel): Section
    {
        return Section::make($label)
            ->schema([
                Repeater::make($field)
                    ->label($label)
                    ->table([
                        TableColumn::make('任务'),
                        TableColumn::make('目标'),
                        TableColumn::make('解锁'),
                        TableColumn::make('奖励'),
                    ])
                    ->schema([
                        TextInput::make('task_id')->label('任务 ID')->required()->maxLength(64)->columnSpan(3),
                        TextInput::make('name')->label('任务名称')->required()->maxLength(255)->columnSpan(3),
                        Textarea::make('desc')->label('任务说明')->rows(2)->columnSpan(6),
                        Select::make('goal_type')->label('任务目标')->required()->options(SectTaskRulesService::goalTypeOptions())->columnSpan(3),
                        TextInput::make('target')->label('目标次数')->integer()->required()->minValue(1)->default(1)->columnSpan(2),
                        Select::make('unlock_stage_id')->label('主线要求')->options(AdminOptions::stageOptions())->searchable()->preload()->columnSpan(4),
                        Toggle::make('is_enabled')->label('启用')->default(true)->columnSpan(1),
                        TextInput::make('sort_order')->label('排序')->integer()->required()->minValue(0)->default(0)->columnSpan(2),
                        Section::make('奖励')
                            ->schema([
                                TextInput::make('rewards.gold')->label('金币')->integer()->minValue(0)->default(0),
                                TextInput::make('rewards.sect_contribution')->label('宗门贡献')->integer()->minValue(0)->default(0),
                                TextInput::make('rewards.skill_points')->label('技能点')->integer()->minValue(0)->default(0),
                                TextInput::make('rewards.spirit_stone')->label('灵石')->integer()->minValue(0)->default(0),
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
                    ->addActionLabel($addLabel)
                    ->columnSpanFull(),
            ]);
    }
}
