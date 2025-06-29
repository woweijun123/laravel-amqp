<?php declare(strict_types=1);

namespace Riven\Amqp\Enum;

enum AmqpRedisKey: string
{
    use SprintfVal;
    // AMQP消息重试次数
    case AmqpRetryCount = 'amqp:retry:count:%s';
    // AMQP已申明的交换机
    case AmqpDeclaredExchange = 'amqp:exchange';
    // AMQP已申明的队列
    case AmqpDeclaredQueue = 'amqp:queue';

    // 接口实现注解的常量名
    case Impl = 'impl';
    // 回调方法注解的常量名
    case Callee = 'callee';
    // AMQP消费者注解的常量名
    case Amqp = 'amqp';
}
