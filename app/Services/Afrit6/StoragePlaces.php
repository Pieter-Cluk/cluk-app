<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Warehouse\Position;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Which shelf or pallet place belongs to which member: a place is linked to a running storage request.
 * Only places marked "Afrit 6" in Warehouse › Places can be used, and one place belongs to one member at a time.
 * When the storage ends, the place is free again (the link stays as history).
 */
class StoragePlaces
{
    /** The running storage request that holds this place, if any. */
    public static function holder(Position $p, ?int $except = null): ?ServiceRequest
    {
        return ServiceRequest::runningStorage()->with('member.company')
            ->whereHas('places', fn ($q) => $q->where('warehouse_positions.id', $p->id))
            ->when($except, fn ($q) => $q->where('id', '!=', $except))->first();
    }

    /** @return array<string, ServiceRequest> place code => running storage request */
    public static function held(): array
    {
        $out = [];
        foreach (ServiceRequest::runningStorage()->with(['member.company', 'places'])->get() as $r) {
            foreach ($r->places as $p) {
                $out[$p->code] = $r;
            }
        }

        return $out;
    }

    /** Free Afrit 6 places for this request: the same kind (shelf or pallet) first. */
    public static function choices(ServiceRequest $r): Collection
    {
        $held = self::held();

        return Position::where('use', 'afrit6')->where('active', true)->orderBy('code')->get()
            ->reject(fn (Position $p) => isset($held[$p->code]))
            ->sortBy(fn (Position $p) => ($p->kind === $r->kind ? '0' : '1').$p->code)->values();
    }

    /** Why this place can't go to this request, or null when it can. */
    public static function problem(ServiceRequest $r, Position $p): ?string
    {
        if (! $r->isStorage()) {
            return __('workspace.places.not_storage');
        }
        if ($p->use !== 'afrit6' || ! $p->active) {
            return __('workspace.places.not_afrit6', ['place' => $p->code]);
        }
        if ($other = self::holder($p, $r->id)) {
            return __('workspace.places.taken', ['place' => $p->code, 'name' => $other->member?->companyName() ?? '—']);
        }

        return null;
    }

    public static function add(ServiceRequest $r, Position $p, User $by): void
    {
        if ($why = self::problem($r, $p)) {
            throw ValidationException::withMessages(['place' => $why]);
        }
        if ($r->places()->where('warehouse_positions.id', $p->id)->exists()) {
            return;
        }
        $r->places()->attach($p->id, ['assigned_by_id' => $by->id]);
        AuditLog::record('afrit6.place_set', $r->member, ['place' => $p->code, 'request' => $r->id, 'kind' => $r->kind], $by);
    }

    public static function remove(ServiceRequest $r, Position $p, User $by): void
    {
        if ($r->places()->detach($p->id)) {
            AuditLog::record('afrit6.place_freed', $r->member, ['place' => $p->code, 'request' => $r->id], $by);
        }
    }
}
