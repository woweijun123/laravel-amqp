<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;


use Riven\Amqp\Builder\ExchangeBuilder;

interface MessageInterface
{
    /**
     * Pool name for amqp.
     */
    public function getPoolName(): string;

    public function setType(string $type);

    public function getType(): string;

    public function setExchange(string $exchange);

    public function getExchange(): string;

    public function setRoutingKey($routingKey);

    public function getRoutingKey(): array|string;

    public function getExchangeBuilder(): ExchangeBuilder;

    /**
     * Serialize the message body to a string.
     */
    public function serialize(): string;

    /**
     * Unserialize the message body.
     */
    public function unserialize(string $data);
}
