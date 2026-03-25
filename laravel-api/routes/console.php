<?php

use App\Jobs\CheckRemindersJob;
use App\Jobs\ProcessAutoCampaignJob;
use App\Jobs\RunQualityVerificationJob;
use App\Jobs\RunScraperBatchJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckRemindersJob)->hourly();

// Daily database backup at 3:00 AM UTC
Schedule::command('backup:database')->dailyAt('03:00')->withoutOverlapping();

// Web scraper: dispatch batch of contacts to scrape every hour
Schedule::job(new RunScraperBatchJob)->hourly()->withoutOverlapping();

// Auto campaigns: check for next task to process every minute
// The job itself handles rate limiting (default 5min between tasks)
Schedule::job(new ProcessAutoCampaignJob)->everyMinute()->withoutOverlapping();

// Quality verification: run full pipeline every hour
Schedule::job(new RunQualityVerificationJob)->hourly()->withoutOverlapping();
