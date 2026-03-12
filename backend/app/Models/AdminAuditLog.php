<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_user_id',
        'action_type',
        'target_type',
        'target_id',
        'status',
        'summary',
        'status_message',
        'payload_json',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'admin_user_id' => 'integer',
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
