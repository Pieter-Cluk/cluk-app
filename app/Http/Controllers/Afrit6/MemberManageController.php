<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\Guest;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Member;
use App\Notifications\MemberInvited;
use App\Services\Afrit6\MemberApproval;
use App\Support\SafeMail;
use App\Support\VatNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The owner's tools for Afrit 6 members (right afrit6.manage_members, off for everyone by default): add a member,
 * change anything on the card, add or remove bookings, change or remove desk rentals, delete a member.
 * Passwords stay with the member: a new member gets a mail to choose one. Every change goes to the audit log.
 */
class MemberManageController extends Controller
{
    private const DETAILS = ['first_name', 'last_name', 'email', 'mobile', 'locale', 'newsletter', 'reg_company_name', 'reg_vat_number', 'reg_country', 'reg_address', 'status', 'company_id'];

    public function create(): View
    {
        return view('afrit6.staff.create', ['companies' => self::companyChoices()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $member = DB::transaction(function () use ($d, $request) {
            $member = new Member;
            $member->forceFill([
                'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'email' => $d['email'], 'mobile' => $d['mobile'] ?? null,
                'locale' => $d['locale'], 'newsletter' => $d['newsletter'], 'password' => Str::random(48), 'status' => Member::PENDING,
                'reg_company_name' => $d['reg_company_name'], 'reg_vat_number' => $d['reg_vat_number'], 'reg_country' => $d['reg_country'],
                'reg_address' => $d['reg_address'], 'company_id' => $d['company_id'],
            ])->save();
            AuditLog::record('afrit6.member_added', $member, ['by_staff' => true], $request->user());
            MemberApproval::approve($member->fresh(), $request->user());

            return $member->fresh();
        });
        if ($request->boolean('send_mail', true)) {
            $this->welcome($member);
        }

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.manage.added', ['name' => $member->name]));
    }

    /** Change anything on the card. */
    public function update(Request $request, Member $member): RedirectResponse
    {
        $d = $this->validated($request, $member);
        $before = $member->only(self::DETAILS);
        $member->forceFill(array_intersect_key($d, array_flip(self::DETAILS)));
        if ($member->status === Member::ACTIVE && ! $member->approved_at) {
            $member->approved_at = now();
        }
        if ($member->status === Member::BLOCKED) {
            $member->remember_token = null;
        }
        $changes = collect(self::DETAILS)->filter(fn ($k) => (string) ($before[$k] ?? '') !== (string) ($member->{$k} ?? ''))
            ->mapWithKeys(fn ($k) => [$k => [$before[$k], $member->{$k}]])->all();
        $member->save();
        if ($changes) {
            AuditLog::record('afrit6.member_edited', $member, ['changes' => $changes], $request->user());
        }

        return back()->with('status', __('afrit6.manage.saved'));
    }

    public function sendWelcome(Member $member): RedirectResponse
    {
        $this->welcome($member);

        return back()->with('status', __('afrit6.manage.welcome_sent', ['email' => $member->email]));
    }

    /** Delete a member with their bookings. Not when there are statements or invoices: block them instead. */
    public function destroy(Request $request, Member $member): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:'.$member->email]], ['confirm.in' => __('afrit6.manage.delete_type_email')]);
        if ($member->statements()->exists() || DB::table('pre_invoices')->where('member_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['confirm' => __('afrit6.manage.delete_has_bills')]);
        }
        $name = $member->name;
        DB::transaction(function () use ($member, $request, $name) {
            AuditLog::record('afrit6.member_deleted', null, ['member_id' => $member->id, 'name' => $name, 'company' => $member->companyName()], $request->user());
            $member->delete();
        });

        return redirect()->route('afrit6.staff.index', ['tab' => 'active'])->with('status', __('afrit6.manage.deleted', ['name' => $name]));
    }

    // ------------------------------------------------------------------ bookings

    /** A desk for a day or half a day, also in the past (e.g. used without booking). */
    public function addDeskDay(Request $request, Member $member): RedirectResponse
    {
        $d = $request->validate([
            'space_id' => ['required', 'integer', Rule::exists('afrit6_spaces', 'id')->where('kind', 'desk')],
            'date' => ['required', 'date_format:Y-m-d'],
            'part' => ['required', Rule::in(DeskBooking::PARTS)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        $date = Carbon::parse($d['date'])->startOfDay();
        $space = Space::findOrFail($d['space_id']);
        if (! $space->isFreeForPart($date, $d['part'])) {
            throw ValidationException::withMessages(['space_id' => __('workspace.days.taken', ['desk' => $space->code])]);
        }
        $b = DeskBooking::create(['member_id' => $member->id, 'space_id' => $space->id, 'date' => $date, 'part' => $d['part'],
            'price_cents' => isset($d['price']) ? (int) round($d['price'] * 100) : DeskBooking::price($d['part'])]);
        AuditLog::record('afrit6.booking_added', $member, ['desk' => $space->code, 'date' => $date->toDateString(), 'part' => $d['part'], 'price_cents' => $b->price_cents], $request->user());

        return back()->with('status', __('afrit6.manage.booking_added'));
    }

    /** The meeting room, also in the past. */
    public function addRoom(Request $request, Member $member): RedirectResponse
    {
        $room = Space::room();
        abort_unless($room, 404);
        $max = (int) config('afrit6.room.slots');
        $d = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'integer', 'min:0', 'max:'.($max - 1)],
            'slots' => ['required', 'integer', 'min:1', 'max:'.$max],
            'setup' => ['required', Rule::in(array_keys(config('afrit6.room.setups')))],
        ]);
        $start = (int) $d['start'];
        $end = $start + (int) $d['slots'];
        if ($end > $max) {
            throw ValidationException::withMessages(['slots' => __('afrit6.manage.room_too_late')]);
        }
        $clash = RoomBooking::live()->where('space_id', $room->id)->whereDate('date', $d['date'])->get()
            ->first(fn ($b) => $b->start_slot < $end && $b->endSlot() > $start);
        if ($clash) {
            throw ValidationException::withMessages(['start' => __('afrit6.manage.room_taken', ['time' => $clash->timeRange()])]);
        }
        $b = RoomBooking::create(['member_id' => $member->id, 'space_id' => $room->id, 'date' => $d['date'], 'start_slot' => $start, 'slots' => (int) $d['slots'], 'setup' => $d['setup'], 'note' => __('afrit6.manage.added_by_cluk')]);
        AuditLog::record('afrit6.booking_added', $member, ['room' => $d['date'].' '.$b->timeRange()], $request->user());

        return back()->with('status', __('afrit6.manage.booking_added'));
    }

    /** Remove a booking (desk day, room or guest): it no longer counts on the statement. */
    public function removeBooking(Request $request, Member $member, string $kind, int $id): RedirectResponse
    {
        $model = ['desk' => DeskBooking::class, 'room' => RoomBooking::class, 'guest' => Guest::class][$kind] ?? abort(404);
        $b = $model::where('member_id', $member->id)->whereNull('cancelled_at')->findOrFail($id);
        $b->update(['cancelled_at' => now()]);
        AuditLog::record('afrit6.booking_removed', $member, ['kind' => $kind, 'id' => $b->id, 'date' => $b->date?->toDateString()], $request->user());

        return back()->with('status', __('afrit6.manage.booking_removed'));
    }

    // ------------------------------------------------------------------ desk rentals

    public function updateRental(Request $request, Member $member, DeskRental $rental): RedirectResponse
    {
        abort_unless($rental->member_id === $member->id, 404);
        $d = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);
        if (! $rental->space->isFree($d['starts_on'], $d['ends_on'] ?? null, $rental->id)) {
            throw ValidationException::withMessages(['starts_on' => __('workspace.desks.not_free', ['desk' => $rental->space->code])]);
        }
        $before = ['starts_on' => $rental->starts_on->toDateString(), 'ends_on' => $rental->ends_on?->toDateString(), 'price_cents' => $rental->monthly_price_cents];
        $rental->update(['starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'] ?? null, 'monthly_price_cents' => (int) round($d['price'] * 100)]);
        AuditLog::record('afrit6.rental_edited', $member, ['desk' => $rental->space->code, 'before' => $before, 'after' => ['starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'] ?? null, 'price_cents' => $rental->monthly_price_cents]], $request->user());

