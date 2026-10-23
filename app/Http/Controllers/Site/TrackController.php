<?php

namespace App\Http\Controllers\Site;

use App\Services\Website\Tracking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Events from the website pages (only stored when the visitor accepted statistics cookies). */
class TrackController extends SiteController
{
    public function store(Request $request): Response
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['view', 'search', 'product', 'add'])],
            'section' => ['nullable', 'string', 'max:10'], 'path' => ['nullable', 'string', 'max:500'], 'label' => ['nullable', 'string', 'max:500'],
            'item_id' => ['nullable', 'integer'], 'referrer' => ['nullable', 'string', 'max:500'],
            'utm' => ['nullable', 'array'], 'utm.*' => ['nullable', 'string', 'max:200'], 'meta' => ['nullable', 'array', 'max:10'],
        ]);
        Tracking::record($request, $d['type'], $d);

        return response()->noContent();
    }
}
