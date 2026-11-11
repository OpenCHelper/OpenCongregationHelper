<?php

use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use Livewire\Livewire;

test('the language chosen by the user also applies to Livewire requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(SwitchLanguageLocale::class);
});
