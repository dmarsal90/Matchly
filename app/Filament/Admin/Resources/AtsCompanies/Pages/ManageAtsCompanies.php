<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AtsCompanies\Pages;

use App\Filament\Admin\Resources\AtsCompanies\AtsCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageAtsCompanies extends ManageRecords
{
    protected static string $resource = AtsCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
