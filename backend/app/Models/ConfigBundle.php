<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigBundle extends Model
{
    protected $table = 'config_bundles';

    protected $fillable = [
        'bundle_id',
        'manifest_path',
        'sha256',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

