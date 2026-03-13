<?php

use App\Jobs\CheckRemindersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckRemindersJob)->hourly();
