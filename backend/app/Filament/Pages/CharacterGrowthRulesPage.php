<?php

namespace App\Filament\Pages;

use App\Services\CharacterGrowthRulesService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;

class CharacterGrowthRulesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = '人物成长';

    protected static ?string $title = '人物成长';

    protected static ?int $navigationSort = 20;

    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    protected string $view = 'filament.pages.character-growth-rules-page';

    public ?array $data = [];

    public function mount(): void
    {
        CharacterGrowthRulesService::ensureDefaultSetting();
        $this->form->fill(CharacterGrowthRulesService::loadConfig());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('CharacterGrowthRulesTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('等级与经验')
                            ->schema([
                                Section::make('等级上限')
                                    ->description('当前先围绕 1-20 玩法调校，但结构支持未来扩展到 60 级。')
                                    ->schema([
                                        TextInput::make('level_cap')
                                            ->label('等级上限')
                                            ->required()
                                            ->integer()
                                            ->minValue(20)
                                            ->default(60),
                                    ])
                                    ->columns(1),
                                Section::make('每级经验与属性点')
                                    ->description('按等级顺序维护每级升级所需经验和该级升级后获得的属性点。最后一级必须是等级上限减一。')
                                    ->schema([
                                        Repeater::make('level_exp_table')
                                            ->hiddenLabel()
                                            ->default(CharacterGrowthRulesService::defaultConfig()['level_exp_table'])
                                            ->minItems(1)
                                            ->columns(12)
                                            ->reorderable(false)
                                            ->reorderableWithButtons(false)
                                            ->reorderableWithDragAndDrop(false)
                                            ->table([
                                                TableColumn::make('等级'),
                                                TableColumn::make('升级经验'),
                                                TableColumn::make('属性点'),
                                            ])
                                            ->schema([
                                                TextInput::make('level')
                                                    ->label('等级')
                                                    ->integer()
                                                    ->required()
                                                    ->minValue(1)
                                                    ->columnSpan(3),
                                                TextInput::make('exp_to_next')
                                                    ->label('升到下一级所需经验')
                                                    ->integer()
                                                    ->required()
                                                    ->minValue(1)
                                                    ->columnSpan(5),
                                                TextInput::make('attr_points_gain')
                                                    ->label('升级属性点')
                                                    ->integer()
                                                    ->required()
                                                    ->minValue(0)
                                                    ->columnSpan(4),
                                            ])
                                            ->addActionLabel('新增等级经验行')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('起始模板')
                            ->schema([
                                Section::make('初始角色')
                                    ->description('用于新存档默认初始化，不影响已有 progress.json 的存档值。')
                                    ->schema([
                                        TextInput::make('initial.level')
                                            ->label('初始等级')
                                            ->integer()
                                            ->required()
                                            ->minValue(1),
                                        TextInput::make('initial.free_attr_points')
                                            ->label('初始自由属性点')
                                            ->integer()
                                            ->required()
                                            ->minValue(0),
                                        TextInput::make('initial.skill_points')
                                            ->label('初始技能点')
                                            ->integer()
                                            ->required()
                                            ->minValue(0),
                                        Select::make('initial.current_class')
                                            ->label('初始宗门')
                                            ->required()
                                            ->options(CharacterGrowthRulesService::classOptions()),
                                    ])
                                    ->columns(4),
                                Section::make('初始六维')
                                    ->schema([
                                        TextInput::make('initial.base_attributes.strength')->label('力道')->integer()->required()->minValue(0),
                                        TextInput::make('initial.base_attributes.physique')->label('体魄')->integer()->required()->minValue(0),
                                        TextInput::make('initial.base_attributes.agility')->label('身法')->integer()->required()->minValue(0),
                                        TextInput::make('initial.base_attributes.spirit')->label('神识')->integer()->required()->minValue(0),
                                        TextInput::make('initial.base_attributes.true_energy')->label('真元')->integer()->required()->minValue(0),
                                        TextInput::make('initial.base_attributes.fortune')->label('运势')->integer()->required()->minValue(0),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('属性换算')
                            ->schema([
                                Section::make('基础成长')
                                    ->schema([
                                        TextInput::make('base_growth.hp.base')->label('生命基础值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.hp.per_level_every')->label('生命每隔几级成长')->integer()->required()->minValue(1),
                                        TextInput::make('base_growth.hp.per_level_gain')->label('生命每次成长值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.qi.base')->label('真气基础值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.qi.per_level_every')->label('真气每隔几级成长')->integer()->required()->minValue(1),
                                        TextInput::make('base_growth.qi.per_level_gain')->label('真气每次成长值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.atk.base')->label('攻击基础值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.atk.per_level_gain')->label('攻击每级成长')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.def.base')->label('防御基础值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.def.per_level_gain')->label('防御每级成长')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.crit_percent.base')->label('暴击率基础值')->numeric()->required()->minValue(0),
                                        TextInput::make('base_growth.loot_bonus_percent.base')->label('掉落加成基础值')->numeric()->required()->minValue(0),
                                    ])
                                    ->columns(3),
                                Section::make('六维换算')
                                    ->schema([
                                        TextInput::make('attribute_formulas.physique.hp_per_point')->label('体魄每点生命')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.physique.hp_extra_every_10')->label('体魄每10点额外生命')->integer()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.physique.def_per_point')->label('体魄每点防御')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.true_energy.qi_per_point')->label('真元每点真气')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.agility.crit_percent_per_point')->label('身法每点暴击率')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.agility.dodge_per_point')->label('身法每点闪避')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.agility.attack_speed_per_point')->label('身法每点攻速')->numeric()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.strength.phys_mul_permille_per_point')->label('力道每点物理倍率(千分比)')->integer()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.spirit.spell_mul_permille_per_point')->label('神识每点术法倍率(千分比)')->integer()->required()->minValue(0),
                                        TextInput::make('attribute_formulas.fortune.loot_bonus_percent_per_point')->label('运势每点掉落加成')->numeric()->required()->minValue(0),
                                    ])
                                    ->columns(3),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        CharacterGrowthRulesService::saveConfig($this->form->getState());

        Notification::make()
            ->title('人物成长规则已保存')
            ->body('当前人物成长配置已写入后台配置源。')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('导出人物成长')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    Artisan::call('game:export-character-growth-rules');

                    Notification::make()
                        ->title('已导出 character_growth_rules_v1.json')
                        ->success()
                        ->send();
                }),
        ];
    }
}
