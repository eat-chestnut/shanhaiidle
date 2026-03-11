<?php

namespace App\Filament\Pages;

use App\Services\EquipmentGrowthRulesService;
use App\Support\AdminOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class EquipmentGrowthRulesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '装备成长规则';

    protected static ?string $title = '装备成长规则';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationGroup = '装备成长';

    protected static string $view = 'filament.pages.equipment-growth-rules-page';

    public ?array $data = [];

    public function mount(): void
    {
        EquipmentGrowthRulesService::ensureDefaultSetting();
        $this->form->fill(EquipmentGrowthRulesService::loadConfig());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('GrowthRulesTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('升星与升孔')
                            ->schema([
                                Section::make('星级上限')
                                    ->description('按等级段限制装备最高可升到的星级。')
                                    ->schema([
                                        TextInput::make('star_caps.20')->label('20级装备')->integer()->required()->minValue(0),
                                        TextInput::make('star_caps.40')->label('40级装备')->integer()->required()->minValue(0),
                                        TextInput::make('star_caps.50')->label('50级装备')->integer()->required()->minValue(0),
                                        TextInput::make('star_caps.60')->label('60级装备')->integer()->required()->minValue(0),
                                    ])
                                    ->columns(4),
                                Section::make('星级开孔规则')
                                    ->description('固定生效规则，不再维护随机孔位权重，也不再提供后台编辑入口。')
                                    ->schema([
                                        Placeholder::make('socket_rule_3')
                                            ->label('3星')
                                            ->content('开第1孔'),
                                        Placeholder::make('socket_rule_6')
                                            ->label('6星')
                                            ->content('开第2孔'),
                                        Placeholder::make('socket_rule_8')
                                            ->label('8星')
                                            ->content('开第3孔'),
                                        Placeholder::make('socket_rule_10')
                                            ->label('10星')
                                            ->content('开第4孔'),
                                    ])
                                    ->columns(4),
                                Section::make('升星材料阶段')
                                    ->description('用于导出给前端识别当前星级段对应的材料档位。')
                                    ->schema([
                                        KeyValue::make('star_material_stage')
                                            ->label('阶段与材料名称')
                                            ->keyLabel('阶段区间')
                                            ->valueLabel('材料显示名')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('套装与词条')
                            ->schema([
                                Section::make('套装总件数规则')
                                    ->description('定义各等级套装默认总件数，用于套装激活和导出。')
                                    ->schema([
                                        TextInput::make('set_stage_piece_count.20')->label('20级套装')->integer()->required()->minValue(0),
                                        TextInput::make('set_stage_piece_count.40')->label('40级套装')->integer()->required()->minValue(0),
                                        TextInput::make('set_stage_piece_count.60')->label('60级套装')->integer()->required()->minValue(0),
                                    ])
                                    ->columns(3),
                                Section::make('词条开放规则')
                                    ->description('蓝词条和紫色洗练在角色达到指定等级后开放。')
                                    ->schema([
                                        TextInput::make('blue_affix_unlock_level')->label('蓝词条开放等级')->integer()->required()->minValue(1)->helperText('建议 30 级'),
                                        TextInput::make('purple_affix_unlock_level')->label('紫色洗练开放等级')->integer()->required()->minValue(1)->helperText('建议 50 级'),
                                        TagsInput::make('resonance_thresholds')
                                            ->label('全身共鸣档位')
                                            ->separator(',')
                                            ->placeholder('输入后回车，例如 3 / 6 / 8 / 10')
                                            ->helperText('数值越小越早触发，导出时会按数字数组写入。')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('蓝装限制')
                            ->schema([
                                Section::make('主养成装备上限')
                                    ->description('戒指和手镯属于配置层单部位，但运行时允许多件装备。')
                                    ->schema([
                                        TextInput::make('main_equipment_limits.ring')->label('戒指装备上限')->integer()->required()->minValue(1),
                                        TextInput::make('main_equipment_limits.bracelet')->label('手镯装备上限')->integer()->required()->minValue(1),
                                    ])
                                    ->columns(2),
                                Section::make('蓝装允许与禁用部位')
                                    ->description('允许部位用于 Boss 蓝装掉落，禁用部位会被系统明确排除。')
                                    ->schema([
                                        Select::make('blue_gear_rules.allowed_slots')
                                            ->label('蓝装允许部位')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(AdminOptions::slotOptions())
                                            ->helperText('理论上不应该手敲部位值，统一从预设部位中选择。'),
                                        Select::make('blue_gear_rules.forbidden_slots')
                                            ->label('蓝装禁用部位')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(AdminOptions::slotOptions()),
                                    ])
                                    ->columns(2),
                                Section::make('蓝装行为限制')
                                    ->description('这些开关决定蓝装是否能进入升星、升阶、镶嵌和洗练等主养成流程。')
                                    ->schema([
                                        Toggle::make('blue_gear_rules.drop_from_boss_only')->label('仅 Boss 掉落'),
                                        Toggle::make('blue_gear_rules.can_star_up')->label('允许升星'),
                                        Toggle::make('blue_gear_rules.can_rank_up')->label('允许升阶'),
                                        Toggle::make('blue_gear_rules.can_socket')->label('允许镶嵌宝石'),
                                        Toggle::make('blue_gear_rules.can_reforge')->label('允许洗练'),
                                    ])
                                    ->columns(3),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['resonance_thresholds'] = array_values(array_map('intval', $state['resonance_thresholds'] ?? []));

        EquipmentGrowthRulesService::saveConfig($state);

        Notification::make()
            ->title('装备成长规则已保存')
            ->body('已按新的录入规范更新成长规则配置。')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('导出成长规则')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    Artisan::call('game:export-equipment-growth-rules');

                    Notification::make()
                        ->title('已导出 equipment_growth_rules_v1.json')
                        ->success()
                        ->send();
                }),
        ];
    }
}
