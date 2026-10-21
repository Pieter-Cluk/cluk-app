<?php

namespace App\Services\Afrit6;

/** Afrit 6 price rules (cents, excl. VAT). Prices come from config/afrit6.php. */
class Pricing
{
    public static function price(string $key): int
    {
        return (int) config('afrit6.prices.'.$key);
    }

    public static function handling(int $colis, int $pallets): int
    {
        return $colis * self::price('handling_coli') + $pallets * self::price('handling_pallet');
    }

    /**
     * One-off amount of a service request. Storage (shelf, pallet) is billed
     * per day on the statement, so it has no one-off amount.
     *
     * @param  array{kind:string, colis?:int, pallets?:int, then_send?:bool, receive_mode?:?string}  $r
     */
    public static function oneOff(array $r): int
    {
        $c = (int) ($r['colis'] ?? 0);
        $p = (int) ($r['pallets'] ?? 0);

        return match ($r['kind']) {
            'packing' => $c * self::price('packing_coli') + $p * self::price('packing_pallet') + (! empty($r['then_send']) ? self::handling($c, $p) : 0),
            'receive' => ($r['receive_mode'] ?? 'shelf24') === 'store' ? self::handling($c, $p) : 0,
            'send' => self::handling($c, $p),
            default => 0,
        };
    }

    public static function dayPrice(string $kind): int
    {
        return $kind === 'pallet' ? self::price('pallet_day') : self::price('shelf_day');
    }

    public static function eur(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    /** Values used in the payment terms texts: payment days, statutory rate and fixed compensation. */
    public static function termsParams(): array
    {
        $rate = rtrim(rtrim(number_format((float) config('afrit6.payment.statutory_rate'), 2, '.', ''), '0'), '.');

        return [
            'days' => (int) config('afrit6.payment.days'),
            'rate' => app()->getLocale() === 'en' ? $rate : str_replace('.', ',', $rate),
            'fee' => self::eur((int) config('afrit6.payment.fixed_compensation')),
        ];
    }
}
