<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageExport extends Model
{
    protected $table = 'stage_exports';

    const UPDATED_AT = null;

    protected $fillable = [
        'version',
        'file_path',
        'sha256',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'created_at' => 'datetime',
    ];
}
