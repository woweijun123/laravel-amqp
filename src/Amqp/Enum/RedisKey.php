<?php declare(strict_types=1);

namespace Riven\Amqp\Enum;

enum RedisKey: string
{
    use SprintfVal;
    // AMQP消息重试次数
    case AmqpRetryCount = 'a:r:c:%s';
    // AMQP已申明的交换机
    case AmqpDeclaredExchange = 'a:d:e';
    // AMQP已申明的队列
    case AmqpDeclaredQueue = 'a:d:q';
}
