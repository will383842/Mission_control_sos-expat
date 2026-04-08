<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\PressContact;
use App\Observers\ContactObserver;
use App\Observers\InfluenceurObserver;
use App\Observers\PressContactObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
        Influenceur::observe(InfluenceurObserver::class);
        PressContact::observe(PressContactObserver::class);
    }
}
