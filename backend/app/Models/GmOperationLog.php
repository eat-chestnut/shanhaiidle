<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmOperationLog extends Model
{
    public $timestamps = false;

    protected $table = 'gm_operation_logs';

    protected $fillable = [
        'operator_admin_id',
        'target_player_id',
        'action_type',
        'action_payload',
        'result_snapshot_before',
        'result_snapshot_after',
        'status',
        'status_message',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'operator_admin_id' => 'integer',
        'action_payload' => 'array',
        'result_snapshot_before' => 'array',
        'result_snapshot_after' => 'array',
        'created_at' => 'datetime',
    ];
}
