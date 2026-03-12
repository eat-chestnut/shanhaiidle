<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function log(
        ?User $adminUser,
        string $actionType,
        string $targetType,
        ?string $targetId,
        string $summary,
        array $payload = [],
        string $status = 'success',
        ?string $statusMessage = null,
        ?Request $request = null,
    ): AdminAuditLog {
        $safeRequest = $request ?? request();

        return AdminAuditLog::query()->create([
            'admin_user_id' => $adminUser?->getKey(),
            'action_type' => trim($actionType) !== '' ? trim($actionType) : 'unknown',
            'target_type' => trim($targetType) !== '' ? trim($targetType) : 'unknown',
            'target_id' => ($targetId !== null && trim($targetId) !== '') ? trim($targetId) : null,
            'status' => trim($status) !== '' ? trim($status) : 'success',
            'summary' => mb_substr(trim($summary) !== '' ? trim($summary) : '未提供摘要', 0, 255),
            'status_message' => $statusMessage,
            'payload_json' => $payload !== [] ? $payload : null,
            'ip_address' => $safeRequest?->ip(),
            'user_agent' => $safeRequest?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function logForCurrentAdmin(
        string $actionType,
        string $targetType,
        ?string $targetId,
        string $summary,
        array $payload = [],
        string $status = 'success',
        ?string $statusMessage = null,
    ): AdminAuditLog {
        $admin = auth()->user();

        return $this->log(
            $admin instanceof User ? $admin : null,
            $actionType,
            $targetType,
            $targetId,
            $summary,
            $payload,
            $status,
            $statusMessage,
        );
    }

    public static function actionTypeOptions(): array
    {
        return [
            'view_player_profile' => '浏览玩家档案',
            'set_level' => '设置等级',
            'add_exp' => '增加经验',
            'set_currency' => '设置货币',
            'set_points' => '设置属性点/技能点',
            'grant_item' => '发放物品',
            'grant_equipment' => '发放装备',
            'reset_milestones' => '重置里程碑奖励',
            'reset_shop_limits' => '重置商城限购',
        ];
    }

    public static function targetTypeOptions(): array
    {
        return [
            'player' => '玩家',
            'shop_goods' => '商城商品',
            'system' => '系统',
        ];
    }
}
