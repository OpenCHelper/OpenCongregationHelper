<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local', ['serve' => true, 'url' => '/private-storage']);
});

test('a profile photo is served by the application without the public/storage link', function () {
    $user = User::factory()->create();
    $user->updateProfilePhoto(UploadedFile::fake()->image('photo.jpg'));

    $path = $user->fresh()->profile_photo_path;

    $this->get('/storage/'.$path)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect(parse_url($user->fresh()->profile_photo_url, PHP_URL_PATH))->toBe('/storage/'.$path);
});

test('missing, hidden and outside files of the public disk are not found', function (string $path) {
    Storage::disk('public')->put('.gitignore', '*');
    Storage::disk('local')->put('secret.txt', 'secret');

    $this->get('/storage/'.$path)->assertNotFound();
})->with([
    'missing file' => 'profile-photos/missing.jpg',
    'hidden file' => '.gitignore',
    'path traversal' => '../private/secret.txt',
    'encoded path traversal' => '..%2Fprivate%2Fsecret.txt',
]);

test('the private files are still served through their signed URLs only', function () {
    Storage::disk('local')->put('maps/map.png', 'image');

    $this->get(Storage::disk('local')->temporaryUrl('maps/map.png', now()->addMinute()))
        ->assertOk();

    $this->get('/private-storage/maps/map.png')->assertForbidden();
});
