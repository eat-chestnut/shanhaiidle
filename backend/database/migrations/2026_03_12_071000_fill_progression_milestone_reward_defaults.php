<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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

        $changed = false;
        foreach ($milestones as $index => $milestone) {
            if (! is_array($milestone)) {
                continue;
            }

            $rewardItemId = trim((string) ($milestone['reward_item_id'] ?? ''));
            if ($rewardItemId !== '') {
                continue;
            }

            $level = max(1, (int) ($milestone['level'] ?? 0));
            $defaultReward = match ($level) {
                1 => 'milestone_pack_lv1',
                5 => 'milestone_pack_lv5',
                10 => 'milestone_pack_lv10',
                15 => 'milestone_pack_lv15',
                20 => 'milestone_pack_lv20',
                default => '',
            };

            if ($defaultReward === '') {
                continue;
            }

            $milestones[$index]['reward_item_id'] = $defaultReward;
            $milestones[$index]['reward_count'] = max(1, (int) ($milestone['reward_count'] ?? 1));
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $decoded['milestones'] = $milestones;

        DB::table('app_settings')
            ->where('key', 'progression_milestones')
            ->update(['value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function down(): void
    {
        // No-op.
    }
};
