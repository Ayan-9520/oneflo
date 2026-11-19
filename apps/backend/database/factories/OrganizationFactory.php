<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'platform_id' => Platform::query()->firstOrCreate(
                ['slug' => 'oneflo'],
                ['name' => 'OneFlo'],
            )->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'status' => 'active',
        ];
    }
}
