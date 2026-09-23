<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$response = Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
    ->timeout(15)
    ->post('https://api.openai.com/v1/chat/completions', [
        'model' => config('services.openai.text_model', 'gpt-4o-mini'),
        'messages' => [
            ['role' => 'user', 'content' => 'Reponds uniquement OK'],
        ],
    ]);

echo 'STATUS: ' . $response->status() . PHP_EOL;
echo $response->body() . PHP_EOL;
