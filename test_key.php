<?php
// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Config key: " . substr(config('services.openai.key'), -20) . "\n";
echo "Env key: " . substr(env('OPENAI_API_KEY'), -20) . "\n";
echo "getenv: " . substr(getenv('OPENAI_API_KEY'), -20) . "\n";
