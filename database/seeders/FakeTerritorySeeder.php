<?php

namespace Database\Seeders;

use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Faker\Generator as Faker;

class FakeTerritorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = app(Faker::class);
        
        $users = User::all();
        $territoryZones = TerritoryZone::factory(3)->withColor()->hasTerritories(3, fn () => [
            'is_commercial' => $faker->boolean(25),
        ])->withTags()->create();

        Territory::factory(3)->for($territoryZones->random())->state(fn () => [
            'created_by' => $users->random()->id,
        ])->withImage()->withTags()->create();
        
        Territory::factory(5)->for($territoryZones->random())->state(fn () => [
            'created_by' => $users->random()->id,
        ])->withMap()->withTags()->create();

        Territory::factory(2)->for($territoryZones->random())->state(fn () => [
            'created_by' => $users->random()->id,
        ])->isCommercial()->withImage()->create();

        Territory::factory(2)->for($territoryZones->random())->state(fn () => [
            'created_by' => $users->random()->id,
        ])->isCommercial()->withMap()->create();

        // Create territories with both image and map (test)
        Territory::factory(2)->for($territoryZones->random())->state(fn () => [
            'created_by' => $users->random()->id,
        ])->isCommercial()->withImage()->withMap()->create();
    }
}
