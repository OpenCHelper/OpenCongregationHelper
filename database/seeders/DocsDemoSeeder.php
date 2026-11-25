<?php

namespace Database\Seeders;

use App\Enums\ContactType;
use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Enums\PublicationCategory;
use App\Enums\TagType;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\CampaignCreatedNotification;
use App\Notifications\StaleAssignmentNotification;
use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;
use BezhanSalleh\FilamentShield\Support\Utils;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the demo data shown in the screenshots of the user documentation (`docs/user-documentation/{en,it}`), so that
 * they can be taken again with the same data: an English-speaking congregation in Brooklyn, whose root user is
 * Daniel Hughes.
 *
 * It runs on top of a fresh installation (`php artisan migrate:fresh --seed`, which creates the root user from the
 * `INITIAL_ROOT_*` variables). Nothing is random: the dates are relative to the day of seeding, so that the pages show
 * the same durations ("3 days", "Ended 4 days ago"...) whenever the screenshots are taken.
 */
class DocsDemoSeeder extends Seeder
{
    use Localizable;
    use WithoutModelEvents;

    /**
     * The password of the demo users who can log in.
     */
    public const PASSWORD = 'password';

    /**
     * The demo users besides the root user, with their role: the ones without a role are virtual (they cannot log in,
     * but can still receive territories).
     *
     * @var list<array{first_name: string, last_name: string, role: ?string}>
     */
    public const USERS = [
        ['first_name' => 'Emily', 'last_name' => 'Parker', 'role' => 'territory_servant'],
        ['first_name' => 'James', 'last_name' => 'Collins', 'role' => 'publisher'],
        ['first_name' => 'Olivia', 'last_name' => 'Bennett', 'role' => 'publisher'],
        ['first_name' => 'Grace', 'last_name' => 'Mitchell', 'role' => 'publisher'],
        ['first_name' => 'Thomas', 'last_name' => 'Reed', 'role' => null],
        ['first_name' => 'Hannah', 'last_name' => 'Brooks', 'role' => null],
        ['first_name' => 'Samuel', 'last_name' => 'Price', 'role' => null],
        ['first_name' => 'Lucy', 'last_name' => 'Ward', 'role' => null],
        ['first_name' => 'Oliver', 'last_name' => 'Hayes', 'role' => null],
    ];

    /**
     * The contacts of the users, by full name.
     *
     * @var array<string, list<array{type: ContactType, name: string, phone_number: string, is_primary: bool}>>
     */
    public const CONTACTS = [
        'Daniel Hughes' => [
            ['type' => ContactType::EMERGENCY, 'name' => 'Sarah Hughes', 'phone_number' => '+1 718 555 0142', 'is_primary' => true],
            ['type' => ContactType::PERSONAL, 'name' => 'Emma Hughes', 'phone_number' => '+1 718 555 0187', 'is_primary' => false],
        ],
        'Emily Parker' => [
            ['type' => ContactType::EMERGENCY, 'name' => 'Mark Parker', 'phone_number' => '+1 718 555 0123', 'is_primary' => true],
        ],
    ];

    /**
     * The members of the departments, by full name: the first one is the overseer, the others are assistants. Only
     * brothers serve in the departments; James Collins is left out, so that his menu (shown while impersonating him)
     * only has the territories and zones of a publisher.
     *
     * @var array<string, list<string>>
     */
    public const DEPARTMENTS = [
        DepartmentKey::Territories->value => ['Daniel Hughes', 'Samuel Price'],
        DepartmentKey::PublicWitnessing->value => ['Thomas Reed', 'Oliver Hayes'],
        DepartmentKey::Publications->value => ['Samuel Price', 'Thomas Reed'],
    ];

    /**
     * The tags, by type.
     *
     * @var array<string, list<string>>
     */
    public const TAGS = [
        TagType::ZONES->value => ['North', 'South'],
        TagType::TERRITORIES->value => ['Apartment blocks', 'Detached houses', 'Intercom only', 'Shops'],
    ];

    /**
     * The size of a territory block, in degrees of latitude and longitude (about 450 x 420 meters).
     */
    private const BLOCK_LATITUDE_SIZE = 0.004;

