<?php

namespace App\Services\Warehouse;

use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use App\Models\Warehouse\Delivery;
use App\Services\Afrit6\MemberMails;

/** Unannounced deliveries: find the owner, or wait on the unknown goods shelf. */
class Deliveries
{
    /** The goods belong to an Afrit 6 member: on the 24 h shelf, the member is told. */
    public static function toMember(Delivery $d, Member $member, User $by): ServiceRequest
    {
        $r = ServiceRequest::create([
            'member_id' => $member->id, 'kind' => 'receive', 'status' => ServiceRequest::DONE, 'date' => today(), 'colis' => $d->colli, 'pallets' => $d->pallets,
            'receive_mode' => 'shelf24', 'from_text' => $d->carrier, 'note' => __('warehouse.delivery.request_note', ['n' => $d->id]),
            'photos' => $d->photos, 'scans' => [['code' => 'A6-24H', 'at' => now()->toIso8601String(), 'by' => $by->id]],
            'assignee_id' => $by->id, 'decided_by_id' => $by->id, 'done_at' => now(),
        ]);
        $d->update(['match' => 'member', 'member_id' => $member->id, 'request_id' => $r->id, 'status' => 'done', 'closed_by_id' => $by->id, 'closed_at' => now()]);
        AuditLog::record('warehouse.delivery', $d, ['to' => $member->companyName(), 'colli' => $d->colli, 'pallets' => $d->pallets], $by);
        MemberMails::arrived($r->load('member'));

        return $r;
    }

    public static function close(Delivery $d, string $match, ?string $note, User $by, array $extra = []): void
    {
        $d->update($extra + ['match' => $match, 'status' => 'done', 'closed_by_id' => $by->id, 'closed_at' => now(), 'note' => $note ?? $d->note]);
        AuditLog::record('warehouse.delivery', $d, ['match' => $match, 'note' => $note], $by);
    }
}
