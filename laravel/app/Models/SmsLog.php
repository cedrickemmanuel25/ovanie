<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{


    public function handle()
    {
        $log = SmsLog::create([
            'phone' => $this->phone,
            'message' => $this->message,
            'status' => 'pending'
        ]);

        try {
            SmsManager::send($this->phone, $this->message);

            $log->update([
                'status' => 'sent'
            ]);

        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'response' => $e->getMessage()
            ]);
        }
    }
}
