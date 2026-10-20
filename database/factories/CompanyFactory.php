<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Demo '.fake()->unique()->word().' BV',
            'is_customer' => true,
            'is_supplier' => false,
            'country' => 'BE',
            'city' => fake()->city(),
            'language' => 'nl',
            'vat_regime' => 'auto',
        ];
    }

    public function supplier(): static
    {
        return $this->state(['is_customer' => false, 'is_supplier' => true]);
    }
}
