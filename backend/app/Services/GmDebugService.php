<?php

namespace App\Services;

use App\Models\BlueGearTemplate;
use App\Models\EquipTemplate;
use App\Models\GmOperationLog;
use App\Models\Item;
use App\Models\PlayerMilestone;
use App\Models\ShopPlayerProfile;
use App\Models\ShopPurchaseLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GmDebugService
{
    public static function isEnabled(): bool
    {
        return (bool) config('gm.enabled', false);
    }

    public static function canAccess(?User $user): bool
    {
        if (! static::isEnabled() || ! $user instanceof User) {
            return false;
        }

        $email = Str::lower(trim((string) $user->email));
        $allowed = collect(config('gm.allowed_emails', []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter()
            ->all();

        return in_array($email, $allowed, true);
    }

    public function loadSnapshot(string $playerId): array
    {
        $safePlayerId = trim($playerId);
        if ($safePlayerId === '') {
            return $this->emptySnapshot();
        }

        $profile = ShopPlayerProfile::query()
            ->where('player_id', $safePlayerId)
            ->first();

        return $profile instanceof ShopPlayerProfile
            ? $this->snapshotFromProfile($profile)
            : array_merge($this->emptySnapshot(), ['player_id' => $safePlayerId]);
    }

    public function setLevel(User $operator, string $playerId, int $level): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'set_level',
            ['level' => $level],
            function (ShopPlayerProfile $profile) use ($level): array {
                $profile->level = max(1, $level);
                $profile->save();

                return ['message' => sprintf('等级已设置为 %d。', (int) $profile->level)];
            },
        );
    }

    public function addExp(User $operator, string $playerId, int $amount): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'add_exp',
            ['amount' => $amount],
            function (ShopPlayerProfile $profile) use ($amount): array {
                if ($amount <= 0) {
                    throw new RuntimeException('经验增量必须大于 0。');
                }

                $profile->exp = max(0, (int) $profile->exp + $amount);
                $profile->save();

                return ['message' => sprintf('已增加经验 %d。', $amount)];
            },
        );
    }

    public function setCurrency(User $operator, string $playerId, string $currencyType, int $amount): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'set_currency',
            ['currency_type' => $currencyType, 'amount' => $amount],
            function (ShopPlayerProfile $profile) use ($currencyType, $amount): array {
                $safeCurrencyType = trim($currencyType);
                if (! in_array($safeCurrencyType, ['gold', 'crystal', 'contribution'], true)) {
                    throw new RuntimeException('不支持的货币类型。');
                }

                $safeAmount = max(0, $amount);

                match ($safeCurrencyType) {
                    'gold' => $profile->gold = $safeAmount,
                    'crystal' => $profile->crystal = $safeAmount,
                    'contribution' => $profile->contribution = $safeAmount,
                };

                $profile->save();

                $currencyName = match ($safeCurrencyType) {
                    'gold' => '金币',
                    'crystal' => '晶石',
                    'contribution' => '贡献',
                };

                return ['message' => sprintf('%s已设置为 %d。', $currencyName, $safeAmount)];
            },
        );
    }

    public function grantItem(User $operator, string $playerId, string $itemId, int $count): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'grant_item',
            ['item_id' => $itemId, 'count' => $count],
            function (ShopPlayerProfile $profile) use ($itemId, $count): array {
                $safeItemId = trim($itemId);
                $safeCount = max(0, $count);

                if ($safeItemId === '' || $safeCount <= 0) {
                    throw new RuntimeException('物品和数量不能为空。');
                }

                $item = Item::query()->where('item_id', $safeItemId)->first();
                if (! $item instanceof Item) {
                    throw new RuntimeException('目标物品不存在。');
                }

                $inventory = is_array($profile->inventory) ? $profile->inventory : [];
                $inventory[$safeItemId] = max(0, (int) ($inventory[$safeItemId] ?? 0)) + $safeCount;
                ksort($inventory);

                $profile->inventory = $inventory;
                $profile->save();

                return ['message' => sprintf('已发放物品：%s × %d。', (string) $item->display_name, $safeCount)];
            },
        );
    }

    public function grantEquipment(User $operator, string $playerId, string $templateId, int $count): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'grant_equipment',
            ['template_id' => $templateId, 'count' => $count],
            function (ShopPlayerProfile $profile) use ($templateId, $count): array {
                $safeTemplateId = trim($templateId);
                $safeCount = max(0, $count);

                if ($safeTemplateId === '' || $safeCount <= 0) {
                    throw new RuntimeException('装备模板和数量不能为空。');
                }

                $templatePayload = $this->resolveEquipmentTemplate($safeTemplateId);
                if ($templatePayload === null) {
                    throw new RuntimeException('目标装备模板不存在。');
                }

                $equipment = is_array($profile->equipment) ? array_values($profile->equipment) : [];

                for ($index = 0; $index < $safeCount; $index++) {
                    $equipment[] = [
                        'equipment_id' => (string) Str::uuid(),
                        'template_id' => $templatePayload['template_id'],
                        'template_name' => $templatePayload['template_name'],
                        'template_type' => $templatePayload['template_type'],
                        'rarity' => $templatePayload['rarity'],
                        'required_level' => $templatePayload['required_level'],
                        'source' => 'gm',
                        'granted_at' => now()->toDateTimeString(),
                    ];
                }

                $profile->equipment = $equipment;
                $profile->save();

                return ['message' => sprintf('已发放装备：%s × %d。', $templatePayload['template_name'], $safeCount)];
            },
        );
    }

    public function setPoints(User $operator, string $playerId, int $freeAttrPoints, int $skillPoints): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'set_points',
            [
                'free_attr_points' => $freeAttrPoints,
                'skill_points' => $skillPoints,
            ],
            function (ShopPlayerProfile $profile) use ($freeAttrPoints, $skillPoints): array {
                $profile->free_attr_points = max(0, $freeAttrPoints);
                $profile->skill_points = max(0, $skillPoints);
                $profile->save();

                return ['message' => '属性点与技能点已更新。'];
            },
        );
    }

    public function resetMilestones(User $operator, string $playerId): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'reset_milestones',
            [],
            function (ShopPlayerProfile $profile): array {
                PlayerMilestone::query()
                    ->where('player_id', (string) $profile->player_id)
                    ->delete();
                $profile->claimed_milestones = [];
                $profile->save();

                return ['message' => '成长里程碑领取状态已重置。'];
            },
        );
    }

    public function resetShopLimits(User $operator, string $playerId): array
    {
        return $this->runLoggedOperation(
            $operator,
            $playerId,
            'reset_shop_limits',
            [],
            function (ShopPlayerProfile $profile): array {
                ShopPurchaseLog::query()
                    ->where('player_id', (string) $profile->player_id)
                    ->delete();

                return ['message' => '商城限购记录已重置。'];
            },
        );
    }

    private function runLoggedOperation(
        User $operator,
        string $playerId,
        string $actionType,
        array $payload,
        callable $operation,
    ): array {
        $safePlayerId = trim($playerId);
        $before = $this->snapshotForLogs($safePlayerId);
        $after = $before;
        $status = 'success';
        $statusMessage = null;

        try {
            if (! static::canAccess($operator)) {
                throw new RuntimeException('当前管理员无权执行 GM 调试操作。');
            }

            if ($safePlayerId === '') {
                throw new RuntimeException('目标玩家 ID 不能为空。');
            }

            $result = DB::transaction(function () use ($safePlayerId, $operation): array {
                $profile = $this->lockOrCreateProfile($safePlayerId);

                return $operation($profile);
            });

            $after = $this->snapshotForLogs($safePlayerId);
            $statusMessage = (string) ($result['message'] ?? '操作成功');

            $this->writeLog(
                $operator,
                $safePlayerId,
                $actionType,
                $payload,
                $before,
                $after,
                $status,
                $statusMessage,
            );

            return [
                'ok' => true,
                'message' => $statusMessage,
                'snapshot' => $after,
            ];
        } catch (Throwable $exception) {
            $status = 'failed';
            $statusMessage = $exception->getMessage();
            $after = $this->snapshotForLogs($safePlayerId);

            $this->writeLog(
                $operator,
                $safePlayerId,
                $actionType,
                $payload,
                $before,
                $after,
                $status,
                $statusMessage,
            );

            report($exception);

            return [
                'ok' => false,
                'message' => $statusMessage,
                'snapshot' => $after ?? $before,
            ];
        }
    }

    private function lockOrCreateProfile(string $playerId): ShopPlayerProfile
    {
        $profile = ShopPlayerProfile::query()
            ->where('player_id', $playerId)
            ->lockForUpdate()
            ->first();

        if ($profile instanceof ShopPlayerProfile) {
            return $profile;
        }

        $defaults = $this->defaultProfilePayload($playerId);

        ShopPlayerProfile::query()->create($defaults);

        $created = ShopPlayerProfile::query()
            ->where('player_id', $playerId)
            ->lockForUpdate()
            ->first();

        if (! $created instanceof ShopPlayerProfile) {
            throw new RuntimeException('创建调试档案失败。');
        }

        return $created;
    }

    private function defaultProfilePayload(string $playerId): array
    {
        $growthConfig = CharacterGrowthRulesService::loadConfig();
        $initial = is_array($growthConfig['initial'] ?? null) ? $growthConfig['initial'] : [];

        return [
            'player_id' => $playerId,
            'level' => max(1, (int) ($initial['level'] ?? 1)),
            'exp' => 0,
            'gold' => 0,
            'crystal' => 0,
            'contribution' => 0,
            'free_attr_points' => max(0, (int) ($initial['free_attr_points'] ?? 0)),
            'skill_points' => max(0, (int) ($initial['skill_points'] ?? 0)),
            'inventory' => [],
            'equipment' => [],
            'claimed_milestones' => [],
        ];
    }

    private function resolveEquipmentTemplate(string $templateId): ?array
    {
        $equipTemplate = EquipTemplate::query()->where('id', $templateId)->first();
        if ($equipTemplate instanceof EquipTemplate) {
            return [
                'template_id' => (string) $equipTemplate->id,
                'template_name' => (string) $equipTemplate->name,
                'template_type' => 'equip_template',
                'rarity' => (string) ($equipTemplate->rarity ?? 'white'),
                'required_level' => max(1, (int) ($equipTemplate->required_level ?? 1)),
            ];
        }

        $blueTemplate = BlueGearTemplate::query()->where('template_id', $templateId)->first();
        if ($blueTemplate instanceof BlueGearTemplate) {
            return [
                'template_id' => (string) $blueTemplate->template_id,
                'template_name' => (string) $blueTemplate->name,
                'template_type' => 'blue_gear_template',
                'rarity' => 'blue',
                'required_level' => max(1, (int) ($blueTemplate->required_level ?? 1)),
            ];
        }

        return null;
    }

    private function snapshotForLogs(string $playerId): ?array
    {
        if ($playerId === '') {
            return null;
        }

        $profile = ShopPlayerProfile::query()
            ->where('player_id', $playerId)
            ->first();

        return $profile instanceof ShopPlayerProfile ? $this->snapshotFromProfile($profile) : null;
    }

    private function snapshotFromProfile(ShopPlayerProfile $profile): array
    {
        $inventory = is_array($profile->inventory) ? $profile->inventory : [];
        $equipment = is_array($profile->equipment) ? array_values($profile->equipment) : [];
        $claimedMilestones = is_array($profile->claimed_milestones) ? array_values($profile->claimed_milestones) : [];

        return [
            'exists' => true,
            'player_id' => (string) $profile->player_id,
            'level' => max(1, (int) $profile->level),
            'exp' => max(0, (int) $profile->exp),
            'gold' => max(0, (int) $profile->gold),
            'crystal' => max(0, (int) $profile->crystal),
            'contribution' => max(0, (int) $profile->contribution),
            'free_attr_points' => max(0, (int) $profile->free_attr_points),
            'skill_points' => max(0, (int) $profile->skill_points),
            'inventory' => $inventory,
            'inventory_kind_count' => count($inventory),
            'inventory_total_count' => array_sum(array_map('intval', $inventory)),
            'equipment' => $equipment,
            'equipment_count' => count($equipment),
            'claimed_milestones' => $claimedMilestones,
            'claimed_milestone_count' => count($claimedMilestones),
        ];
    }

    private function emptySnapshot(): array
    {
        return [
            'exists' => false,
            'player_id' => '',
            'level' => 0,
            'exp' => 0,
            'gold' => 0,
            'crystal' => 0,
            'contribution' => 0,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'inventory' => [],
            'inventory_kind_count' => 0,
            'inventory_total_count' => 0,
            'equipment' => [],
            'equipment_count' => 0,
            'claimed_milestones' => [],
            'claimed_milestone_count' => 0,
        ];
    }

    private function writeLog(
        User $operator,
        string $playerId,
        string $actionType,
        array $payload,
        ?array $before,
        ?array $after,
        string $status,
        ?string $statusMessage,
    ): void {
        GmOperationLog::query()->create([
            'operator_admin_id' => $operator->getKey(),
            'target_player_id' => $playerId,
            'action_type' => $actionType,
            'action_payload' => $payload,
            'result_snapshot_before' => $before,
            'result_snapshot_after' => $after,
            'status' => $status,
            'status_message' => $statusMessage,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);

        app(AdminAuditService::class)->log(
            $operator,
            $actionType,
            'player',
            $playerId,
            $statusMessage ?? 'GM 操作',
            [
                'action_payload' => $payload,
                'status' => $status,
                'before' => $before,
                'after' => $after,
            ],
            $status,
            $statusMessage,
        );
    }
}
