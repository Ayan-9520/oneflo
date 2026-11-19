<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_code' => fake()->unique()->bothify('EMP-####'),
            'given_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'status' => 'active',
        ];
    }
}
