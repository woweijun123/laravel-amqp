<?php

declare(strict_types=1);

namespace Riven\Amqp\Builder;

use PhpAmqpLib\Wire\AMQPTable;

/**
 * 队列构建器
 */
class QueueBuilder extends Builder
{
    /**
     * 队列名称
     * @var string|null
     */
    protected ?string $queue = null;

    /**
     * 是否独占
     * @var bool
     */
    protected bool $exclusive = false;

    /**
     * 队列参数
     * @var AMQPTable|array|array[]
     */
    protected AMQPTable|array $arguments = [
        'x-ha-policy' => ['S', 'all'],
    ];

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     * @return $this
     */
    public function setQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @return bool
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * @param bool $exclusive
     * @return $this
     */
    public function setExclusive(bool $exclusive): self
    {
        $this->exclusive = $exclusive;

        return $this;
    }
}
