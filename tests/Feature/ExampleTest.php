<?php

beforeEach(function () {
    // Render the real assets when they are built (as in CI), so that entries missing
    // from the Vite build are caught, and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
