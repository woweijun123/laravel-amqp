<?php

namespace Riven\Amqp\Invoke;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

class Reflection
{
    /**
     * 缓存容器数据
     * @var array
     */
    protected static array $container = [];

    /**
     * 注解反射调用，根据 Callee 注解调用相关函数
     * @param CalleeEvent|array|null $event   事件枚举 或 [命名空间, 事件名] 格式的数组
     * @param array                  $args    参数
     * @param bool                   $strict  是否严格模式，为true时，空参数都会使用默认值
     * @param mixed|null             $default 默认值，注解方法不存在时返回
     * @param string|null            $scope   作用域
     * @return mixed
     */
    public static function call(CalleeEvent|array|null $event, array $args = [], bool $strict = false, mixed $default = null, ?string $scope = null): mixed
    {
        if (!$callable = CalleeCollector::getCallee($event, $scope)) {
            return value($default, $args);
        }

        return self::invoke($callable[0], $args, $callable[1], $strict);
    }

    /**
     * callable 方式反射调用
     * @param array $callable callable 调用数组
     * @param array $args   参数
     * @param array $mapper 参数映射，格式 源参数 key => 转换后的目标参数 key
     * @param bool  $strict 是否严格模式，为true时，空参数都会使用默认值
     * @return mixed
     * @throws
     */
    public static function invoke(array $callable, array $args = [], array $mapper = [], bool $strict = false): mixed
    {
        [$class, $method] = $callable;
        if (is_string($class)) {
            $class = Container::getInstance()->make($class);
        }
        $className = get_class($class);
        // 返回获取的默认参数值
        $reflect = static::reflectParameters($className, $method);
        $parameters = [];
        $missingValue = Str::random(10);
        // 转换参数映射
        $argsMapper = [];
        foreach ($mapper as $src => $target) {
            $val = Arr::get($args, $src, $missingValue);
            if ($val !== $missingValue && !isset($argsMapper[$target])) {
                $argsMapper[$target] = $val;
            }
        }
        // 没有映射时使用原参数
        $argsMapper = $argsMapper ?: $args;
        foreach ($reflect as $name => $default) {
            // 参数值获取顺序：注解key -> 参数key -> 默认值或null
            $val = Arr::get($argsMapper, $name, $default);
            $parameters[] = $strict ? ($val ?: $default) : $val;
        }

        return $class->$method(...$parameters);
    }

    /**
     * 反射类/接口/trait。
     * 获取并缓存 ReflectionClass 实例。
     * @param string $className 类、接口或 trait 的完全限定名。
     * @return ReflectionClass ReflectionClass 实例。
     * @throws InvalidArgumentException 如果类/接口/trait 不存在。
     */
    public static function reflectClass(string $className): ReflectionClass
    {
        if (!isset(static::$container['class'][$className])) {
            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                throw new InvalidArgumentException("Class {$className} not exist");
            }
            static::$container['class'][$className] = new ReflectionClass($className);
        }

        return static::$container['class'][$className];
    }

    /**
     * 反射方法。
     * 获取并缓存 ReflectionMethod 实例。
     * @param string $className 方法所属的类或 trait 名。
     * @param string $method    要反射的方法名。
     * @return ReflectionMethod ReflectionMethod 实例。
     * @throws InvalidArgumentException 如果类/trait 不存在。
     * @throws ReflectionException 如果方法不存在。
     */
    public static function reflectMethod(string $className, string $method): ReflectionMethod
    {
        $key = $className . '::' . $method;
        if (!isset(static::$container['method'][$key])) {
            if (!class_exists($className) && !trait_exists($className)) {
                throw new InvalidArgumentException("Class $className not exist");
            }
            if (!method_exists($className, $method)) {
                throw new InvalidArgumentException("Method $method does not exist in class $className.");
            }
            static::$container['method'][$key] = static::reflectClass($className)->getMethod($method);
        }

        return static::$container['method'][$key];
    }

    /**
     * 反射属性。
     * 获取并缓存 ReflectionProperty 实例。
     * @param string $className 属性所属的类名。
     * @param string $property  要反射的属性名。
     * @return ReflectionProperty ReflectionProperty 实例。
     * @throws InvalidArgumentException 如果类不存在。
     * @throws ReflectionException 如果属性不存在。
     */
    public static function reflectProperty(string $className, string $property): ReflectionProperty
    {
        $key = $className . '::' . $property;
        if (!isset(static::$container['property'][$key])) {
            static::$container['property'][$key] = static::reflectClass($className)->getProperty($property);
        }

        return static::$container['property'][$key];
    }

    /**
     * 反射方法参数。
     * 获取并缓存方法参数名及其默认值。
     * @param string $className 方法所属的类名。
     * @param string $method    要反射参数的方法名。
     * @return array 参数名 => 默认值的关联数组。
     * @throws ReflectionException 如果方法不存在。
     */
    public static function reflectParameters(string $className, string $method): array
    {
        $key = $className . '::' . $method;
        if (!isset(static::$container['parameter'][$key])) {
            $reflectionParameters = static::reflectMethod($className, $method)->getParameters();
            static::$container['parameter'][$key] = array_reduce($reflectionParameters, function ($carry, $param) {
                $carry[$param->getName()] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                return $carry;
            });
        }
        return static::$container['parameter'][$key];
    }
}
