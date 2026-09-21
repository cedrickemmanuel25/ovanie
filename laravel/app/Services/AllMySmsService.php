<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AllMySmsService
{
    protected $apiKey;
    protected $sender;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('ALLMYSMS_API_KEY');
        $this->sender = env('ALLMYSMS_SENDER');
        $this->apiUrl = env('ALLMYSMS_API_URL');
    }

    public function sendSms($to, $message)
    {
        $response = Http::get($this->apiUrl, [
            'apiKey' => $this->apiKey,
            'sms' => $message,
            'sender' => $this->sender,
            'recipients' => $to,
        ]);

        return $response->json();
    }
}