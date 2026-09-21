<?php

namespace App\Services\Sms\Drivers;

use Illuminate\Support\Facades\Http;

class AllMySmsDriver
{
    protected $apiKey;
    protected $sender;
    protected $url;

    public function __construct()
    {
        $this->apiKey = config('sms.allmysms.api_key');
        $this->sender = config('sms.allmysms.sender');
        $this->url = config('sms.allmysms.url');
    }

    public function send($phone, $message)
    {
        if (blank($this->apiKey) || blank($this->sender) || blank($this->url)) {
            return false;
        }

        $response = Http::get($this->url, [
            'apiKey' => $this->apiKey,
            'sms' => $message,
            'sender' => $this->sender,
            'recipients' => $phone,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Erreur API SMS');
        }

        return $response->json();
    }
}
