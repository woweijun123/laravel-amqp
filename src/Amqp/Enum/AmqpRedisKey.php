<?php declare(strict_types=1);

namespace Riven\Amqp\Enum;

enum AmqpRedisKey: string
{
    use SprintfVal;
    // AMQP消息重试次数
    case AmqpRetryCount = 'amqp:retry:count:%s';
}
