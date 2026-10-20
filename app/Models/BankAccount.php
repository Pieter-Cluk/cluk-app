<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bank', 'iban', 'bic', 'is_main', 'on_documents'])]
class BankAccount extends Model
{
    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'on_documents' => 'boolean'];
    }

    /** IBAN in groups of four, as printed: BE68 5390 0754 7034. */
    public function formattedIban(): string
    {
        return trim(chunk_split(strtoupper(str_replace(' ', '', $this->iban)), 4, ' '));
    }

    /** IBAN checksum (mod 97). */
    public static function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $moved = substr($iban, 4).substr($iban, 0, 4);
        $numeric = preg_replace_callback('/[A-Z]/', fn ($m) => (string) (ord($m[0]) - 55), $moved);

        $rest = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $rest = (int) (($rest.$chunk) % 97);
        }

        return $rest === 1;
    }
}
