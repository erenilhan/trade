<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$binance = app(App\Services\BinanceService::class);
$positions = $binance->fetchPositions();

echo "Binance Canlı Pozisyonlar:\n";
echo str_repeat('-', 60) . "\n";

foreach ($positions as $pos) {
    if ($pos['contracts'] != 0) {
        echo sprintf(
            "%-12s | Leverage: %2dx | Contracts: %s\n",
            $pos['symbol'],
            $pos['leverage'],
            $pos['contracts']
        );
    }
}
