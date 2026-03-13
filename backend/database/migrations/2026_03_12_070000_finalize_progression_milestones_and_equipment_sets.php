<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->normalizeProgressionMilestones();
            $this->backfillEquipmentSetPieceCount();
        });
    }

    public function down(): void
    {
        // Finalization only; no rollback for legacy shape restoration.
    }

    private function normalizeProgressionMilestones(): void
    {
        $row = DB::table('app_settings')->where('key', 'progression_milestones')->first();
        if ($row === null) {
            return;
        }

        $decoded = json_decode((string) ($row->value ?? ''), true);
        if (! is_array($decoded)) {
            return;
        }

        $milestones = $decoded['milestones'] ?? null;
        if (! is_array($milestones)) {
            return;
        }

        $normalized = [];
        foreach ($milestones as $index => $milestone) {
            if (! is_array($milestone)) {
                continue;
            }

            $level = max(1, (int) ($milestone['level'] ?? 0));
            $rewardItemId = trim((string) ($milestone['reward_item_id'] ?? ''));
            $rewardCount = max(1, (int) ($milestone['reward_count'] ?? 1));

            if ($rewardItemId === '') {
                $legacyRewards = $milestone['rewards'] ?? [];
                if (is_array($legacyRewards)) {
                    foreach ($legacyRewards as $reward) {
                        if (! is_array($reward)) {
                            continue;
                        }
                        $candidateId = trim((string) ($reward['reward_item_id'] ?? $reward['item_id'] ?? ''));
                        if ($candidateId === '') {
                            continue;
                        }
                        $rewardItemId = $candidateId;
                        $rewardCount = max(1, (int) ($reward['reward_count'] ?? $reward['count'] ?? 1));
                        break;
                    }
                }
            }

            $unlockContents = $milestone['unlock_contents'] ?? null;
            if (! is_array($unlockContents) || $unlockContents === []) {
                $unlockContents = [];

                $mainStageUnlock = (int) ($milestone['main_stage_unlock'] ?? 0);
                if ($mainStageUnlock >= 1) {
                    $unlockContents[] = [
                        'type' => 'main_stage',
                        'content' => sprintf('开放主线第%d关', $mainStageUnlock),
                    ];
                }

                foreach ((array) ($milestone['daily_dungeon_unlocks'] ?? []) as $value) {
                    $label = match (trim((string) $value)) {
                        'daily_gold' => '开放金币副本',
                        'daily_exp' => '开放经验副本',
                        'daily_material' => '开放材料副本',
                        'daily_gem' => '开放宝石副本',
                        default => '',
                    };
                    if ($label !== '') {
                        $unlockContents[] = ['type' => 'daily_dungeon', 'content' => $label];
                    }
                }

                $blueGearStage = (int) ($milestone['blue_gear_stage'] ?? 0);
                if ($blueGearStage > 0) {
                    $affixCount = max(1, (int) ($milestone['blue_affix_count'] ?? 1));
                    $unlockContents[] = [
                        'type' => 'blue_gear',
                        'content' => $affixCount >= 2
                            ? sprintf('蓝装进入%d级双词条阶段', $blueGearStage)
                            : sprintf('蓝装进入%d级档', $blueGearStage),
                    ];
                }

                foreach ((array) ($milestone['system_unlocks'] ?? []) as $value) {
                    $label = match (trim((string) $value)) {
                        'main_story' => '开放主线巡山',
                        'equipment' => '开放工坊',
                        'sect_tasks' => '开放宗门任务',
                        'daily_gold' => '开放金币副本',
                        'daily_exp' => '开放经验副本',
                        'daily_material' => '开放材料副本',
                        'daily_gem' => '开放宝石副本',
                        default => '',
                    };
                    if ($label !== '') {
                        $unlockContents[] = ['type' => 'feature_unlock', 'content' => $label];
                    }
                }
            }

            $normalized[] = [
                'level' => $level,
                'milestone_key' => trim((string) ($milestone['milestone_key'] ?? $milestone['stage_key'] ?? ('lv' . $level))),
                'title' => trim((string) ($milestone['title'] ?? '未命名里程碑')),
                'summary' => trim((string) ($milestone['summary'] ?? '')),
                'image' => trim((string) ($milestone['image'] ?? '')),
                'unlock_contents' => array_values(array_filter($unlockContents, fn ($row): bool => is_array($row))),
                'reward_item_id' => $rewardItemId,
                'reward_count' => $rewardCount,
                'claim_once' => (bool) ($milestone['claim_once'] ?? true),
                'is_enabled' => (bool) ($milestone['is_enabled'] ?? true),
                'sort' => max(0, (int) ($milestone['sort'] ?? (($index + 1) * 10))),
            ];
        }

        usort($normalized, fn (array $a, array $b): int => ($a['level'] <=> $b['level']) ?: ($a['sort'] <=> $b['sort']));

        $decoded['milestones'] = array_values($normalized);

        DB::table('app_settings')
            ->where('key', 'progression_milestones')
            ->update(['value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private function backfillEquipmentSetPieceCount(): void
    {
        DB::table('equipment_sets')
            ->where(function ($query): void {
                $query->whereNull('piece_count')->orWhere('piece_count', '<=', 0);
            })
            ->whereNotNull('max_pieces')
            ->update([
                'piece_count' => DB::raw('max_pieces'),
            ]);
    }
};
