<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\JobListings\Pages;

use App\Filament\Admin\Resources\JobListings\JobListingResource;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ManageJobListings extends ManageRecords
{
    protected static string $resource = JobListingResource::class;

    public function getTabs(): array
    {
        return [
            // Filament types the builder as Builder<Model>; constrain the underlying query.
            'open' => Tab::make()->modifyQueryUsing(function (Builder $query): Builder {
                $query->getQuery()->where('status', JobStatus::Open->value)->whereNull('duplicate_of_id');

                return $query;
            }),
            'flagged' => Tab::make('Needs review')->modifyQueryUsing(function (Builder $query): Builder {
                $query->getQuery()->where('status', JobStatus::Flagged->value);

                return $query;
            }),
            'all' => Tab::make(),
        ];
    }
}
