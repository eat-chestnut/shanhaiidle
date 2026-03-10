<?php

namespace App\Filament\Pages;

use App\Services\EquipmentGrowthRulesService;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $navigationGroup = '配置管理';

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
                    ->tabs([
                        Tab::make('升星与开孔')
                            ->schema([
                                TextInput::make('star_caps.20')->label('20级星级上限')->integer()->required()->minValue(0),
                                TextInput::make('star_caps.40')->label('40级星级上限')->integer()->required()->minValue(0),
                                TextInput::make('star_caps.50')->label('50级星级上限')->integer()->required()->minValue(0),
                                TextInput::make('star_caps.60')->label('60级星级上限')->integer()->required()->minValue(0),
                                TextInput::make('socket_unlocks.3')->label('3星开孔数')->integer()->required()->minValue(0),
                                TextInput::make('socket_unlocks.6')->label('6星开孔数')->integer()->required()->minValue(0),
                                TextInput::make('socket_unlocks.8')->label('8星开孔数')->integer()->required()->minValue(0),
                                TextInput::make('socket_unlocks.10')->label('10星开孔数')->integer()->required()->minValue(0),
                                KeyValue::make('star_material_stage')
                                    ->label('升星材料阶段')
                                    ->keyLabel('阶段')
                                    ->valueLabel('材料名')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        Tab::make('套装与词条')
                            ->schema([
                                TextInput::make('set_stage_piece_count.20')->label('20级套件数')->integer()->required()->minValue(0),
                                TextInput::make('set_stage_piece_count.40')->label('40级套件数')->integer()->required()->minValue(0),
                                TextInput::make('set_stage_piece_count.60')->label('60级套件数')->integer()->required()->minValue(0),
                                TextInput::make('blue_affix_unlock_level')->label('蓝词条开放等级')->integer()->required()->minValue(1),
                                TextInput::make('purple_affix_unlock_level')->label('紫词条开放等级')->integer()->required()->minValue(1),
                                TagsInput::make('resonance_thresholds')
                                    ->label('共鸣档位')
                                    ->separator(',')
                                    ->placeholder('3,6,8,10')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        Tab::make('蓝装与装备限制')
                            ->schema([
                                TextInput::make('main_equipment_limits.ring')->label('戒指装备上限')->integer()->required()->minValue(1),
                                TextInput::make('main_equipment_limits.bracelet')->label('手镯装备上限')->integer()->required()->minValue(1),
                                TagsInput::make('blue_gear_rules.allowed_slots')->label('蓝装允许部位'),
                                TagsInput::make('blue_gear_rules.forbidden_slots')->label('蓝装禁用部位'),
                                TextInput::make('blue_gear_rules.drop_from_boss_only')
                                    ->label('仅Boss掉落(1/0)')
                                    ->integer()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(1),
                                TextInput::make('blue_gear_rules.can_star_up')->label('蓝装可升星(1/0)')->integer()->required()->minValue(0)->maxValue(1),
                                TextInput::make('blue_gear_rules.can_rank_up')->label('蓝装可升阶(1/0)')->integer()->required()->minValue(0)->maxValue(1),
                                TextInput::make('blue_gear_rules.can_socket')->label('蓝装可镶嵌(1/0)')->integer()->required()->minValue(0)->maxValue(1),
                                TextInput::make('blue_gear_rules.can_reforge')->label('蓝装可洗炼(1/0)')->integer()->required()->minValue(0)->maxValue(1),
                            ])->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Convert 1/0 text fields to bool for blue gear rules.
        foreach (['drop_from_boss_only', 'can_star_up', 'can_rank_up', 'can_socket', 'can_reforge'] as $key) {
            $state['blue_gear_rules'][$key] = ((int) ($state['blue_gear_rules'][$key] ?? 0)) === 1;
        }

        $state['resonance_thresholds'] = array_values(array_map('intval', $state['resonance_thresholds'] ?? []));

        EquipmentGrowthRulesService::saveConfig($state);

        Notification::make()
            ->title('装备成长规则已保存')
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
