<?php

namespace Riven\Amqp\Invoke;

use Riven\Amqp\Invoke\Exception\InvokeException;
use BackedEnum;
use UnitEnum;

trait CalleeEventTrait
{
    /**
     * 获取事件的命名空间
     * @return string
     */
    public function namespace(): string
    {
        return static::class;
    }

    /**
     * 获取事件的字符串名称
     * @return string
     * @throws
     */
    public function event(): string
    {
        return match (true) {
            $this instanceof BackedEnum => $this->value, // 有值枚举
            $this instanceof UnitEnum => $this->name, // 无值枚举
            default => throw new InvokeException('This trait must in enum'),
        };
    }
}
