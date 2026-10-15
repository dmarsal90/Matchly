<?php

declare(strict_types=1);

return [
    App\Modules\AI\AiServiceProvider::class,
    App\Modules\Alerts\AlertsServiceProvider::class,
    App\Modules\Applications\ApplicationsServiceProvider::class,
    App\Modules\Identity\FortifyServiceProvider::class,
    App\Modules\Identity\IdentityServiceProvider::class,
    App\Modules\Jobs\JobsServiceProvider::class,
    App\Modules\Matching\MatchingServiceProvider::class,
    App\Modules\Profiles\ProfilesServiceProvider::class,
    App\Modules\Providers\ProvidersServiceProvider::class,
    App\Modules\Shared\SharedServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
