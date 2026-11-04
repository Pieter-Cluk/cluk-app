<?php

/*
 * Demo data for filming the Afrit 6 help videos: made-up members and bookings, never real data.
 * Run on an EMPTY demo database only:
 *   DB_DATABASE=/tmp/a6demo.sqlite php artisan migrate:fresh --force
 *   DB_DATABASE=/tmp/a6demo.sqlite php artisan tinker --execute="require 'tools/afrit6-help/demo.php';"
 * Logs in as lotte@studio-lumen.example / Demo-Afrit6-2026!
 */

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\Guest;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\Member;
use App\Services\Afrit6\MemberApproval;

abort_unless(Member::count() === 0, 500, 'Demo data only goes into an empty demo database.');
(new Database\Seeders\RoleSeeder)->run();

$make = function (string $first, string $last, string $email, string $company, string $locale) {
    $m = new Member;
    $m->forceFill([
        'first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => 'Demo-Afrit6-2026!', 'locale' => $locale,
        'mobile' => '+32 470 12 34 56', 'reg_company_name' => $company, 'reg_country' => 'BE', 'reg_address' => 'Demostraat 1, 9870 Zulte',
        'status' => Member::PENDING, 'email_verified_at' => now(), 'terms_accepted_at' => now(), 'rules_accepted_at' => now(),
        'payment_terms_accepted_at' => now(), 'terms_version' => config('afrit6.terms_version'),
    ])->save();
    MemberApproval::approve($m);

    return $m->fresh();
};

$lotte = $make('Lotte', 'Peeters', 'lotte@studio-lumen.example', 'Studio Lumen', 'nl');
$lotte->forceFill(['door_code' => '4826'])->save();
$jonas = $make('Jonas', 'Wouters', 'jonas@kader.example', 'Kader Video', 'nl');
$sara = $make('Sara', 'Maes', 'sara@pixelpeak.example', 'Pixel Peak', 'en');

$desk = fn (string $code) => Space::where('code', $code)->firstOrFail();
$desks = Space::desks()->orderBy('code')->get();
$rate = (int) config('afrit6.prices.desk_month', 30000);
DeskRental::create(['member_id' => $lotte->id, 'space_id' => $desks[3]->id, 'starts_on' => today()->subMonths(2)->startOfMonth(), 'monthly_price_cents' => $rate]);
DeskRental::create(['member_id' => $jonas->id, 'space_id' => $desks[0]->id, 'starts_on' => today()->subMonth()->startOfMonth(), 'monthly_price_cents' => $rate]);
DeskRental::create(['member_id' => $jonas->id, 'space_id' => $desks[1]->id, 'starts_on' => today()->subMonth()->startOfMonth(), 'monthly_price_cents' => $rate]);
DeskRental::create(['member_id' => $sara->id, 'space_id' => $desks[6]->id, 'starts_on' => today()->subMonths(3)->startOfMonth(), 'monthly_price_cents' => $rate]);

$next = today()->addDay()->isWeekend() ? today()->next('Monday') : today()->addDay();
DeskBooking::create(['member_id' => $sara->id, 'space_id' => $desks[8]->id, 'date' => $next, 'part' => 'day', 'price_cents' => DeskBooking::price('day')]);

$room = Space::room();
RoomBooking::create(['member_id' => $jonas->id, 'space_id' => $room->id, 'date' => $next, 'start_slot' => 2, 'slots' => 4, 'setup' => 'meeting']);
RoomBooking::create(['member_id' => $lotte->id, 'space_id' => $room->id, 'date' => $next->copy()->addDays(2), 'start_slot' => 8, 'slots' => 3, 'setup' => 'meeting', 'note' => 'Klantgesprek']);

Guest::create(['kind' => 'guest', 'member_id' => $lotte->id, 'date' => $next->copy()->addDays(2), 'time' => '14:00', 'name' => 'Tom Claes', 'company' => 'Brouwerij Demo', 'on_screen' => true]);

ServiceRequest::create(['member_id' => $lotte->id, 'kind' => 'shelf', 'status' => ServiceRequest::CONFIRMED, 'date' => today()->subWeeks(3), 'qty' => 1]);

echo "Demo ready: {$lotte->email}\n";
