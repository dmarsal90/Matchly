<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IngestionRuns\Pages;

use App\Filament\Admin\Resources\IngestionRuns\IngestionRunResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageIngestionRuns extends ManageRecords
{
    protected static string $resource = IngestionRunResource::class;
}
