<?php

namespace Database\Factories\Spatie\Tags;

use App\Enums\TagType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Tags\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'tag-'.$this->faker->unique()->word();

        return [
            'name' => ['en' => $name],
            'slug' => ['en' => $name],
            'type' => $this->faker->randomElement(array_keys(TagType::getOptions())),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
