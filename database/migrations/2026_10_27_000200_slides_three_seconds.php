<?php

use App\Services\Website\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/** Pieter, 27/10/2026: the slideshow changes every 3 seconds instead of 6. */
return new class extends Migration
{
    public function up(): void
    {
        SiteSettings::put('general.slide_seconds', 3);
    }

    public function down(): void
    {
        SiteSettings::put('general.slide_seconds', 6);
    }
};
