<?php

namespace App\Http\Controllers;

use App\Models\EmailBlock;
use App\Services\Mail\Bounces;
use App\Services\Sales\CommercialTasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A bounced address was checked: mails may go to it again (the task closes). */
class EmailBlockController extends Controller
{
    public function clear(Request $request, EmailBlock $block): RedirectResponse
    {
        abort_if($block->cleared_at, 409);
        Bounces::clear($block, $request->user());
        CommercialTasks::refresh();

        return back()->with('status', __('companies.bounce.cleared', ['email' => $block->email]));
    }
}
