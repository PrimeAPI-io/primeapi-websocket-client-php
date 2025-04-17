<?php

declare(strict_types=1);

namespace PrimeAPI\WebSocket;

use Psr\Log\LoggerInterface;
use WebSocket\Client as WebSocketClient;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;

class Client
{
    public const string
        OP_AUTH = 'auth',
        OP_SUBSCRIBE = 'subscribe',
        OP_UNSUBSCRIBE = 'unsubscribe',
        OP_SHUTDOWN = 'shutdown',
        OP_PRICE = 'price',
        OP_STATUS = 'status',
        OP_CONNECT = 'connect',
        OP_ERROR = 'error';

    public const string
        STREAM_REALTIME = 'fx',
        STREAM_ONE_SEC = 'fx1s';

    protected ?string $apiKey = null;
    protected array $realTimePairs = [];
    protected array $oneSecondPairs = [];
    protected ?LoggerInterface $messageLogger = null;

    /**
     * @var PriceHandlerInterface[]
     */
    protected array $priceHandlers = [];

    private WebSocketClient $webSocketClient;

    public function __construct(
        private readonly string $webSocketUrl
    ) {
        $this->apiKey = getenv('PRIME_API_KEY') ?: null;
        $this->initWebSocketClient();
    }

    protected function initWebSocketClient(): void
    {
        $this->webSocketClient = new WebSocketClient($this->webSocketUrl);
        $this->webSocketClient->setTimeout(5);
    }

    public function apiKey(string $apiKey): self
    {
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        }
        return $this;
    }

    public function realTimePairs(array $realTimePairs): self
    {
        $this->realTimePairs = $realTimePairs;
        return $this;
    }

    public function oneSecondPairs(array $oneSecondPairs): self
    {
        $this->oneSecondPairs = $oneSecondPairs;
        return $this;
    }

    public function messageLogger(LoggerInterface $logger): self
    {
        $this->messageLogger = $logger;
        return $this;
    }

    public function priceHandler(PriceHandlerInterface $handler): self
    {
        $this->priceHandlers[] = $handler;
        return $this;
    }

    public function start(): void
    {
        $this->webSocketClient
            ->addMiddleware(new CloseHandler())
            ->addMiddleware(new PingResponder())
            ->onText(function (WebSocketClient $webSocketClient, Connection $connection, Message $message) {
                $this->onMessage($message);
            })
            ->start();
    }

    public function onMessage(Message $message): void
    {
        $this->messageLogger?->debug('Got message: ' . $message->getContent());
        $payload = \json_decode($message->getPayload(), true);
        match ($payload['op'] ?? 'unknown') {
            self::OP_CONNECT    => $this->handleConnect($payload),
            self::OP_AUTH       => $this->handleAuth($payload),
            self::OP_STATUS     => $this->handleStatus($payload),
            self::OP_SUBSCRIBE  => $this->handleSubscribe($payload),
            self::OP_SHUTDOWN   => $this->handleShutdown($payload),
            self::OP_PRICE      => $this->handlePrice($payload),
            default             => throw new \InvalidArgumentException(
                sprintf('Unknown message type received [%s]', $payload['op'] ?? '-')
            ),
        };
    }

    protected function handleConnect(array $payload): void
    {
        $this->expectOKStatus($payload);
        $this->sendAuth();
    }

    protected function sendAuth(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Client cannot send auth message - API key not set');
        }
        $this->webSocketClient->text(\json_encode([
            'op' => self::OP_AUTH,
            'key' => $this->apiKey,
        ]));
    }

    protected function handleAuth(array $payload): void
    {
        $this->expectOKStatus($payload);
        $this->sendSubscribe();
    }

    protected function sendSubscribe(): void
    {
        foreach ([
            self::STREAM_REALTIME => array_filter($this->realTimePairs),
            self::STREAM_ONE_SEC => array_filter($this->oneSecondPairs),
         ] as $stream => $pairs) {
            if (!empty($pairs)) {
                $this->webSocketClient->text(\json_encode([
                    'op' => self::OP_SUBSCRIBE,
                    'stream' => $stream,
                    'pairs' => $pairs,
                ]));
            }
        }
    }

    protected function handlePrice(array $payload): void
    {
        foreach ($this->priceHandlers as $handler) {
            $handler->price(
                (string) $payload['sym'] ?? '',
                (string) $payload['bid'] ?? '',
                (string) $payload['ask'] ?? '',
            );
        }
    }

    protected function handleStatus(array $payload): void
    {
        // noop for now
    }

    protected function handleSubscribe(array $payload): void
    {
        // noop for now
    }

    protected function handleShutdown(array $payload): void
    {
        // noop for now
    }

    protected function expectOKStatus(array $payload): void
    {
        if (200 === (int) ($payload['status'] ?? 0)) {
            return;
        }
        throw new \UnexpectedValueException(sprintf(
            'Unexpected/missing status code received [%d]',
            $payload['status'] ?? 0
        ));
    }
}
