<?php

namespace Riven\Amqp;

use App\Enums\Amqp\AmqpAttr;

class AmqpCollector
{
    protected static array $producer = [];
    protected static array $consumer = [];

    /**
     * 添加消费者
     * @param array $data
     * @return void
     */
    public static function setProducer(array $data): void
    {
        static::$consumer = $data;
    }

    /**
     * 获取消费者
     * @param AmqpAttr $queue
     * @return array|null
     */
    public static function getProducer(AmqpAttr $queue): ?array
    {
        return static::$consumer[$queue->value] ?? null;
    }

    /**
     * 检查是否存在对应的消费者
     * @param AmqpAttr $queue
     * @return bool
     */
    public static function hasProducer(AmqpAttr $queue): bool
    {
        return isset(static::$consumer[$queue->value]);
    }

    /**
     * 获取所有消费者
     * @return array|null
     */
    public static function allProducer(): ?array
    {
        return static::$consumer;
    }

    /**
     * 添加消费者
     * @param array $data
     * @return void
     */
    public static function setConsumer(array $data): void
    {
        static::$consumer = $data;
    }

    /**
     * 获取消费者
     * @param AmqpAttr $queue
     * @return array|null
     */
    public static function getConsumer(AmqpAttr $queue): ?array
    {
        return static::$consumer[$queue->value] ?? null;
    }

    /**
     * 检查是否存在对应的消费者
     * @param AmqpAttr $queue
     * @return bool
     */
    public static function hasConsumer(AmqpAttr $queue): bool
    {
        return isset(static::$consumer[$queue->value]);
    }

    /**
     * 获取所有消费者
     * @return array|null
     */
    public static function allConsumer(): ?array
    {
        return static::$consumer;
    }
}
