<?php

use App\Enums\CampaignStatus;
use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Enums\TagType;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Poster;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\SharedUrl;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DocsDemoSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Tags\Tag;

beforeEach(function () {
    // `och:install` creates the keys of the AI assistants when they are missing
    usePassportTestKeys();
    Notification::fake();
    Storage::fake('local');

    config(['app.initial_root_user' => [
        'name' => 'Daniel Hughes',
        'email' => 'admin@example.com',
        'password' => 'Demo-password-2026!',
    ]]);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DocsDemoSeeder::class);
});

test('the users of the documentation are seeded with their roles', function () {
    $users = User::with('roles')->orderBy('id')->get();

    expect($users->map(fn (User $user): string => $user->full_name)->all())->toBe([
        'Daniel Hughes', 'Emily Parker', 'James Collins', 'Olivia Bennett', 'Grace Mitchell',
        'Thomas Reed', 'Hannah Brooks', 'Samuel Price', 'Lucy Ward', 'Oliver Hayes',
    ])
        ->and($users->first()->email)->toBe('admin@example.com')
        ->and($users->first()->hasRole('super_admin'))->toBeTrue()
        ->and($users->first()->hasVerifiedEmail())->toBeTrue()
        ->and(User::where('email', 'emily.parker@example.com')->sole()->hasRole('territory_servant'))->toBeTrue()
        ->and(User::where('email', 'grace.mitchell@example.com')->sole()->hasRole('publisher'))->toBeTrue()
        ->and(User::where('can_login', true)->count())->toBe(5)
        ->and(User::where('can_login', false)->with('roles')->get()->every(fn (User $user): bool => $user->roles->isEmpty() && ! $user->hasVerifiedEmail()))->toBeTrue()
        ->and(User::find(1)->contacts()->where('is_primary', true)->sole()->name)->toBe('Sarah Hughes');
});

test('the zones, territories and tags of the documentation are seeded', function () {
    expect(TerritoryZone::withCount('territories')->pluck('territories_count', 'name')->all())
        ->toBe(['Old Town' => 6, 'Outskirts' => 8, 'Village' => 7, 'Hillside' => 6])
        ->and(Territory::count())->toBe(27)
        ->and(Territory::where('is_commercial', true)->count())->toBe(3)
        ->and(Territory::where('name', 'OT-01')->sole()->description)->toBe('Maple Street, Chestnut Street')
        ->and(Territory::where('name', 'OT-02')->sole()->image_path)->toBe('territories/ot-02.png')
        ->and(Tag::getWithType(TagType::ZONES->value)->pluck('name')->all())->toEqualCanonicalizing(['North', 'South'])
        ->and(Tag::getWithType(TagType::TERRITORIES->value)->pluck('name')->all())
        ->toEqualCanonicalizing(['Apartment blocks', 'Detached houses', 'Intercom only', 'Shops'])
        ->and(Territory::withAnyTags(['Apartment blocks'], TagType::TERRITORIES->value)->count())->toBe(13)
        ->and(Territory::withAnyTags(['Detached houses'], TagType::TERRITORIES->value)->count())->toBe(10);

    Storage::disk('local')->assertExists('territories/ot-02.png');
});

test('the campaigns of the documentation have the statuses shown in the screenshots', function () {
    $statuses = Campaign::all()->mapWithKeys(fn (Campaign $campaign): array => [$campaign->short_name => $campaign->getStatus()]);

    expect($statuses->all())->toBe([
        'EST' => CampaignStatus::TERMINATED,
        'LET' => CampaignStatus::ACTIVE,
        'MEM' => CampaignStatus::ALMOST_EXPIRED,
        'ASS' => CampaignStatus::ACTIVE,
    ])
        ->and(Campaign::where('short_name', 'MEM')->sole()->assignments()->count())->toBe(9);
});

test('the assignments of the documentation are seeded', function () {
    $ot01 = TerritoryAssignment::whereRelation('territory', 'name', 'OT-01')->whereNull('ended_at')->sole();

    expect($ot01->assignee->full_name)->toBe('Daniel Hughes')
        ->and($ot01->started_at->toDateString())->toBe(today()->subDays(3)->toDateString())
        ->and($ot01->campaign->name)->toBe('Regional convention invitations')
        ->and(SharedUrl::sole()->uid)->toBe(DocsDemoSeeder::SHARED_ASSIGNMENT_UID)
        ->and(TerritoryAssignment::whereNull('ended_at')->count())->toBe(15)
        ->and(TerritoryAssignment::where('assignee_id', 1)->count())->toBe(9)
        ->and(TerritoryAssignment::whereRelation('territory', 'name', 'OS-02')->count())->toBe(3)
        ->and(User::find(1)->unreadNotifications()->count())->toBe(3)
        ->and(User::find(1)->notifications()->count())->toBe(6)
        ->and(User::find(1)->notifications()->latest()->first()->data['title'])->toBe('Territory out for 66 days');
});

test('the departments, publications and posters of the documentation are seeded', function () {
    $territories = Department::for(DepartmentKey::Territories);

    expect($territories->members()->sole()->full_name)->toBe('Emily Parker')
        ->and($territories->members()->sole()->pivot->role)->toBe(DepartmentRole::Overseer)
        ->and(PublicationSubscriber::count())->toBe(count(DocsDemoSeeder::SUBSCRIPTIONS))
        ->and(PublicationRequest::whereNull('subscription_id')->count())->toBe(count(DocsDemoSeeder::REQUESTS))
        ->and(Poster::count())->toBe(8);
});

test('seeding the demo data twice does nothing the second time', function () {
    $this->seed(DocsDemoSeeder::class);

    expect(User::count())->toBe(10)
        ->and(Territory::count())->toBe(27);
});
