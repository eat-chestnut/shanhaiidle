<?php

namespace App\Services\Game\Equipment;

class TalismanStarLinkResolver
{
    private const PARTICIPATING_SLOT_TYPES = [
        'main_weapon',
        'sub_weapon',
        'armor',
        'leg',
        'shoe',
        'cloak',
        'helmet',
        'necklace',
        'bracelet_1',
        'bracelet_2',
        'ring_1',
        'ring_2',
    ];

    public function resolve(
        array $loadouts,
        array $equipmentInstances,
        ?array $talismanInstance,
        array $talismanStarLinks
    ): array {
        $emptyData = [
            'qualified_star_links' => [],
            'missing_requirements' => [],
        ];

        if (! is_array($talismanInstance) || $talismanInstance === []) {
            return $this->failure('talisman_instance_missing', $emptyData);
        }

        $talismanSlotType = trim((string) ($talismanInstance['slot_type'] ?? 'talisman'));
        if ($talismanSlotType !== '' && $talismanSlotType !== 'talisman') {
            return $this->failure('invalid_talisman_instance', $emptyData);
        }

        $starLinks = $this->filterLinksForCurrentTalisman($talismanStarLinks, $talismanInstance);
        if ($starLinks === []) {
            return $this->failure('talisman_star_links_not_found', $emptyData);
        }

        $trackedStars = $this->trackedStarsBySlot($loadouts, $equipmentInstances);

        $qualifiedStarLinks = [];
        $missingRequirements = [];

        foreach ($starLinks as $starLink) {
            $requiredEquipmentStar = (int) ($starLink['required_equipment_star'] ?? 0);
            if ($requiredEquipmentStar < 1) {
                continue;
            }

            $qualified = true;
            foreach (self::PARTICIPATING_SLOT_TYPES as $slotType) {
                $slotStar = (int) ($trackedStars[$slotType] ?? 0);
                if ($slotStar < $requiredEquipmentStar) {
                    $qualified = false;
                    break;
                }
            }

            if ($qualified) {
                $qualifiedStarLinks[] = [
                    'required_equipment_star' => $requiredEquipmentStar,
                    'effect_key' => trim((string) ($starLink['effect_key'] ?? '')),
                    'value_type' => trim((string) ($starLink['value_type'] ?? '')),
                    'value' => $this->normalizeNumeric($starLink['value'] ?? 0),
                ];

                continue;
            }

            $missingRequirements[] = [
                'required_equipment_star' => $requiredEquipmentStar,
                'reason' => sprintf(
                    'one or more participating slots have stars below %d',
                    $requiredEquipmentStar
                ),
            ];
        }

        return $this->success([
            'qualified_star_links' => $qualifiedStarLinks,
            'missing_requirements' => $missingRequirements,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function trackedStarsBySlot(array $loadouts, array $equipmentInstances): array
    {
        $instancesById = [];
        foreach ($equipmentInstances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            $instanceId = trim((string) ($instance['instance_id'] ?? ''));
            if ($instanceId === '') {
                continue;
            }

            $instancesById[$instanceId] = $instance;
        }

        $starsBySlot = [];
        foreach ($loadouts as $loadout) {
            if (! is_array($loadout)) {
                continue;
            }

            $slotType = trim((string) ($loadout['slot_type'] ?? ''));
            if (! in_array($slotType, self::PARTICIPATING_SLOT_TYPES, true)) {
                continue;
            }

            $instanceId = trim((string) ($loadout['instance_id'] ?? ''));
            if ($instanceId === '' || ! isset($instancesById[$instanceId])) {
                continue;
            }

            $instance = $instancesById[$instanceId];
            $instanceSlotType = trim((string) ($instance['slot_type'] ?? $slotType));
            if (! in_array($instanceSlotType, self::PARTICIPATING_SLOT_TYPES, true)) {
                continue;
            }

            $starsBySlot[$slotType] = (int) ($instance['star'] ?? 0);
        }

        return $starsBySlot;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filterLinksForCurrentTalisman(array $starLinks, array $talismanInstance): array
    {
        $rows = [];
        foreach ($starLinks as $starLink) {
            if (! is_array($starLink)) {
                continue;
            }

            $rows[] = $starLink;
        }

        $talismanId = trim((string) ($talismanInstance['talisman_id'] ?? ''));
        if ($talismanId !== '') {
            $sameTalismanRows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => trim((string) ($row['talisman_id'] ?? '')) === $talismanId
            ));

            if ($sameTalismanRows !== []) {
                $rows = $sameTalismanRows;
            } else {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $row): bool => ! array_key_exists('talisman_id', $row)
                ));
            }
        }

        if (! array_key_exists('tier_no', $talismanInstance) || $talismanInstance['tier_no'] === null) {
            return $rows;
        }

        $tierNo = (int) $talismanInstance['tier_no'];

        $sameTierRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => array_key_exists('tier_no', $row) && (int) $row['tier_no'] === $tierNo
        ));

        return $sameTierRows !== [] ? $sameTierRows : $rows;
    }

    private function normalizeNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            return 0;
        }

        $numeric = (float) $value;

        return floor($numeric) === $numeric ? (int) $numeric : $numeric;
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason, ?array $data = null): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => $data,
        ];
    }
}
