<?php

declare(strict_types=1);

namespace Riven\Amqp\Builder;

class ExchangeBuilder extends Builder
{
    protected ?string $exchange = null;

    protected ?string $type = null;

    protected bool $internal = false;

    public function getExchange(): string
    {
        return $this->exchange;
    }

    public function setExchange(string $exchange): static
    {
        $this->exchange = $exchange;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool $internal): static
    {
        $this->internal = $internal;

        return $this;
    }
}
