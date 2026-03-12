<?php

namespace App\Filament\Resources\AdminAuditLogResource\Pages;

use App\Filament\Resources\AdminAuditLogResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminAuditLog extends ViewRecord
{
    protected static string $resource = AdminAuditLogResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $payload = $data['payload_json'] ?? [];
        $data['created_at_display'] = $this->record->created_at?->format('Y-m-d H:i:s') ?? '—';
        $data['admin_user_display'] = (string) ($this->record->adminUser?->email ?? '—');
        $data['action_type_display'] = AdminAuditService::actionTypeOptions()[$this->record->action_type] ?? (string) $this->record->action_type;
        $data['target_type_display'] = AdminAuditService::targetTypeOptions()[$this->record->target_type] ?? (string) $this->record->target_type;
        $data['status_display'] = $this->record->status === 'success' ? '成功' : '失败';
        $data['payload_pretty'] = is_array($payload)
            ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '{}';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
