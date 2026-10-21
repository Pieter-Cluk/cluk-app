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
        return $this->state(fn () => ['email_verified_at' => now(), 'status' => Member::ACTIVE, 'approved_at' => now()])->withTerms();
    }

    /** Accepted the current house & booking rules and payment terms. */
    public function withTerms(): static
    {
        return $this->state(fn () => [
            'terms_accepted_at' => now(), 'rules_accepted_at' => now(), 'payment_terms_accepted_at' => now(),
            'terms_version' => config('afrit6.terms_version'),
        ]);
    }

    public function withoutTerms(): static
    {
        return $this->state(fn () => ['rules_accepted_at' => null, 'payment_terms_accepted_at' => null, 'terms_version' => null]);
    }
}
