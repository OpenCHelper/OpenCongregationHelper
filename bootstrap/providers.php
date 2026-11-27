<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\GuestPanelProvider;
use App\Providers\Filament\KnowledgeBasePanelProvider;
use App\Providers\Filament\SetupPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\JetstreamServiceProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    GuestPanelProvider::class,
    KnowledgeBasePanelProvider::class,
    SetupPanelProvider::class,
    FortifyServiceProvider::class,
    JetstreamServiceProvider::class,
];
