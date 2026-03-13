<?php

namespace App\Services\Game\Equipment;

use App\Models\BlueEquipmentTemplate;
use App\Models\BossCore;
use App\Models\EquipTemplate;
use App\Models\EquipmentSetEffect;
use App\Models\Gem;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use App\Models\Talisman;
use App\Models\TalismanStarLink;
use App\Models\TalismanTier;

class PlayerEquipmentStatAggregator
{
    public function __construct(
        private readonly BaseEquipmentStatAggregator $baseEquipmentStatAggregator = new BaseEquipmentStatAggregator(),
        private readonly EquipmentBonusAggregator $equipmentBonusAggregator = new EquipmentBonusAggregator(),
        private readonly EquipmentSpecialEffectAggregator $equipmentSpecialEffectAggregator = new EquipmentSpecialEffectAggregator(),
        private readonly EquipmentSetEffectResolver $equipmentSetEffectResolver = new EquipmentSetEffectResolver(),
        private readonly TalismanStarLinkResolver $talismanStarLinkResolver = new TalismanStarLinkResolver(),
    ) {
    }

    public function aggregate(int|string $playerId): array
    {
        $loadouts = PlayerEquipmentLoadout::query()
            ->where('player_id', $playerId)
            ->orderBy('slot_type')
            ->orderBy('id')
            ->get()
            ->map(fn (PlayerEquipmentLoadout $loadout): array => $loadout->toArray())
            ->all();

        $instanceIds = array_values(array_filter(array_map(
            static fn (mixed $loadout): string => is_array($loadout) ? trim((string) ($loadout['instance_id'] ?? '')) : '',
            $loadouts
        )));

        $equipmentInstances = PlayerEquipmentInstance::query()
            ->where('player_id', $playerId)
            ->when(
                $instanceIds !== [],
                static fn ($query) => $query->whereIn('instance_id', $instanceIds),
                static fn ($query) => $query->whereRaw('1 = 0')
            )
            ->orderBy('slot_type')
            ->orderBy('id')
            ->get()
            ->map(fn (PlayerEquipmentInstance $instance): array => $instance->toArray())
            ->all();

        $equipmentConfigs = $this->loadEquipmentConfigsForInstances($equipmentInstances);
        $blueTemplates = $this->loadBlueTemplatesForInstances($equipmentInstances);

        $baseResult = $this->baseEquipmentStatAggregator->aggregate(
            $playerId,
            $loadouts,
            $equipmentInstances,
            $equipmentConfigs,
            $blueTemplates
        );
        if (! ($baseResult['ok'] ?? false)) {
            return $this->failure((string) ($baseResult['reason'] ?? 'base_equipment_stat_aggregation_failed'));
        }

        $setEffectResult = $this->resolveSetEffects($loadouts, $equipmentInstances);
        $talismanContext = $this->resolveTalismanContext($loadouts, $equipmentInstances);
        $gemEffects = $this->loadGemEffectsForInstances($instanceIds);
        $bossCoreContext = $this->loadBossCoreEffectsForInstances($equipmentInstances);
        $blueAffixContext = $this->loadBlueAffixEffectsForInstances($equipmentInstances);

        $bonusResult = $this->equipmentBonusAggregator->aggregate(
            $playerId,
            $setEffectResult,
            $talismanContext['link_result'],
            $talismanContext['base_bonus_effects'],
            $gemEffects,
            $bossCoreContext['bonus_effects'],
            $blueAffixContext['bonus_effects']
        );
        if (! ($bonusResult['ok'] ?? false)) {
            return $this->failure((string) ($bonusResult['reason'] ?? 'equipment_bonus_aggregation_failed'));
        }

        $specialResult = $this->equipmentSpecialEffectAggregator->aggregate(
            $playerId,
            $talismanContext['special_effects'],
            $bossCoreContext['special_effects'],
            [],
            $blueAffixContext['special_effects']
        );
        if (! ($specialResult['ok'] ?? false)) {
            return $this->failure((string) ($specialResult['reason'] ?? 'equipment_special_effect_aggregation_failed'));
        }

        return $this->success([
            'base_stats' => $baseResult['data'] ?? [],
            'bonus_stats' => $bonusResult['data'] ?? [],
            'special_effects' => $specialResult['data'] ?? [],
            'sources' => $this->buildSources(
                $equipmentInstances,
                $setEffectResult,
                $talismanContext,
                $gemEffects,
                $bossCoreContext,
                $blueAffixContext
            ),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array<int, array<string, mixed>>
     */
    private function loadEquipmentConfigsForInstances(array $equipmentInstances): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $instance): string => is_array($instance) ? trim((string) ($instance['item_id'] ?? '')) : '',
            $equipmentInstances
        ))));

        if ($itemIds === []) {
            return [];
        }

        return EquipTemplate::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->map(static fn (EquipTemplate $template): array => [
                'item_id' => (string) $template->id,
                'white_stats' => is_array($template->white_stats) ? $template->white_stats : [],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array<int, array<string, mixed>>
     */
    private function loadBlueTemplatesForInstances(array $equipmentInstances): array
    {
        $blueItemIds = array_values(array_unique(array_filter(array_map(
            static function (mixed $instance): string {
                if (! is_array($instance)) {
                    return '';
                }

                return trim((string) (($instance['equipment_source_type'] ?? '') === 'blue_equipment'
                    ? ($instance['item_id'] ?? '')
                    : ''));
            },
            $equipmentInstances
        ))));

        if ($blueItemIds === []) {
            return [];
        }

        return BlueEquipmentTemplate::query()
            ->with('baseStats')
            ->whereIn('result_item_id', $blueItemIds)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (BlueEquipmentTemplate $template): array => [
                'result_item_id' => (string) $template->result_item_id,
                'base_stats' => $template->baseStats
                    ->filter(static fn ($baseStat): bool => (bool) $baseStat->is_enabled)
                    ->map(static fn ($baseStat): array => [
                        'stat_key' => (string) $baseStat->stat_key,
                        'value_type' => (string) $baseStat->value_type,
                        'value' => $baseStat->value,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $loadouts
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array<string, mixed>
     */
    private function resolveSetEffects(array $loadouts, array $equipmentInstances): array
    {
        $setIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $instance): string => is_array($instance) ? trim((string) ($instance['set_id'] ?? '')) : '',
            $equipmentInstances
        ))));

        $setEffects = EquipmentSetEffect::query()
            ->whereIn('set_id', $setIds === [] ? ['__none__'] : $setIds)
            ->where('is_enabled', true)
            ->orderBy('set_id')
            ->orderBy('piece_count')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EquipmentSetEffect $effect): array => $effect->toArray())
            ->all();

        return $this->equipmentSetEffectResolver->resolve($loadouts, $equipmentInstances, $setEffects);
    }

    /**
     * @param  array<int, array<string, mixed>>  $loadouts
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array<string, mixed>
     */
    private function resolveTalismanContext(array $loadouts, array $equipmentInstances): array
    {
        $empty = [
            'link_result' => [
                'ok' => true,
                'reason' => null,
                'data' => ['qualified_star_links' => [], 'missing_requirements' => []],
            ],
            'base_bonus_effects' => [],
            'special_effects' => [],
        ];

        $talismanInstance = null;
        foreach ($equipmentInstances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            if (trim((string) ($instance['slot_type'] ?? '')) !== 'talisman') {
                continue;
            }

            $talismanInstance = $instance;
            break;
        }

        if ($talismanInstance === null) {
            return $empty;
        }

        $talisman = Talisman::query()
            ->where('item_id', (string) ($talismanInstance['item_id'] ?? ''))
            ->where('is_enabled', true)
            ->first();

        if ($talisman === null) {
            return $empty;
        }

        $talismanInstance['talisman_id'] = (string) $talisman->talisman_id;

        $tierNo = array_key_exists('tier_no', $talismanInstance) ? (int) ($talismanInstance['tier_no'] ?? 0) : 0;
        if ($tierNo < 1) {
            return $empty;
        }

        $talismanInstance['tier_no'] = $tierNo;

        $tiers = TalismanTier::query()
            ->where('talisman_id', $talisman->talisman_id)
            ->where('tier_no', $tierNo)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $starLinks = TalismanStarLink::query()
            ->where('talisman_id', $talisman->talisman_id)
            ->where('tier_no', $tierNo)
            ->where('is_enabled', true)
            ->orderBy('required_equipment_star')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TalismanStarLink $starLink): array => $starLink->toArray())
            ->all();

        $linkResult = $this->talismanStarLinkResolver->resolve($loadouts, $equipmentInstances, $talismanInstance, $starLinks);
        if (! ($linkResult['ok'] ?? false)) {
            $linkResult = $empty['link_result'];
        }

        $baseBonusEffects = [];
        $specialEffects = [];

        foreach ($tiers as $tier) {
            $source = sprintf('%s_tier_%d', (string) $talisman->talisman_id, (int) $tier->tier_no);
            if ((string) $tier->trigger_rule === 'passive_always') {
                $baseBonusEffects[] = [
                    'effect_key' => (string) $tier->effect_key,
                    'value_type' => (string) $tier->value_type,
                    'value' => $tier->value,
                    'source' => $source,
                ];

                continue;
            }

            $specialEffects[] = [
                'effect_key' => (string) $tier->effect_key,
                'source' => $source,
            ];
        }

        return [
            'link_result' => $linkResult,
            'base_bonus_effects' => $baseBonusEffects,
            'special_effects' => $specialEffects,
        ];
    }

    /**
     * @param  array<int, string>  $instanceIds
     * @return array<int, array<string, mixed>>
     */
    private function loadGemEffectsForInstances(array $instanceIds): array
    {
        if ($instanceIds === []) {
            return [];
        }

        $gemItemIds = PlayerEquipmentGemSlot::query()
            ->whereIn('instance_id', $instanceIds)
            ->where('is_unlocked', true)
            ->whereNotNull('gem_item_id')
            ->pluck('gem_item_id')
            ->filter()
            ->map(static fn (mixed $itemId): string => trim((string) $itemId))
            ->unique()
            ->values()
            ->all();

        if ($gemItemIds === []) {
            return [];
        }

        return Gem::query()
            ->whereIn('item_id', $gemItemIds)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Gem $gem): array => [
                'effect_key' => $this->mapGemToEffectKey($gem),
                'value_type' => (string) $gem->value_type,
                'value' => $gem->value,
                'source' => (string) $gem->item_id,
            ])
            ->filter(static fn (array $effect): bool => $effect['effect_key'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array{bonus_effects: array<int, array<string, mixed>>, special_effects: array<int, array<string, mixed>>}
     */
    private function loadBossCoreEffectsForInstances(array $equipmentInstances): array
    {
        $coreIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $instance): string => is_array($instance) ? trim((string) ($instance['boss_core_id'] ?? '')) : '',
            $equipmentInstances
        ))));

        if ($coreIds === []) {
            return [
                'bonus_effects' => [],
                'special_effects' => [],
            ];
        }

        $bonusEffects = [];
        $specialEffects = [];
        $bossCores = BossCore::query()
            ->with('effects')
            ->whereIn('core_id', $coreIds)
            ->where('is_enabled', true)
            ->get();

        foreach ($bossCores as $bossCore) {
            foreach ($bossCore->effects->where('is_enabled', true) as $effect) {
                $source = (string) $bossCore->core_id;
                if ($effect->value_type !== null && $effect->value !== null) {
                    $bonusEffects[] = [
                        'effect_key' => (string) $effect->effect_key,
                        'value_type' => (string) $effect->value_type,
                        'value' => $effect->value,
                        'source' => $source,
                    ];

                    continue;
                }

                $specialEffects[] = [
                    'effect_key' => (string) $effect->effect_key,
                    'source' => $source,
                ];
            }
        }

        return [
            'bonus_effects' => $bonusEffects,
            'special_effects' => $specialEffects,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @return array{bonus_effects: array<int, array<string, mixed>>, special_effects: array<int, array<string, mixed>>}
     */
    private function loadBlueAffixEffectsForInstances(array $equipmentInstances): array
    {
        unset($equipmentInstances);

        return [
            'bonus_effects' => [],
            'special_effects' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @param  array<string, mixed>  $setEffectResult
     * @param  array<string, mixed>  $talismanContext
     * @param  array<int, array<string, mixed>>  $gemEffects
     * @param  array{bonus_effects: array<int, array<string, mixed>>, special_effects: array<int, array<string, mixed>>}  $bossCoreContext
     * @param  array{bonus_effects: array<int, array<string, mixed>>, special_effects: array<int, array<string, mixed>>}  $blueAffixContext
     * @return array<int, array<string, string>>
     */
    private function buildSources(
        array $equipmentInstances,
        array $setEffectResult,
        array $talismanContext,
        array $gemEffects,
        array $bossCoreContext,
        array $blueAffixContext
    ): array {
        $sources = [];

        foreach ($equipmentInstances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            $instanceId = trim((string) ($instance['instance_id'] ?? ''));
            if ($instanceId === '') {
                continue;
            }

            $this->pushSource($sources, 'equipment', $instanceId);
        }

        foreach (($setEffectResult['data']['activated_effects'] ?? []) as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $setId = trim((string) ($effect['set_id'] ?? ''));
            $pieceCount = (int) ($effect['piece_count'] ?? 0);
            $source = trim((string) ($effect['source'] ?? ($setId !== '' && $pieceCount > 0
                ? sprintf('%s_%dpc', $setId, $pieceCount)
                : '')));
            $this->pushSource($sources, 'set_effect', $source);
        }

        foreach ($talismanContext['base_bonus_effects'] as $effect) {
            $this->pushSource($sources, 'talisman_base', (string) ($effect['source'] ?? ''));
        }

        foreach (($talismanContext['link_result']['data']['qualified_star_links'] ?? []) as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $requiredEquipmentStar = (int) ($effect['required_equipment_star'] ?? 0);
            $source = trim((string) ($effect['source'] ?? ($requiredEquipmentStar > 0
                ? sprintf('talisman_star_link_%d', $requiredEquipmentStar)
                : '')));
            $this->pushSource($sources, 'talisman_star_link', $source);
        }

        foreach ($talismanContext['special_effects'] as $effect) {
            $this->pushSource($sources, 'talisman_base', (string) ($effect['source'] ?? ''));
        }

        foreach ($gemEffects as $effect) {
            $this->pushSource($sources, 'gem', (string) ($effect['source'] ?? ''));
        }

        foreach (['bonus_effects', 'special_effects'] as $bucket) {
            foreach ($bossCoreContext[$bucket] as $effect) {
                $this->pushSource($sources, 'boss_core', (string) ($effect['source'] ?? ''));
            }

            foreach ($blueAffixContext[$bucket] as $effect) {
                $this->pushSource($sources, 'blue_affix', (string) ($effect['source'] ?? ''));
            }
        }

        return array_values($sources);
    }

    /**
     * @param  array<string, array{type: string, source: string}>  $sources
     */
    private function pushSource(array &$sources, string $type, string $source): void
    {
        $safeType = trim($type);
        $safeSource = trim($source);
        if ($safeType === '' || $safeSource === '') {
            return;
        }

        $sources[$safeType.':'.$safeSource] = [
            'type' => $safeType,
            'source' => $safeSource,
        ];
    }

    private function mapGemToEffectKey(Gem $gem): string
    {
        if ((string) $gem->gem_type === 'skill_gem') {
            return 'bonus_skill_dmg';
        }

        $statKey = strtolower(trim((string) $gem->stat_key));

        return $statKey === '' ? '' : 'bonus_'.$statKey;
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ];
    }
}
