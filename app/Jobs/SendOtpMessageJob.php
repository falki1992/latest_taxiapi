<?php

namespace App\Jobs;

use App\Services\WAAPIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOtpMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mobileNo;
    public $otp;

    /**
     * Create a new job instance.
     *
     * @param string $mobileNo
     * @param int $otp
     */
    public function __construct($mobileNo, $otp)
    {
        $this->mobileNo = $mobileNo;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Resolve the WAAPIService from the container
        $whatsappService = app(WAAPIService::class);

        // Send OTP via WhatsApp
        $message = "Taxi Rides OTP is: " . $this->otp;
        $whatsappService->sendMessage($this->mobileNo, $message);
    }
}