    private const BLOCK_LONGITUDE_SIZE = 0.005;

    /**
     * The number of territories in each row of the grid of a zone.
     */
    private const GRID_COLUMNS = 3;

    /**
     * The zones, with the code of their territories, and the territories: their description (streets), tags, and
     * optionally notes, whether they are commercial, and whether they have an image (a scanned card).
     *
     * @var list<array{name: string, code: string, color: string, center: array{float, float}, tag: string, territories: list<array{description: string, tags: list<string>, notes?: string, is_commercial?: bool, with_image?: bool}>}>
     */
    public const ZONES = [
        [
            'name' => 'Old Town',
            'code' => 'OT',
            'color' => '#dc2626',
            'center' => [40.6440, -73.9765],
            'tag' => 'North',
            'territories' => [
                ['description' => 'Maple Street, Chestnut Street', 'tags' => ['Detached houses'], 'notes' => 'Many buildings with intercom: better in the afternoon.'],
                ['description' => 'Linden Street, Church Lane', 'tags' => ['Apartment blocks', 'Intercom only'], 'with_image' => true],
                ['description' => 'Market Street, Bell Street', 'tags' => ['Apartment blocks']],
                ['description' => 'Orchard Road, Mill Lane', 'tags' => ['Detached houses']],
                ['description' => 'Castle Street, Tower Court', 'tags' => ['Apartment blocks']],
                ['description' => 'High Street (shops)', 'tags' => ['Intercom only', 'Shops'], 'notes' => 'Best on weekday mornings, avoid Saturdays.', 'is_commercial' => true],
            ],
        ],
        [
            'name' => 'Outskirts',
            'code' => 'OS',
            'color' => '#ea580c',
            'center' => [40.6300, -73.9800],
            'tag' => 'South',
            'territories' => [
                ['description' => 'Willow Road, Fern Close', 'tags' => ['Detached houses']],
                ['description' => 'Railway Avenue, Station Road', 'tags' => ['Apartment blocks']],
                ['description' => 'Factory Lane, Canal Street', 'tags' => ['Apartment blocks']],
                ['description' => 'Meadow Drive, Brook Lane', 'tags' => ['Detached houses']],
                ['description' => 'Harbor View, Dock Street', 'tags' => ['Apartment blocks', 'Intercom only'], 'notes' => 'Ring the concierge at number 12 first.'],
                ['description' => 'Pine Avenue, Cedar Close', 'tags' => ['Detached houses']],
                ['description' => 'Bridge Road, River Walk', 'tags' => ['Apartment blocks']],
                ['description' => 'Farm Lane, Hollow Road', 'tags' => ['Detached houses'], 'notes' => 'Long distances between houses: a car helps.'],
            ],
        ],
        [
            'name' => 'Village',
            'code' => 'VI',
            'color' => '#16a34a',
            'center' => [40.6170, -73.9590],
            'tag' => 'North',
            'territories' => [
                ['description' => 'Green Street, School Lane', 'tags' => ['Apartment blocks']],
                ['description' => 'Rose Avenue, Ivy Court', 'tags' => ['Detached houses', 'Intercom only']],
                ['description' => 'Park Road, Garden Row', 'tags' => ['Apartment blocks']],
                ['description' => 'Elm Street, Oak Lane', 'tags' => ['Detached houses']],
                ['description' => 'Well Street, Pond Lane', 'tags' => ['Apartment blocks']],
                ['description' => 'Church Road, Vicarage Close', 'tags' => []],
                ['description' => 'Village Square (shops)', 'tags' => ['Shops'], 'is_commercial' => true],
            ],
        ],
        [
            'name' => 'Hillside',
            'code' => 'HS',
            'color' => '#2563eb',
            'center' => [40.6460, -73.9390],
            'tag' => 'South',
            'territories' => [
                ['description' => 'Hill Street, Summit Avenue', 'tags' => ['Apartment blocks']],
                ['description' => 'Valley Road, Ridge Lane', 'tags' => ['Detached houses', 'Intercom only']],
                ['description' => 'Primrose Hill, Heather Way', 'tags' => ['Apartment blocks']],
                ['description' => 'Hillside Parade (shops)', 'tags' => ['Shops'], 'is_commercial' => true],
                ['description' => 'Windmill Lane, Lookout Road', 'tags' => ['Detached houses']],
                ['description' => 'Daffodil Lane, Maple Street', 'tags' => ['Apartment blocks', 'Intercom only']],
            ],
        ],
    ];

