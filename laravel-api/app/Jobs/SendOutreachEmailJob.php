<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 2;

    public function __construct(private int $outreachEmailId) {}

    public function handle(EmailSendingService $service): void
    {
        $email = OutreachEmail::with('influenceur')->find($this->outreachEmailId);
        if (!$email || $email->status !== 'approved') return;

        $service->send($email);
    }
}
