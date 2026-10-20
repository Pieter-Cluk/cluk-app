<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Settings › Users › Activity log: who changed what, newest first. */
class AuditLogController extends Controller
{
    public const AREAS = ['user', 'role', 'auth', 'crm', 'afrit6', 'import', 'company', 'vat'];

    public function index(Request $request): View
    {
        $area = in_array($request->query('area'), self::AREAS, true) ? $request->query('area') : null;

        return view('settings.users.audit', [
            'logs' => AuditLog::query()
                ->with(['actor', 'subject'])
                ->when($area, fn ($q) => $q->where('action', 'like', $area.'.%'))
                ->latest('id')
                ->paginate(50)->withQueryString(),
            'area' => $area,
        ]);
    }
}