    /**
     * The campaigns, by short name: the days since they started and until they end, relative to the day of seeding,
     * and the days since they were created (and their users notified).
     *
     * @var array<string, array{name: string, description: string, color: string, from: int, until: int, created: int}>
     */
    public const CAMPAIGNS = [
        'EST' => [
            'name' => 'Summer special campaign',
            'description' => 'Distribution of the special issue of the magazine to every home of the territory.',
            'color' => '#f59e0b',
            'from' => -200,
            'until' => -140,
            'created' => 202,
        ],
        'LET' => [
            'name' => 'Letter writing',
            'description' => 'Letters to the residents of gated buildings and of the homes never found at home.',
            'color' => '#0891b2',
            'from' => -60,
            'until' => 120,
            'created' => 62,
        ],
        'MEM' => [
            'name' => 'Memorial invitation campaign',
            'description' => 'Invite everyone to the Memorial of Jesus\' death.',
            'color' => '#7c3aed',
            'from' => -25,
            'until' => 2,
            'created' => 25,
        ],
        'ASS' => [
            'name' => 'Regional convention invitations',
            'description' => 'Invitations to the regional convention, in the weeks before it starts.',
            'color' => '#059669',
            'from' => -7,
            'until' => 45,
            'created' => 7,
        ],
    ];

    /**
     * The assignments: territory, assignee (full name), days since they started and since they ended (null while in
     * progress), campaign (short name) and notes. The ones in progress started at 10:15, the returned ones at 16:00 and
     * ended at 18:30.
     *
     * @var list<array{0: string, 1: string, 2: int, 3: ?int, 4: ?string, 5?: string}>
     */
    public const ASSIGNMENTS = [
        // In progress
        ['OT-01', 'Daniel Hughes', 3, null, 'ASS', 'Please also leave the invitation in the mailboxes.'],
        ['OT-02', 'James Collins', 12, null, 'MEM'],
        ['OT-04', 'Grace Mitchell', 40, null, null],
        ['OT-06', 'Hannah Brooks', 95, null, null],
        ['OS-02', 'Lucy Ward', 128, null, null],
        ['OS-03', 'Oliver Hayes', 155, null, null],
        ['OS-05', 'Emily Parker', 3, null, 'ASS'],
        ['OS-06', 'James Collins', 12, null, 'MEM'],
        ['OS-07', 'Olivia Bennett', 21, null, 'MEM'],
        ['VI-01', 'Thomas Reed', 30, null, 'LET'],
        ['VI-03', 'Samuel Price', 8, null, 'ASS'],
        ['HS-01', 'Emily Parker', 3, null, 'ASS'],
        ['HS-02', 'Daniel Hughes', 66, null, null],
        ['HS-04', 'Grace Mitchell', 40, null, null],
        ['HS-06', 'Hannah Brooks', 95, null, null],
        // Returned during the Memorial invitation campaign
        ['OT-03', 'Olivia Bennett', 18, 4, 'MEM', 'Worked the odd numbers, the even ones are still to do.'],
        ['OS-01', 'Emily Parker', 20, 4, 'MEM'],
        ['HS-03', 'Grace Mitchell', 16, 4, 'MEM'],
        ['VI-05', 'Daniel Hughes', 22, 7, 'MEM'],
        ['VI-02', 'Emily Parker', 24, 10, 'MEM'],
        ['OS-04', 'Daniel Hughes', 23, 9, 'MEM', 'Nobody at home in the morning: better to go back in the afternoon.'],
        // Returned earlier
        ['OT-01', 'Daniel Hughes', 64, 39, null],
        ['OT-01', 'Emily Parker', 140, 109, null],
        ['OT-04', 'Emily Parker', 100, 70, null],
        ['OS-02', 'Daniel Hughes', 207, 172, null],
        ['OS-02', 'Samuel Price', 302, 278, null],
        ['OT-05', 'Thomas Reed', 190, 160, 'EST'],
        ['VI-04', 'Daniel Hughes', 180, 150, 'EST'],
        ['VI-06', 'Emily Parker', 175, 145, 'EST'],
        ['HS-05', 'Samuel Price', 290, 261, null],
        ['OS-08', 'Daniel Hughes', 250, 220, null],
        ['VI-07', 'Daniel Hughes', 330, 300, null],
    ];

