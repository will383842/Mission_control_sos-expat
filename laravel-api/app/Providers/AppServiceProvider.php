<?php

namespace App\Providers;

use App\Models\Contact;
use App\Observers\ContactObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
    }
}
