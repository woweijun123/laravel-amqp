<?php

declare(strict_types=1);

namespace Riven\Amqp\Builder;

/**
 * 交换机构建器
 */
class ExchangeBuilder extends Builder
{
    /**
     * 交换机名称
     * @var string|null
     */
    protected ?string $exchange = null;

    /**
     * 交换机类型
     * @var string|null
     */
    protected ?string $type = null;

    /**
     * 是否为内部交换机（客户端不能直接发布消息到它）
     * @var bool
     */
    protected bool $internal = false;

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
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
    public function getType(): string
    {
        return $this->type;
    }

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
     * @return bool
     */
    public function isInternal(): bool
    {
        return $this->internal;
    }

    /**
     * @param bool $internal
     * @return $this
     */
    public function setInternal(bool $internal): static
    {
        $this->internal = $internal;

        return $this;
    }
}
