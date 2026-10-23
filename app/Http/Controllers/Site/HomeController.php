<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends SiteController
{
    public function show(Request $request): View
    {
        $this->locale($request);

        return view('site.home', ['mix' => \App\Services\Website\SocialFeed::mix(4), 'list' => \App\Services\Website\SocialFeed::for(null, 14)]);
    }
}
