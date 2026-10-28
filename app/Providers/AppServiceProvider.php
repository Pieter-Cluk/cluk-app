<?php

namespace App\Providers;

use App\Mail\GraphTransport;
use App\Support\MailCopy;
use Illuminate\Mail\Events\MessageSending;
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
        // Every mail to outside people: a blind copy to CLUK's own mailbox.
        // Addresses that bounced get no more mail (App\Services\Mail\Bounces); then the copy rule.
        Event::listen(function (MessageSending $event) {
            if (\App\Services\Mail\Bounces::filter($event->message) === false) {
                return false;
            }
            MailCopy::apply($event->message);
        });
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }
}
