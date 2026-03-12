<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\GmDebugService;
use App\Services\PlayerProfileSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PlayerProfilesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = '玩家档案';

    protected static ?string $title = '玩家档案';

    protected static ?int $navigationSort = 5;

    protected static string | \UnitEnum | null $navigationGroup = '玩家与运营';

    protected string $view = 'filament.pages.player-profiles-page';

    public ?array $data = [];

    public array $profileSnapshot = [];

    public string $lastAuditedPlayerId = '';

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

        $latest = app(PlayerProfileSyncService::class)->recentProfiles(1);
        $targetPlayerId = (string) ($latest[0]['player_id'] ?? '');

        $this->profileSnapshot = app(PlayerProfileSyncService::class)->loadArchive($targetPlayerId);
        $this->auditViewedProfile($targetPlayerId);
        $this->form->fill([
            'target_player_id' => $targetPlayerId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('搜索玩家')
                    ->description('后台浏览的是客户端关键节点上报的最近一份玩家档案快照，不是实时在线内存视图。')
                    ->schema([
                        Select::make('target_player_id')
                            ->label('玩家')
                            ->searchable()
                            ->placeholder('输入玩家 ID 或昵称搜索')
                            ->getSearchResultsUsing(fn (string $search): array => app(PlayerProfileSyncService::class)->searchProfileOptions($search))
                            ->getOptionLabelUsing(fn (mixed $value): string => app(PlayerProfileSyncService::class)->optionLabel((string) $value))
                            ->live()
                            ->afterStateUpdated(fn (mixed $state): bool => $this->openProfile((string) $state)),
                    ]),
                Section::make('基础信息')
                    ->schema([
                        Placeholder::make('player_id')
                            ->label('玩家 ID')
                            ->content(fn (): string => $this->valueOrDash('player_id')),
                        Placeholder::make('nickname')
                            ->label('昵称')
                            ->content(fn (): string => $this->valueOrDash('nickname')),
                        Placeholder::make('updated_at')
                            ->label('最近同步时间')
                            ->content(fn (): string => $this->valueOrDash('updated_at')),
                        Placeholder::make('profile_exists')
                            ->label('档案状态')
                            ->content(fn (): string => (bool) ($this->profileSnapshot['exists'] ?? false) ? '已同步' : '尚无服务端快照'),
                    ])
                    ->columns(4),
                Section::make('货币')
                    ->schema([
                        Placeholder::make('gold')
                            ->label('金币')
                            ->content(fn (): string => (string) ((int) ($this->profileSnapshot['gold'] ?? 0))),
                        Placeholder::make('crystal')
                            ->label('晶石')
                            ->content(fn (): string => (string) ((int) ($this->profileSnapshot['crystal'] ?? 0))),
                        Placeholder::make('contribution')
                            ->label('宗门贡献')
                            ->content(fn (): string => (string) ((int) ($this->profileSnapshot['contribution'] ?? 0))),
                    ])
                    ->columns(3),
                Section::make('角色成长')
                    ->schema([
                        Placeholder::make('level_exp')
                            ->label('等级 / 经验')
                            ->content(fn (): string => sprintf(
                                'Lv%d / EXP %d',
                                (int) ($this->profileSnapshot['level'] ?? 0),
                                (int) ($this->profileSnapshot['exp'] ?? 0),
                            )),
                        Placeholder::make('points')
                            ->label('点数')
                            ->content(fn (): string => sprintf(
                                '自由属性点 %d / 技能点 %d',
                                (int) ($this->profileSnapshot['free_attr_points'] ?? 0),
                                (int) ($this->profileSnapshot['skill_points'] ?? 0),
                            )),
                        Placeholder::make('sect_id')
                            ->label('当前宗门')
                            ->content(fn (): string => $this->valueOrDash('current_sect_id')),
                        Placeholder::make('attrs_summary')
                            ->label('六维属性')
                            ->content(fn (): string => $this->valueOrDash('attrs_summary'))
                            ->columnSpanFull(),
                        Placeholder::make('claimed_milestones')
                            ->label('成长里程碑')
                            ->content(fn (): string => sprintf(
                                '已领取 %d 项',
                                (int) ($this->profileSnapshot['claimed_milestone_count'] ?? 0),
                            )),
                    ])
                    ->columns(3),
                Section::make('主线进度')
                    ->schema([
                        Placeholder::make('current_stage')
                            ->label('当前选择主线')
                            ->content(fn (): string => $this->stageLine(
                                'current_stage_name',
                                'current_difficulty_name',
                            )),
                        Placeholder::make('highest_stage')
                            ->label('最高通关主线')
                            ->content(fn (): string => $this->stageLine(
                                'highest_cleared_stage_name',
                                'highest_cleared_difficulty_name',
                            )),
                    ])
                    ->columns(2),
                Section::make('自动巡查')
                    ->schema([
                        Placeholder::make('patrol_summary')
                            ->label('巡查状态')
                            ->content(fn (): HtmlString => $this->linesHtml($this->patrolLines(), '暂无自动巡查快照')),
                    ]),
                Section::make('宗门任务摘要')
                    ->schema([
                        Placeholder::make('task_summary')
                            ->label('任务状态')
                            ->content(fn (): HtmlString => $this->linesHtml($this->taskLines(), '暂无宗门任务快照')),
                    ]),
                Section::make('背包摘要')
                    ->schema([
                        Placeholder::make('inventory_totals')
                            ->label('概览')
                            ->content(fn (): string => sprintf(
                                '%d 种物品 / 共 %d 个',
                                (int) ($this->profileSnapshot['inventory_kind_count'] ?? 0),
                                (int) ($this->profileSnapshot['inventory_total_count'] ?? 0),
                            )),
                        Placeholder::make('inventory_preview')
                            ->label('背包预览')
                            ->content(fn (): HtmlString => $this->linesHtml(
                                $this->profileSnapshot['inventory_preview_lines'] ?? [],
                                '背包为空',
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('装备摘要')
                    ->schema([
                        Placeholder::make('equipment_totals')
                            ->label('概览')
                            ->content(fn (): string => sprintf(
                                '已装备 %d 件 / 背包装备 %d 件',
                                (int) ($this->profileSnapshot['equipped_count'] ?? 0),
                                (int) ($this->profileSnapshot['bag_equipment_count'] ?? 0),
                            )),
                        Placeholder::make('equipment_preview')
                            ->label('装备预览')
                            ->content(fn (): HtmlString => $this->linesHtml(
                                $this->profileSnapshot['equipment_preview_lines'] ?? [],
                                '暂无装备快照',
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function openProfile(string $playerId): bool
    {
        $safePlayerId = trim($playerId);
        $this->data['target_player_id'] = $safePlayerId;
        $this->profileSnapshot = app(PlayerProfileSyncService::class)->loadArchive($safePlayerId);
        $this->auditViewedProfile($safePlayerId);

        return true;
    }

    public function recentProfiles(): array
    {
        return app(PlayerProfileSyncService::class)->recentProfiles();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_profile')
                ->label('刷新当前档案')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $this->openProfile((string) ($this->data['target_player_id'] ?? ''));
                }),
        ];
    }

    private function valueOrDash(string $key): string
    {
        $value = trim((string) ($this->profileSnapshot[$key] ?? ''));

        return $value !== '' ? $value : '—';
    }

    private function stageLine(string $stageKey, string $difficultyKey): string
    {
        $stageName = trim((string) ($this->profileSnapshot[$stageKey] ?? ''));
        $difficultyName = trim((string) ($this->profileSnapshot[$difficultyKey] ?? ''));

        if ($stageName === '') {
            return '—';
        }

        return $difficultyName !== '' && $difficultyName !== '—'
            ? sprintf('%s｜%s', $stageName, $difficultyName)
            : $stageName;
    }

    private function patrolLines(): array
    {
        $summary = $this->profileSnapshot['patrol_summary'] ?? [];
        if (! is_array($summary) || $summary === []) {
            return [];
        }

        $lines = [];
        $status = trim((string) ($summary['status_text'] ?? ''));
        $route = trim((string) ($summary['route_name'] ?? ''));
        $seconds = (int) ($summary['accumulated_seconds'] ?? 0);

        if ($status !== '') {
            $lines[] = '状态：' . $status;
        }
        if ($route !== '') {
            $lines[] = '巡查路线：' . $route;
        }
        if ($seconds > 0) {
            $lines[] = '已累计：' . gmdate('H:i:s', $seconds);
        }
        if (! empty($summary['has_rare_drop'])) {
            $lines[] = '含低概率稀有掉落';
        }

        return $lines;
    }

    private function taskLines(): array
    {
        $summary = $this->profileSnapshot['task_summary'] ?? [];
        if (! is_array($summary) || $summary === []) {
            return [];
        }

        return [
            sprintf(
                '日常宗务：%d/%d 已开放',
                (int) ($summary['daily_unlocked'] ?? 0),
                (int) ($summary['daily_total'] ?? 0),
            ),
            sprintf(
                '历程宗务：%d/%d 已开放',
                (int) ($summary['milestone_unlocked'] ?? 0),
                (int) ($summary['milestone_total'] ?? 0),
            ),
            sprintf('当前可领取：%d 项', (int) ($summary['claimable'] ?? 0)),
        ];
    }

    private function linesHtml(mixed $lines, string $emptyText): HtmlString
    {
        if (! is_array($lines) || $lines === []) {
            return new HtmlString('<div class="whitespace-pre-wrap text-sm text-gray-500 dark:text-gray-400">' . e($emptyText) . '</div>');
        }

        $html = collect($lines)
            ->map(fn (mixed $line): string => '<div>' . e((string) $line) . '</div>')
            ->implode('');

        return new HtmlString('<div class="whitespace-pre-wrap text-sm leading-6">' . $html . '</div>');
    }

    private function auditViewedProfile(string $playerId): void
    {
        $safePlayerId = trim($playerId);
        if ($safePlayerId === '' || $safePlayerId === $this->lastAuditedPlayerId) {
            return;
        }

        $admin = auth()->user();

        app(AdminAuditService::class)->log(
            $admin instanceof User ? $admin : null,
            'view_player_profile',
            'player',
            $safePlayerId,
            sprintf('浏览玩家档案：%s', $safePlayerId),
            [
                'player_id' => $safePlayerId,
                'nickname' => (string) ($this->profileSnapshot['nickname'] ?? ''),
                'level' => (int) ($this->profileSnapshot['level'] ?? 0),
                'current_stage_id' => (string) ($this->profileSnapshot['current_stage_id'] ?? ''),
            ],
        );

        $this->lastAuditedPlayerId = $safePlayerId;
    }
}
