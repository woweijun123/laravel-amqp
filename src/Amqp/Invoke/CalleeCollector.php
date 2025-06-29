<?php

namespace Riven\Amqp\Invoke;

use ReflectionException;
use Riven\Amqp\Annotation\Mapper;

class CalleeCollector
{
    protected static array $container = [];

    /**
     * 添加被调用方法到容器
     * @param array             $callable [类名, 方法名]
     * @param CalleeEvent|array $event    事件枚举 或 [命名空间, 事件名] 格式的数组
     * @param string|null       $scope    作用域
     * @return void
     */
    public static function addCallee(array $callable, CalleeEvent|array $event, ?string $scope = null): void
    {
        try {
            $parameters = Reflection::reflectMethod(...$callable)->getParameters();
        } catch (ReflectionException) {
            $parameters = [];
        }

        $mapper = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $reflectionAttributes = $parameter->getAttributes(Mapper::class);
            foreach ($reflectionAttributes as $attribute) {
                $instance = $attribute->newInstance();
                foreach ($instance->src as $src) {
                    $mapper[$src] = $name;
                }
            }
            // 默认参数名优先级最低
            $mapper[$name] = $name;
        }

        [$namespace, $eventName] = self::getEvent($event, $scope);

        static::$container[$namespace][$eventName] = [$callable, $mapper];
    }

    /**
     * 检查是否存在对应的调用方法
     * @param CalleeEvent|array|null $event 事件枚举 或 [命名空间, 事件名] 格式的数组
     * @param string|null            $scope 作用域
     * @return bool
     */
    public static function hasCallee(CalleeEvent|array|null $event, ?string $scope = null): bool
    {
        if (!$event) {
            return false;
        }

        [$namespace, $event] = self::getEvent($event, $scope);

        return isset(static::$container[$namespace][$event]);
    }

    /**
     * 获取调用方法及参数映射
     * @param CalleeEvent|array|null $event 事件枚举 或 [命名空间, 事件名] 格式的数组
     * @param string|null            $scope 作用域
     * @return array|null
     */
    public static function getCallee(CalleeEvent|array|null $event, ?string $scope = null): ?array
    {
        if (!$event) {
            return null;
        }

        [$namespace, $event] = self::getEvent($event, $scope);

        return static::$container[$namespace][$event] ?? null;
    }

    /**
     * 解析事件命名空间和事件名
     * @param CalleeEvent|array $event 事件枚举 或 [命名空间, 事件名] 格式的数组
     * @param string|null       $scope 作用域
     * @return array
     */
    public static function getEvent(CalleeEvent|array $event, ?string $scope = null): array
    {
        $result = is_array($event) ? $event : [$event->namespace(), $event->event()];

        return [$scope ?: $result[0], $result[1]];
    }

    /**
     * 清除容器数据
     * @param string|null $key 类名（可选）
     * @return void
     */
    public static function clear(?string $key = null): void
    {
        if ($key) {
            foreach (static::$container as $namespace => $events) {
                foreach ($events as $event => $callable) {
                    if ($callable[0][0] === $key) {
                        unset(static::$container[$namespace][$event]);
                    }
                }
            }
        } else {
            static::$container = [];
        }
    }
}
