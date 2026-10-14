<?php

declare(strict_types=1);

use App\Modules\Jobs\JobsServiceProvider;
use App\Modules\Providers\ProvidersServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    SharedServiceProvider::class,
    JobsServiceProvider::class,
    ProvidersServiceProvider::class,
];