    /**
     * The identifier of the public link of the assignment of OT-01 in progress.
     */
    public const SHARED_ASSIGNMENT_UID = '4f887aad-734f-49d7-a0e5-6e4fbd45bfdf';

    /**
     * The periodicals received by the publishers (full name): the copies of each publication (code), and since how
     * many months.
     *
     * @var array<string, array{0: array<string, int>, 1: int}>
     */
    public const SUBSCRIPTIONS = [
        'James Collins' => [['w' => 1, 'mwb' => 1], 11],
        'Olivia Bennett' => [['w' => 2, 'mwb' => 2], 9],
        'Grace Mitchell' => [['wlp' => 1], 7],
        'Thomas Reed' => [['w' => 1], 5],
        'Hannah Brooks' => [['mwb' => 1], 4],
        'Lucy Ward' => [['w' => 1, 'mwb' => 1], 3],
        'Emily Parker' => [['mwb' => 1], 1],
    ];

    /**
     * The one-off requests: publisher (full name), publication (code), copies, months ago, and whether they were
     * ordered and delivered.
     *
     * @var list<array{0: string, 1: string, 2: int, 3: int, 4: bool, 5: bool}>
     */
    public const REQUESTS = [
        ['Grace Mitchell', 'lff', 1, 10, true, true],
        ['Samuel Price', 'lff', 2, 6, false, true],
        ['James Collins', 'T-36', 20, 3, true, true],
        ['Olivia Bennett', 'T-36', 50, 1, true, false],
        ['Hannah Brooks', 'lff', 1, 0, false, false],
        ['Emily Parker', 'T-36', 10, 0, false, false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $root = User::rootUser();

        if ($root === null) {
            throw new RuntimeException('There is no root user: run `php artisan db:seed` (or `och:install`) first.');
        }

        if (TerritoryZone::query()->where('name', self::ZONES[0]['name'])->exists()) {
            $this->command?->warn('The demo data of the documentation is already seeded: nothing was done.');

            return;
        }

        Model::unguarded(function () use ($root): void {
            $users = $this->seedUsers($root);
            $this->seedRoles($users);
            $this->seedContacts($users);
            $this->seedDepartments($users);
            $territories = $this->seedTerritories($root);
            $campaigns = $this->seedCampaigns($root);
            $assignments = $this->seedAssignments($users, $territories, $campaigns, $root);
            $this->seedNotifications($root, $campaigns, $assignments);
            $this->seedPublications($users);
        });

        $this->call(FakePosterSeeder::class);
    }

    /**
     * Create the demo users, and mark the email of the root user as verified (in English, like the others).
     *
     * @return array<string, User> The users (root included), by full name
     */
    private function seedUsers(User $root): array
    {
        $root->forceFill(['email_verified_at' => $root->email_verified_at ?? now(), 'locale' => 'en'])->save();
        $users = [$root->full_name => $root];

        foreach (self::USERS as $data) {
            $canLogin = $data['role'] !== null;

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => Str::lower("{$data['first_name']}.{$data['last_name']}@example.com"),
                'password' => $canLogin ? self::PASSWORD : null,
                'can_login' => $canLogin,
                'email_verified_at' => $canLogin ? now() : null,
                'locale' => 'en',
            ]);

            $users[$user->full_name] = $user;
        }

