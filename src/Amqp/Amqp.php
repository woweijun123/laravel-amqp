<?php

declare(strict_types=1);

namespace Riven\Amqp;

class Amqp
{
    public const int DELIVERY_MODE_NON_PERSISTENT = 1; // 非持久化消息

    public const int DELIVERY_MODE_PERSISTENT = 2; // 持久化消息
}