        return back()->with('status', __('afrit6.manage.saved'));
    }

    public function deleteRental(Request $request, Member $member, DeskRental $rental): RedirectResponse
    {
        abort_unless($rental->member_id === $member->id, 404);
        AuditLog::record('afrit6.rental_deleted', $member, ['desk' => $rental->space->code, 'starts_on' => $rental->starts_on->toDateString(), 'ends_on' => $rental->ends_on?->toDateString(), 'price_cents' => $rental->monthly_price_cents], $request->user());
        $rental->delete();

        return back()->with('status', __('afrit6.manage.rental_deleted'));
    }

    // ------------------------------------------------------------------ helpers

    /** Company as "Name · #id" (existing) or a new name. */
    public static function companyChoices()
    {
        return Company::orderBy('name')->get(['id', 'name'])->map(fn ($c) => $c->name.' · #'.$c->id);
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', Rule::unique('members', 'email')->ignore($member?->id)],
            'mobile' => ['nullable', 'string', 'max:40'],
            'locale' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'company' => ['required', 'string', 'max:190'],
            'reg_vat_number' => ['nullable', 'string', 'max:30'],
            'reg_country' => ['nullable', 'string', 'size:2'],
            'reg_address' => ['nullable', 'string', 'max:255'],
            'status' => [$member ? 'required' : 'nullable', Rule::in([Member::PENDING, Member::ACTIVE, Member::DECLINED, Member::BLOCKED])],
        ]);
        $company = preg_match('/#(\d+)\s*$/', $d['company'], $m) ? Company::find((int) $m[1]) : null;
        $d['company_id'] = $company?->id ?? ($member && $d['company'] === $member->companyName() ? $member->company_id : null);
        $d['reg_company_name'] = $company?->name ?? trim(preg_replace('/·\s*#\d+\s*$/', '', $d['company']));
        $d['reg_country'] = strtoupper($d['reg_country'] ?? '') ?: ($company?->country ?? 'BE');
        $d['reg_vat_number'] = filled($d['reg_vat_number'] ?? null) ? VatNumber::normalize($d['reg_vat_number'], $d['reg_country']) : ($company?->vat_number ?? null);
        $d['newsletter'] = $request->boolean('newsletter');
        if (! $member) {
            unset($d['status']);
        }

        return $d;
    }

    private function welcome(Member $member): void
    {
        $token = Password::broker('member_invites')->createToken($member);
        SafeMail::notify($member, new MemberInvited($token));
        AuditLog::record('afrit6.welcome_sent', $member);
    }
}
