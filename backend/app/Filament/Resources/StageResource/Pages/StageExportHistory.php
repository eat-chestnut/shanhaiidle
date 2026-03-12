<?php

namespace App\Filament\Resources\StageResource\Pages;

use App\Filament\Resources\StageResource;
use Filament\Resources\Pages\Page;

class StageExportHistory extends Page
{
    protected static string $resource = StageResource::class;

    protected string $view = 'filament.resources.stage-resource.pages.stage-export-history';
}
