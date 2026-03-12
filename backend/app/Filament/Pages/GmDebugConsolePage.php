<?php

namespace App\Filament\Pages;

use App\Models\BlueGearTemplate;
use App\Models\EquipTemplate;
use App\Models\User;
use App\Services\GmDebugService;
use App\Support\AdminOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RuntimeException;

class GmDebugConsolePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = '玩家调试';

    protected static ?string $title = 'GM控制台';

    protected static ?int $navigationSort = 10;

    protected static string | \UnitEnum | null $navigationGroup = '调试与GM';

    protected string $view = 'filament.pages.gm-debug-console-page';

    public ?array $data = [];

    public array $profileSnapshot = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && GmDebugService::canAccess($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->profileSnapshot = app(GmDebugService::class)->loadSnapshot('');
        $this->form->fill([
            'target_player_id' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('目标玩家')
                    ->description('当前后台尚未接入正式玩家云档案表，GM V1 会基于现有最小调试档案操作 player_id，并把所有改档动作写入日志。')
                    ->schema([
                        TextInput::make('target_player_id')
                            ->label('目标玩家 ID')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('例如：player_demo')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->refreshSnapshot((string) $state);
                            }),
                    ]),
                Section::make('玩家概览')
                    ->schema([
                        Placeholder::make('profile_status')
                            ->label('档案状态')
                            ->content(fn (): string => (bool) ($this->profileSnapshot['exists'] ?? false)
                                ? '已存在'
                                : '未找到，首次 GM 操作会自动创建最小调试档案'),
                        Placeholder::make('profile_level')
                            ->label('等级 / 经验')
                            ->content(fn (): string => sprintf(
                                'Lv%d / EXP %d',
                                (int) ($this->profileSnapshot['level'] ?? 0),
                                (int) ($this->profileSnapshot['exp'] ?? 0),
                            )),
                        Placeholder::make('profile_currency')
                            ->label('货币')
                            ->content(fn (): string => sprintf(
                                '金币 %d / 晶石 %d / 贡献 %d',
                                (int) ($this->profileSnapshot['gold'] ?? 0),
                                (int) ($this->profileSnapshot['crystal'] ?? 0),
                                (int) ($this->profileSnapshot['contribution'] ?? 0),
                            )),
                        Placeholder::make('profile_points')
                            ->label('点数')
                            ->content(fn (): string => sprintf(
                                '自由属性点 %d / 技能点 %d',
                                (int) ($this->profileSnapshot['free_attr_points'] ?? 0),
                                (int) ($this->profileSnapshot['skill_points'] ?? 0),
                            )),
                        Placeholder::make('profile_inventory')
                            ->label('背包')
                            ->content(fn (): string => sprintf(
                                '%d 种物品 / 共 %d 个',
                                (int) ($this->profileSnapshot['inventory_kind_count'] ?? 0),
                                (int) ($this->profileSnapshot['inventory_total_count'] ?? 0),
                            )),
                        Placeholder::make('profile_equipment')
                            ->label('装备')
                            ->content(fn (): string => sprintf(
                                '%d 件装备 / 已领里程碑 %d 项',
                                (int) ($this->profileSnapshot['equipment_count'] ?? 0),
                                (int) ($this->profileSnapshot['claimed_milestone_count'] ?? 0),
                            )),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_snapshot')
                ->label('刷新目标玩家')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $playerId = $this->targetPlayerId();
                    if ($playerId === '') {
                        Notification::make()
                            ->title('请先输入目标玩家 ID')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->refreshSnapshot($playerId);

                    Notification::make()
                        ->title('玩家概览已刷新')
                        ->success()
                        ->send();
                }),
            Action::make('set_level')
                ->label('设置等级')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    TextInput::make('level')
                        ->label('目标等级')
                        ->required()
                        ->integer()
                        ->minValue(1),
                ])
                ->fillForm(fn (): array => ['level' => max(1, (int) ($this->profileSnapshot['level'] ?? 1))])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->setLevel(
                            $operator,
                            $this->targetPlayerId(),
                            (int) $data['level'],
                        ),
                    );
                }),
            Action::make('add_exp')
                ->label('增加经验')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    TextInput::make('amount')
                        ->label('经验增量')
                        ->required()
                        ->integer()
                        ->minValue(1),
                ])
                ->fillForm(fn (): array => ['amount' => 100])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->addExp(
                            $operator,
                            $this->targetPlayerId(),
                            (int) $data['amount'],
                        ),
                    );
                }),
            Action::make('set_currency')
                ->label('设置货币')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    Select::make('currency_type')
                        ->label('货币类型')
                        ->required()
                        ->options(AdminOptions::shopCurrencyOptions()),
                    TextInput::make('amount')
                        ->label('目标数量')
                        ->required()
                        ->integer()
                        ->minValue(0),
                ])
                ->fillForm(fn (): array => ['currency_type' => 'gold', 'amount' => 0])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->setCurrency(
                            $operator,
                            $this->targetPlayerId(),
                            (string) $data['currency_type'],
                            (int) $data['amount'],
                        ),
                    );
                }),
            Action::make('set_points')
                ->label('设置点数')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    TextInput::make('free_attr_points')
                        ->label('自由属性点')
                        ->required()
                        ->integer()
                        ->minValue(0),
                    TextInput::make('skill_points')
                        ->label('技能点')
                        ->required()
                        ->integer()
                        ->minValue(0),
                ])
                ->fillForm(fn (): array => [
                    'free_attr_points' => max(0, (int) ($this->profileSnapshot['free_attr_points'] ?? 0)),
                    'skill_points' => max(0, (int) ($this->profileSnapshot['skill_points'] ?? 0)),
                ])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->setPoints(
                            $operator,
                            $this->targetPlayerId(),
                            (int) $data['free_attr_points'],
                            (int) $data['skill_points'],
                        ),
                    );
                }),
            Action::make('grant_item')
                ->label('发放物品')
                ->icon('heroicon-o-cube')
                ->color('primary')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    Select::make('item_id')
                        ->label('物品')
                        ->required()
                        ->options(AdminOptions::itemOptions())
                        ->searchable()
                        ->preload(),
                    TextInput::make('count')
                        ->label('数量')
                        ->required()
                        ->integer()
                        ->minValue(1),
                ])
                ->fillForm(fn (): array => ['count' => 1])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->grantItem(
                            $operator,
                            $this->targetPlayerId(),
                            (string) $data['item_id'],
                            (int) $data['count'],
                        ),
                    );
                }),
            Action::make('grant_equipment')
                ->label('发放装备')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->form([
                    Select::make('template_id')
                        ->label('装备模板')
                        ->required()
                        ->options($this->equipmentTemplateOptions())
                        ->searchable()
                        ->preload(),
                    TextInput::make('count')
                        ->label('数量')
                        ->required()
                        ->integer()
                        ->minValue(1)
                        ->default(1),
                ])
                ->fillForm(fn (): array => ['count' => 1])
                ->action(function (array $data): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->grantEquipment(
                            $operator,
                            $this->targetPlayerId(),
                            (string) $data['template_id'],
                            (int) $data['count'],
                        ),
                    );
                }),
            Action::make('reset_milestones')
                ->label('重置里程碑领取')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->resetMilestones(
                            $operator,
                            $this->targetPlayerId(),
                        ),
                    );
                }),
            Action::make('reset_shop_limits')
                ->label('重置商城限购')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('danger')
                ->visible(fn (): bool => filled($this->targetPlayerId()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->dispatchOperation(
                        fn (GmDebugService $service, User $operator): array => $service->resetShopLimits(
                            $operator,
                            $this->targetPlayerId(),
                        ),
                    );
                }),
        ];
    }

    private function dispatchOperation(callable $callback): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new RuntimeException('当前管理员未登录。');
        }

        $result = $callback(app(GmDebugService::class), $user);
        $this->refreshSnapshot($this->targetPlayerId());

        Notification::make()
            ->title(($result['ok'] ?? false) ? 'GM 操作成功' : 'GM 操作失败')
            ->body((string) ($result['message'] ?? ''))
            ->{($result['ok'] ?? false) ? 'success' : 'danger'}()
            ->send();
    }

    private function refreshSnapshot(string $playerId): void
    {
        $this->profileSnapshot = app(GmDebugService::class)->loadSnapshot($playerId);
    }

    private function targetPlayerId(): string
    {
        return trim((string) data_get($this->data, 'target_player_id', ''));
    }

    private function equipmentTemplateOptions(): array
    {
        $normalOptions = EquipTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (EquipTemplate $template): array => [
                $template->id => sprintf('装备：%s', (string) $template->name),
            ])
            ->all();

        $blueOptions = BlueGearTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (BlueGearTemplate $template): array => [
                (string) $template->template_id => sprintf('蓝装：%s', (string) $template->name),
            ])
            ->all();

        return $normalOptions + $blueOptions;
    }
}
