<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sources\Pages;

use App\Filament\Admin\Resources\Sources\SourceResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageSources extends ManageRecords
{
    protected static string $resource = SourceResource::class;
}
