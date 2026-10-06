<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\PartnerPanelProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PartnerPanelProvider::class,
    MailConfigServiceProvider::class,
];
