<?php

declare(strict_types=1);

namespace Riven\Amqp\Builder;

use PhpAmqpLib\Wire\AMQPTable;

/**
 * 构建者基类
 */
class Builder
{
    /**
     * 是否被动声明「true: 如果交换机不存在，会抛出异常。 false: 如果不存在，就创建它。」
     * @var bool
     */
    protected bool $passive = false;

    /**
     * 是否持久化
     * @var bool
     */
    protected bool $durable = true;

    /**
     * 是否自动删除
     * @var bool
     */
    protected bool $autoDelete = false;

    /**
     * 是否等待服务器响应
     * @var bool
     */
    protected bool $nowait = false;

    /**
     * 交换机参数
     * @var AMQPTable|array
     */
    protected AMQPTable|array $arguments = [];

    /**
     * 队列的访问权限凭证
     * @var int|null
     */
    protected ?int $ticket = null;

    /**
     * @return bool
     */
    public function isPassive(): bool
    {
        return $this->passive;
    }

    /**
     * @param bool $passive
     * @return $this
     */
    public function setPassive(bool $passive): static
    {
        $this->passive = $passive;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDurable(): bool
    {
        return $this->durable;
    }

    /**
     * @param bool $durable
     * @return $this
     */
    public function setDurable(bool $durable): static
    {
        $this->durable = $durable;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoDelete(): bool
    {
        return $this->autoDelete;
    }

    /**
     * @param bool $autoDelete
     * @return $this
     */
    public function setAutoDelete(bool $autoDelete): static
    {
        $this->autoDelete = $autoDelete;
        return $this;
    }

    /**
     * @return bool
     */
    public function isNowait(): bool
    {
        return $this->nowait;
    }

    /**
     * @param bool $nowait
     * @return $this
     */
    public function setNowait(bool $nowait): static
    {
        $this->nowait = $nowait;
        return $this;
    }

    /**
     * @return AMQPTable|array
     */
    public function getArguments(): AMQPTable|array
    {
        return $this->arguments;
    }

    /**
     * @param AMQPTable|array $arguments
     * @return $this
     */
    public function setArguments(AMQPTable|array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getTicket(): ?int
    {
        return $this->ticket;
    }

    /**
     * @param int|null $ticket
     * @return $this
     */
    public function setTicket(?int $ticket): static
    {
        $this->ticket = $ticket;
        return $this;
    }
}
