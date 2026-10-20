<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Cowork2026!',
            'locale' => 'en',
            'reg_country' => 'BE',
            'reg_company_name' => 'Demo Studio',
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['email_verified_at' => now(), 'status' => Member::ACTIVE, 'approved_at' => now()]);
    }
}
