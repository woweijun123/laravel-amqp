<?php

declare(strict_types=1);

namespace Riven\Amqp\Enum;

enum Amqp: int
{
    case DELIVERY_MODE_NON_PERSISTENT = 1; // 非持久化消息

    case DELIVERY_MODE_PERSISTENT = 2; // 持久化消息
}
