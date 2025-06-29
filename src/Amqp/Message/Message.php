<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;


use Riven\Amqp\Builder\ExchangeBuilder;
use Riven\Amqp\Exception\MessageException;

abstract class Message implements MessageInterface
{
    protected string $poolName = 'default';

    protected string $exchange = '';

    protected string $type = Type::TOPIC;

    protected array|string $routingKey = '';

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setExchange(string $exchange): static
    {
        $this->exchange = $exchange;

        return $this;
    }

    public function getExchange(): string
    {
        return $this->exchange;
    }

    public function setRoutingKey($routingKey): static
    {
        $this->routingKey = $routingKey;

        return $this;
    }

    public function getRoutingKey(): array|string
    {
        return $this->routingKey;
    }

    public function getPoolName(): string
    {
        return $this->poolName;
    }

    public function getExchangeBuilder(): ExchangeBuilder
    {
        return (new ExchangeBuilder())->setExchange($this->getExchange())->setType($this->getType());
    }

    /**
     * @throws MessageException
     */
    public function serialize(): string
    {
        throw new MessageException('You have to overwrite serialize() method.');
    }

    /**
     * @throws MessageException
     */
    public function unserialize(string $data)
    {
        throw new MessageException('You have to overwrite unserialize() method.');
    }
}
