<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;

test('the sidebar can be collapsed on desktop', function () {
    expect(Filament::getPanel('app')->isSidebarCollapsibleOnDesktop())->toBeTrue();

    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]))
        ->get(Dashboard::getUrl(panel: 'app'))
        ->assertOk()
        ->assertSee('fi-topbar-collapse-sidebar-btn-ctn', escape: false);
});
