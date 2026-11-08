<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AI\AiUserMessage;
use Illuminate\Support\Facades\Http;

$key = config('gemini.api_key');
$model = config('gemini.default_model');
$url = rtrim(config('gemini.base_url'), '/').'/models/'.$model.':generateContent';

echo 'Key prefix: '.substr((string) $key, 0, 6).'...'.PHP_EOL;
echo 'Model: '.$model.PHP_EOL;

try {
    $r = Http::timeout(20)
        ->withQueryParameters(['key' => $key])
        ->post($url, [
            'contents' => [['parts' => [['text' => 'diga ok']]]],
        ]);
    echo 'HTTP '.$r->status().PHP_EOL;
    echo substr($r->body(), 0, 300).PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: '.AiUserMessage::redactSecrets($e->getMessage()).PHP_EOL;
}
