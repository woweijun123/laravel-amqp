<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;


use Riven\Amqp\Builder\ExchangeBuilder;

/**
 * 消息接口
 */
interface MessageInterface
{
    /**
     * 获取连接池名称
     * @return string
     */
    public function getPoolName(): string;

    /**
     * 设置消息类型
     * @param string $type
     * @return mixed
     */
    public function setType(string $type): mixed;

    /**
     * 获取消息类型
     * @return string
     */
    public function getType(): string;

    /**
     * 设置交换机名称
     * @param string $exchange
     * @return mixed
     */
    public function setExchange(string $exchange): mixed;

    /**
     * 获取交换机名称
     * @return string
     */
    public function getExchange(): string;

    /**
     * 设置路由键
     * @param $routingKey
     * @return mixed
     */
    public function setRoutingKey($routingKey): mixed;

    /**
     * 获取路由键
     * @return array|string
     */
    public function getRoutingKey(): array|string;

    /**
     * 获取交换机构建器
     * @return ExchangeBuilder
     */
    public function getExchangeBuilder(): ExchangeBuilder;

    /**
     * 序列化消息
     * @return string
     */
    public function serialize(): string;

    /**
     * 反序列化消息
     * @param string $data
     * @return mixed
     */
    public function unserialize(string $data): mixed;
}
