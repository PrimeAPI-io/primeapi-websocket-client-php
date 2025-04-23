# PrimeAPI.io PHP WebSocket Client

This repository contains a reference implementation of a WebSocket client for the PrimeAPI.io streaming FX data service.

It also contains a basic CLI demo script.

## Get An API Key
If you don't already have an API key, you can get one by signing up for free at https://console.primeapi.io

## Installing the Client in your Application
```bash
composer install primeapi/php-websocket-client
```

### Using the Client
You're going to need a Price Handler - a class that implements the `PriceHandlerInterface` interface. This is where you will handle the incoming price data from the WebSocket server.

Here's a simple anonymous class implementation that just echos out the prices.

```php
$priceHandler = new class implements \PrimeAPI\WebSocket\PriceHandlerInterface {
    public function price(string $symbol, ?string $bid, ?string $ask): void
    {
        echo sprintf("Price for %s: Bid: %s, Ask: %s", $symbol, $bid, $ask), PHP_EOL;
    }
};
```

Creating and starting a WebSocket client is super simple.
```php
$primeClient = new \PrimeAPI\WebSocket\Client();
    ->apiKey('your-api-key');
    ->realTimePairs(['USDJPY', 'AUDCAD']);
    ->priceHandler($priceHandler);
    ->start();
```

## Running as a Demo

The client contains a demo script that can be run to test the connection to the PrimeAPI.io WebSocket server. 

You can install and run locally, or use our pre built Docker image.

### CLI arguments

- `--key=your-api-key` - Your PrimeAPI.io API key
- `--pairs=EURUSD,GBPUSD` - A comma separated list of currency pairs to subscribe to (default: `EURUSD`)
- `--prices` - Only show price bid/ask outputs
- `--debug` - (default) Show all WebSocket messages, including connect, auth, prices etc.

### Run The Demo With Docker
```bash
docker run --rm -e PRIME_API_KEY=your_api_key primeimg/php-websocket-client:latest
```
Or, if you want to provide arguments
```bash
docker run --rm primeimg/php-websocket-client:latest php /app/bin/demo.php --key=your_api_key --pairs=EURUSD,GBPUSD
```

### Install & Run Demo Locally
```bash
composer install
```
```bash
export PRIME_API_KEY=your_api_key
```
```bash
php bin/demo.php
```
And with arguments
```bash
php bin/demo.php --key=your_api_key --pairs=EURUSD,GBPUSD
```

### Expected CLI Demo Output
```text
Got message: {"op":"connect","status":200,"tsp":1744914599148,"msg":"Connected to ws-grp-prime-euc2-hjf5"}
Got message: {"op":"auth","status":200,"tsp":1744914599299,"msg":"Authenticated OK"}
Got message: {"op":"subscribe","status":200,"tsp":1744914599389,"msg":"Subscribed to 1 channel"}
Got message: {"op":"price","sym":"EURUSD","bid":"1.13575","ask":"1.13577"}
Got message: {"op":"price","sym":"EURUSD","bid":"1.1357","ask":"1.1358"}
Got message: {"op":"price","sym":"EURUSD","bid":"1.13569","ask":"1.1358"}
Got message: {"op":"price","sym":"EURUSD","bid":"1.13574","ask":"1.13575"}
```

