<?php

namespace App\Providers;

use App\Http\Controllers\LinkedInTelegramController;
use App\Console\Commands\AutoPublishLinkedInCommand;
use App\Console\Commands\CheckLinkedInCommentsCommand;
use App\Console\Commands\SetLinkedInTelegramWebhookCommand;
use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\PressContact;
use App\Observers\ContactObserver;
use App\Observers\InfluenceurObserver;
use App\Observers\PressContactObserver;
use App\Services\Social\SocialDriverManager;
use App\Services\Social\TelegramAlertService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Keep SocialDriverManager as a singleton so its internal $instances
        // cache is shared across the request (avoids re-instantiating drivers
        // for every controller/job that resolves one).
        $this->app->singleton(SocialDriverManager::class);

        // LinkedIn-specific classes use the dedicated LinkedIn Telegram bot
        // (TELEGRAM_LINKEDIN_BOT_TOKEN / TELEGRAM_LINKEDIN_CHAT_ID)
        // All other code that injects TelegramAlertService gets the general alerts bot
        $linkedInClasses = [
            AutoPublishLinkedInCommand::class,
            CheckLinkedInCommentsCommand::class,
            SetLinkedInTelegramWebhookCommand::class,
            LinkedInTelegramController::class,
        ];

        foreach ($linkedInClasses as $class) {
            $this->app->when($class)
                ->needs(TelegramAlertService::class)
                ->give(fn() => new TelegramAlertService('linkedin'));
        }
    }

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
        Influenceur::observe(InfluenceurObserver::class);
        PressContact::observe(PressContactObserver::class);
    }
}
