<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

/**
 * 消息队列交换机类型定义类
 * 该类用于定义 AMQP 协议中支持的交换机（Exchange）类型常量，
 * 并提供获取所有交换机类型的静态方法。
 */
class Type
{
    /**
     * 直接交换机类型（Direct Exchange）
     * 路由键完全匹配时消息才会被投递给绑定的队列。
     */
    public const string DIRECT = 'direct';

    /**
     * 扇形交换机类型（Fanout Exchange）
     *
     * 消息会广播到所有绑定到该交换机的队列，忽略路由键。
     */
    public const string FANOUT = 'fanout';

    /**
     * 主题交换机类型（Topic Exchange）
     *
     * 根据路由键的模式匹配规则将消息路由到对应的队列。
     */
    public const string TOPIC = 'topic';

    /**
     * 头信息交换机类型（Headers Exchange）
     *
     * 根据消息头（Headers）进行匹配，而非路由键。
     */
    public const string HEADERS = 'headers';

    /**
     * 获取所有支持的交换机类型列表
     *
     * @return array 返回包含所有交换机类型的字符串数组
     */
    public static function all(): array
    {
        return [
            self::DIRECT,
            self::FANOUT,
            self::TOPIC,
            self::HEADERS,
        ];
    }
}
