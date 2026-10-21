<?php

namespace App\Providers;

use App\Mail\GraphTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Email through Microsoft 365 (Graph).
        Mail::extend('microsoft-graph', fn () => new GraphTransport(
            (string) config('services.microsoft.tenant'),
            (string) config('services.microsoft.client_id'),
            (string) config('services.microsoft.client_secret'),
            (string) config('mail.from.address'),
        ));

        // Microsoft 365 login for staff.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }
}
