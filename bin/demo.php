<?php

declare(strict_types=1);

try {
    require_once __DIR__ . '/../vendor/autoload.php';

    // We support some cli args, with sensible defaults
    $options = getopt('', ['key::', 'pairs::', 'prices::', 'debug::']);
    $apiKey = $options['key'] ?? '';
    $pairs = array_filter(explode(',', strtoupper(trim($options['pairs'] ?? 'EURUSD'))));

    // Default to debug, if neither debug nor prices are set
    if (!isset($options['debug']) && !isset($options['prices'])) {
        $options['debug'] = true;
    }

    // Create the client
    $primeClient = new \PrimeAPI\WebSocket\Client("wss://euc2.primeapi.io");

    // Set API key (by default, and when this is empty, the client looks at PRIME_API_KEY env var)
    $primeClient->apiKey($apiKey);

    // Want real-time updates?
    $primeClient->realTimePairs($pairs); // e.g. ['USDJPY', 'AUDCAD']

    // Use 'one second' pairs If you don't need realtime updates (Can be hundreds of updates per second)
    // $primeClient->oneSecondPairs(['USDJPY', 'AUDCAD']);

    // For the demo, we want to see all the raw message output
    if (isset($options['debug'])) {
        $primeClient->messageLogger(
            new \Monolog\Logger('ws')
                ->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Level::Debug))
        );
    }

    // For this demo, we will use an anonymous PriceHandler that will just print the price to the console
    if (isset($options['prices'])) {
        $primeClient->priceHandler(
            new class implements \PrimeAPI\WebSocket\PriceHandlerInterface {
                public function price(string $symbol, ?string $bid, ?string $ask): void
                {
                    echo sprintf("Price for %s: Bid: %s, Ask: %s", $symbol, $bid, $ask), PHP_EOL;
                }
            }
        );
    }

    // Run
    $primeClient->start();

} catch (\Throwable $thrown) {
    echo sprintf("Failed with [%s] %s", get_class($thrown), $thrown->getMessage()), PHP_EOL;
}
