<?php

namespace Test\Cases\Invoke;
use Illuminate\Support\Arr;
use ReflectionClass;
use Riven\Amqp\Annotation\Mapper;
use Riven\Amqp\Invoke\CalleeCollector;
use Riven\Amqp\Invoke\CalleeEvent;
use Riven\Amqp\Invoke\CalleeEventTrait;
use Riven\Amqp\Invoke\Reflection;
use Test\Cases\TestCase;

class InvokeTest extends TestCase
{
    protected function setUp(): void
    {
        // 模拟注解
        CalleeCollector::addCallee([CalleeClass::class, 'service'], Event::service);
        CalleeCollector::addCallee([CalleeClass::class, 'ref'], Event::ref);
    }

    public function testReflection()
    {
        $className = CalleeClass::class;
        $method = 'service';
        // 测试反射参数
        $reflectParameters = Reflection::reflectParameters($className, $method);
        echo 'wcj-p', PHP_EOL;
        var_dump($reflectParameters);

        $this->assertTrue(true);
    }

    public function testInvoke()
    {
        // $ret = Reflection::invoke([CalleeClass::class, 'service']);
        // $this->assertNull($ret['uid']);
        // $this->assertNull($ret['name']);

        $ret = Reflection::invoke([CalleeClass::class, 'service'], ['name' => 'abc', 'uid' => 123]);
        $this->assertEquals(123, $ret['uid']);
        $this->assertEquals('abc', $ret['name']);

        // $user = new StatusDO();
        // Reflection::invoke([CalleeClass::class, 'ref'], ['user' => $user]);
        // dump($user);
        $this->assertTrue(true);
    }

    public function testCallee()
    {
        $user = [
            'user' => ['name' => 'aaa'],
            'user_id' => 666,
        ];
        $ret = Reflection::call(Event::service, $user);
        $this->assertEquals(Arr::get($user, 'user.name'), $ret['name']);
        $this->assertEquals($user['user_id'], $ret['uid']);
        $this->assertEquals(1, $ret['groupId']);
    }

    public function testCalleeStrict()
    {
        $user = [
            'user' => ['name' => 'aaa'],
            'id' => 999,
            'user_id' => 666,
            'group' => 0,
        ];
        $ret = Reflection::call(Event::service, $user, strict: true);

        $this->assertEquals(Arr::get($user, 'user.name'), $ret['name']);
        $this->assertEquals($user['id'], $ret['uid']);
        $this->assertEquals(1, $ret['groupId']);
    }
}

enum Event implements CalleeEvent
{
    use CalleeEventTrait;

    case service;

    case ref;
}

class CalleeClass
{
    public function service(#[Mapper('id', 'user_id')] $uid, #[Mapper('user.name')] $name, #[Mapper('group')] $groupId = 1): array
    {
        return compact('uid', 'name', 'groupId');
    }

    public function ref(): void
    {
    }
}
