<?php

namespace App\Services\SupportAi;

use Illuminate\Http\Request;

class TwilioWebhookSignatureValidator
{
    public function valid(Request $request): bool
    {
        if (app()->environment('testing') && config('support_ai.telephony.twilio.allow_unsigned_testing')) {
            return true;
        }

        $token = (string) config('support_ai.telephony.twilio.auth_token');
        $provided = (string) $request->header('X-Twilio-Signature');
        if ($token === '' || $provided === '') {
            return false;
        }

        $url = $this->publicUrl($request);
        $params = $request->post();
        ksort($params, SORT_STRING);

        $data = $url;
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                sort($value, SORT_STRING);
                foreach ($value as $item) {
                    $data .= $key.$item;
                }
            } else {
                $data .= $key.$value;
            }
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $provided);
    }

    private function publicUrl(Request $request): string
    {
        $base = rtrim((string) config('support_ai.telephony.public_base_url'), '/');
        $query = $request->getQueryString();

        return $base.$request->getPathInfo().($query ? '?'.$query : '');
    }
}
