<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;


use Riven\Amqp\Builder\ExchangeBuilder;
use Riven\Amqp\Exception\MessageException;

/**
 * 消息抽象类
 */
abstract class Message implements MessageInterface
{
    /**
     * 连接池名称
     * @var string
     */
    protected string $poolName = 'default';

    /**
     * 交换机
     * @var string
     */
    protected string $exchange = '';

    /**
     * 消息类型
     * @var string
     */
    protected string $type = Type::TOPIC;

    /**
     * 路由键
     * @var array|string
     */
    protected array|string $routingKey = '';

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $exchange
     * @return $this
     */
    public function setExchange(string $exchange): static
    {
        $this->exchange = $exchange;

        return $this;
    }

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * @param $routingKey
     * @return $this
     */
    public function setRoutingKey($routingKey): static
    {
        $this->routingKey = $routingKey;

        return $this;
    }

    /**
     * @return array|string
     */
    public function getRoutingKey(): array|string
    {
        return $this->routingKey;
    }

    /**
     * @return string
     */
    public function getPoolName(): string
    {
        return $this->poolName;
    }

    /**
     * @return ExchangeBuilder
     */
    public function getExchangeBuilder(): ExchangeBuilder
    {
        return (new ExchangeBuilder())->setExchange($this->getExchange())->setType($this->getType());
    }

    /**
     * 序列化消息体数据。
     * @return string
     * @throws MessageException
     */
    public function serialize(): string
    {
        throw new MessageException('You have to overwrite serialize() method.');
    }

    /**
     * 反序列化消息体数据。
     * @param string $data
     * @return mixed
     * @throws MessageException
     */
    public function unserialize(string $data): mixed
    {
        throw new MessageException('You have to overwrite unserialize() method.');
    }
}