        return $users;
    }

    /**
     * Create the roles of the congregation (besides `super_admin`, created by `och:install`) and give them to the
     * users: the territory servants manage the territories, the publishers only consult them.
     *
     * @param  array<string, User>  $users
     */
    private function seedRoles(array $users): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var class-string<Model> $roleModel */
        $roleModel = Utils::getRoleModel();
        /** @var class-string<Model> $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        $roles = (new FakeRoleSeeder)->roles();
        $roles['publisher'] = array_values(array_filter(
            $roles['publisher'],
            fn (string $permission): bool => ! str_ends_with($permission, ':Campaign'),
        ));

        foreach (['territory_servant', 'publisher'] as $name) {
            $role = $roleModel::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            $role->syncPermissions(array_map(
                fn (string $permission) => $permissionModel::firstOrCreate(['name' => $permission, 'guard_name' => 'web']),
                $roles[$name],
            ));
        }

        foreach (self::USERS as $data) {
            if ($data['role'] !== null) {
                $users["{$data['first_name']} {$data['last_name']}"]->assignRole($data['role']);
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedContacts(array $users): void
    {
        foreach (self::CONTACTS as $name => $contacts) {
            foreach ($contacts as $contact) {
                $users[$name]->contacts()->create($contact);
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedDepartments(array $users): void
    {
        foreach (self::DEPARTMENTS as $key => $members) {
            $department = Department::for(DepartmentKey::from($key));

            foreach ($members as $index => $name) {
                $department->members()->syncWithoutDetaching([
                    $users[$name]->getKey() => ['role' => $index === 0 ? DepartmentRole::Overseer : DepartmentRole::Assistant],
                ]);
            }
        }
    }

    /**
     * Create the tags, the zones and their territories, named after the code of their zone (e.g. `OT-01`).
     *
     * @return array<string, Territory> The territories, by name
     */
    private function seedTerritories(User $root): array
    {
        foreach (self::TAGS as $type => $names) {
            foreach ($names as $name) {
                FakeTagSeeder::findOrCreate($name, TagType::from($type));
            }
        }

        $territories = [];

        foreach (self::ZONES as $zoneData) {
            $zone = TerritoryZone::query()->create([
                'name' => $zoneData['name'],
                'color' => $zoneData['color'],
                'created_by' => $root->getKey(),
            ]);
            $zone->attachTags([FakeTagSeeder::findOrCreate($zoneData['tag'], TagType::ZONES)]);

            foreach ($zoneData['territories'] as $index => $data) {
                $name = sprintf('%s-%02d', $zoneData['code'], $index + 1);

                $territory = Territory::query()->create([
                    'name' => $name,
                    'territory_zone_id' => $zone->getKey(),
                    'description' => $data['description'],
                    'notes' => $data['notes'] ?? null,
                    'is_commercial' => $data['is_commercial'] ?? false,
                    'image_path' => ($data['with_image'] ?? false) ? $this->storeCardImage($name, $zoneData['name']) : null,
                    'geojson' => $this->blockGeoJson($zoneData['center'], $index, count($zoneData['territories']), $name),
                    'created_by' => $root->getKey(),
                ]);
                $territory->attachTags(array_map(
                    fn (string $tag) => FakeTagSeeder::findOrCreate($tag, TagType::TERRITORIES),
                    $data['tags'],
                ));

                $territories[$name] = $territory;
            }
        }

        return $territories;
    }

    /**
     * Draw a simple territory card (as if scanned from paper) and store it on the local disk.
     *
     * @return string The path of the image on the local disk
     */
    private function storeCardImage(string $territory, string $zone): string
    {
        $path = 'territories/'.Str::lower($territory).'.png';

        $image = imagecreatetruecolor(600, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 246, 233));
        $ink = imagecolorallocate($image, 55, 65, 81);
        $streets = imagecolorallocate($image, 156, 163, 175);
        $border = imagecolorallocate($image, 220, 38, 38);

        imagerectangle($image, 10, 10, 589, 389, $ink);
        imagestring($image, 5, 30, 25, "{$territory} - {$zone}", $ink);

        foreach ([120, 200, 280] as $y) {
            imagefilledrectangle($image, 30, $y, 570, $y + 8, $streets);
        }

        foreach ([150, 300, 450] as $x) {
            imagefilledrectangle($image, $x, 70, $x + 8, 370, $streets);
        }

        imagesetthickness($image, 4);
        imagepolygon($image, [140, 110, 470, 100, 460, 300, 160, 290], $border);

        ob_start();
        imagepng($image);
        Storage::disk('local')->put($path, (string) ob_get_clean());
        imagedestroy($image);

        return $path;
    }

    /**
     * Build the GeoJSON of the block of the grid of a zone at the given position, with its corners moved slightly
     * inwards (always by the same amounts) so that the territories do not look like a perfect grid.
     *
     * @param  array{float, float}  $center  The latitude and longitude of the center of the zone
     */
    private function blockGeoJson(array $center, int $index, int $count, string $name): string
    {
        $rows = (int) ceil($count / self::GRID_COLUMNS);
        $row = intdiv($index, self::GRID_COLUMNS);
        $column = $index % self::GRID_COLUMNS;

        $south = round($center[0] + ($rows / 2 - $row - 1) * self::BLOCK_LATITUDE_SIZE, 6);
        $west = round($center[1] + ($column - self::GRID_COLUMNS / 2) * self::BLOCK_LONGITUDE_SIZE, 6);
        $north = $south + self::BLOCK_LATITUDE_SIZE;
        $east = $west + self::BLOCK_LONGITUDE_SIZE;

        $inset = fn (float $size, int $corner): float => round($size * (0.06 + 0.03 * (($index + $corner) % 4)), 6);

        $corners = [
            [$west + $inset(self::BLOCK_LONGITUDE_SIZE, 0), $south + $inset(self::BLOCK_LATITUDE_SIZE, 1)],
            [$east - $inset(self::BLOCK_LONGITUDE_SIZE, 2), $south + $inset(self::BLOCK_LATITUDE_SIZE, 3)],
            [$east - $inset(self::BLOCK_LONGITUDE_SIZE, 1), $north - $inset(self::BLOCK_LATITUDE_SIZE, 0)],
            [$west + $inset(self::BLOCK_LONGITUDE_SIZE, 3), $north - $inset(self::BLOCK_LATITUDE_SIZE, 2)],
        ];

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => ['name' => $name],
                    'geometry' => [
                        'type' => 'Polygon',
                        // A GeoJSON ring is closed: its last position is the first one
                        'coordinates' => [[...$corners, $corners[0]]],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, Campaign> The campaigns, by short name
     */
    private function seedCampaigns(User $root): array
    {
        $campaigns = [];

        foreach (self::CAMPAIGNS as $shortName => $data) {
            $campaigns[$shortName] = Campaign::query()->create([
                'name' => $data['name'],
                'short_name' => $shortName,
                'description' => $data['description'],
                'color' => $data['color'],
                'valid_from' => today()->addDays($data['from'])->startOfDay(),
                'valid_until' => today()->addDays($data['until'])->endOfDay(),
                'created_by' => $root->getKey(),
                'created_at' => today()->subDays($data['created'])->setTime(8, 30),
                'updated_at' => today()->subDays($data['created'])->setTime(8, 30),
            ]);
        }

        return $campaigns;
    }

    /**
     * Create the assignments, and share the one of OT-01 in progress.
     *
     * @param  array<string, User>  $users
     * @param  array<string, Territory>  $territories
     * @param  array<string, Campaign>  $campaigns
     * @return list<TerritoryAssignment>
     */
    private function seedAssignments(array $users, array $territories, array $campaigns, User $root): array
    {
        $assignments = [];

        foreach (self::ASSIGNMENTS as $data) {
            [$territory, $assignee, $startedDaysAgo, $endedDaysAgo, $campaign] = $data;
            $inProgress = $endedDaysAgo === null;
            $startedAt = today()->subDays($startedDaysAgo)->setTime($inProgress ? 10 : 16, $inProgress ? 15 : 0);

            $assignments[] = TerritoryAssignment::query()->create([
                'territory_id' => $territories[$territory]->getKey(),
                'assignee_id' => $users[$assignee]->getKey(),
                'campaign_id' => $campaign !== null ? $campaigns[$campaign]->getKey() : null,
                'started_at' => $startedAt,
                'ended_at' => $inProgress ? null : today()->subDays($endedDaysAgo)->setTime(18, 30),
                'notes' => $data[5] ?? null,
                'created_by' => $root->getKey(),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
        }

        $assignments[0]->shared()->create(['uid' => self::SHARED_ASSIGNMENT_UID]);

        return $assignments;
    }

    /**
     * Give the root user the notifications they received: the last three are unread.
     *
     * @param  array<string, Campaign>  $campaigns
     * @param  list<TerritoryAssignment>  $assignments
     */
    private function seedNotifications(User $root, array $campaigns, array $assignments): void
    {
        $assignmentOf = fn (string $territory, bool $inProgress): TerritoryAssignment => collect($assignments)
            ->first(fn (TerritoryAssignment $assignment): bool => $assignment->assignee_id === $root->getKey()
                && $assignment->territory->name === $territory
                && ($assignment->ended_at === null) === $inProgress);

        $hs02 = $assignmentOf('HS-02', true);
        $ot01 = $assignmentOf('OT-01', true);
        $vi05 = $assignmentOf('VI-05', false);

        $notifications = [
            [new TerritoryAssignedNotification($hs02), $hs02->started_at, true],
            [new CampaignCreatedNotification($campaigns['MEM']), $campaigns['MEM']->created_at, true],
            [new TerritoryReturnedNotification($vi05), $vi05->ended_at, true],
            [new CampaignCreatedNotification($campaigns['ASS']), $campaigns['ASS']->created_at, false],
            [new TerritoryAssignedNotification($ot01), $ot01->started_at, false],
            [new StaleAssignmentNotification($hs02), now()->subMinutes(35), false],
        ];

        foreach ($notifications as [$notification, $sentAt, $read]) {
            $this->notify($root, $notification, $sentAt, $read);
        }
    }

    /**
     * Store a notification in the database, as sent at the given time, in the language of the user.
     */
    private function notify(User $user, Notification $notification, CarbonInterface $sentAt, bool $read): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $notification::class,
            'data' => $this->withLocale($user->preferredLocale(), fn (): array => $notification->toDatabase($user)),
            'read_at' => $read ? $sentAt->copy()->addHour() : null,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }

    /**
     * Create the regular publications received by the publishers, with their requests of the past months (delivered),
     * and some one-off requests.
     *
     * @param  array<string, User>  $users
     */
    private function seedPublications(array $users): void
    {
        $publications = Publication::query()->get()->keyBy('code');

        foreach (self::SUBSCRIPTIONS as $name => [$quantities, $months]) {
            $subscriber = PublicationSubscriber::query()->create(['user_id' => $users[$name]->getKey()]);
            $since = today()->subMonthsNoOverflow($months)->startOfMonth()->addDays(4);

            foreach ($quantities as $code => $quantity) {
                $subscription = $subscriber->subscriptions()->create([
                    'publication_id' => $publications[$code]->getKey(),
                    'quantity' => $quantity,
                    'started_at' => $since->toDateString(),
                ]);

                FakePublicationSubscriberSeeder::renewEveryMonth($subscription, today());
            }
        }

        // The requests of the past months are delivered, a few days after; the ones of this month are pending
        PublicationRequest::query()
            ->whereNotNull('subscription_id')
            ->whereDate('requested_at', '<', today()->startOfMonth()->toDateString())
            ->each(fn (PublicationRequest $request) => $request->update([
                'delivered_at' => Carbon::parse($request->requested_at)->addDays(5)->min(today())->toDateString(),
            ]));

        $publications['T-36'] ??= Publication::query()->create([
            'code' => 'T-36',
            'name' => ['en' => 'What Is the Kingdom of God?', 'it' => 'Cos\'è il Regno di Dio?', 'es' => '¿Qué es el Reino de Dios?'],
            'category' => PublicationCategory::Tracts,
        ]);

        foreach (self::REQUESTS as $index => [$name, $code, $quantity, $monthsAgo, $ordered, $delivered]) {
            $requestedAt = today()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(9 + $index)->min(today());

            PublicationRequest::query()->create([
                'user_id' => $users[$name]->getKey(),
                'publication_id' => $publications[$code]->getKey(),
                'quantity' => $quantity,
                'requested_at' => $requestedAt->toDateString(),
                'ordered_at' => $ordered ? $requestedAt->copy()->addDays(2)->min(today())->toDateString() : null,
                'delivered_at' => $delivered ? $requestedAt->copy()->addDays(9)->min(today())->toDateString() : null,
            ]);
        }
    }
}
