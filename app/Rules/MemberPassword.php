<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 10+ characters, letters and a number, and not the person's name or email. */
class MemberPassword implements ValidationRule
{
    public function __construct(private ?string $firstName = null, private ?string $lastName = null, private ?string $email = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pw = (string) $value;
        $lower = mb_strtolower($pw);

        if (mb_strlen($pw) < 10) {
            $fail(__('afrit6.password_rules.length'));
        }
        if (! preg_match('/\pL/u', $pw) || ! preg_match('/\d/', $pw)) {
            $fail(__('afrit6.password_rules.mix'));
        }

        $local = mb_strtolower((string) strtok((string) $this->email, '@'));
        foreach ([$this->firstName, $this->lastName, $local] as $part) {
            $part = mb_strtolower(trim((string) $part));
            if (mb_strlen($part) > 2 && str_contains($lower, $part)) {
                $fail(__('afrit6.password_rules.personal'));

                return;
            }
        }
    }
}
