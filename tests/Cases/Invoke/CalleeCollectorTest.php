<?php

namespace Test\Cases\Invoke;


use PHPUnit\Framework\Attributes\Depends;
use Riven\Amqp\Invoke\CalleeCollector;
use Riven\Amqp\Invoke\CalleeEvent;
use Riven\Amqp\Invoke\CalleeEventTrait;
use Test\Cases\TestCase;

class CalleeCollectorTest extends TestCase
{
    public function testAddCallee()
    {
        // CalleeCollector::addCallee([__CLASS__, __FUNCTION__], CalleeEventEnum::user);
        // CalleeCollector::addCallee([__CLASS__, __FUNCTION__], CalleeEventEnumString::add);
        // CalleeCollector::addCallee([__CLASS__, 'del'], CalleeEventEnumString::delete);
        //
        CalleeCollector::addCallee([__CLASS__, __FUNCTION__], CalleeEventEnum::user, scope: self::class);
        // CalleeCollector::addCallee([__CLASS__, __FUNCTION__], CalleeEventEnumString::add, scope: self::class);
        // CalleeCollector::addCallee([__CLASS__, 'del'], CalleeEventEnumString::delete, scope: self::class);
        //
        // CalleeCollector::addCallee([__CLASS__, __FUNCTION__], [self::class, 'user']);
        // CalleeCollector::addCallee([__CLASS__, __FUNCTION__], [self::class, 'user'], scope: self::class);


        $this->assertTrue(true);
    }

    /**
     * @depends testAddCallee
     * @return void
     */
    public function testHasCallee(): void
    {
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnum::user));
        // $this->assertNotTrue(CalleeCollector::hasCallee(CalleeEventEnum::member));

        $hasCallee = CalleeCollector::hasCallee(CalleeEventEnum::user, self::class);
        $this->assertTrue($hasCallee);
        // $this->assertNotTrue(CalleeCollector::hasCallee(CalleeEventEnum::member, self::class));

        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::add));
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::delete));
        // $this->assertNotTrue(CalleeCollector::hasCallee(CalleeEventEnumString::update));
        //
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::add, self::class));
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::delete, self::class));
        // $this->assertNotTrue(CalleeCollector::hasCallee(CalleeEventEnumString::update, self::class));
        //
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnum::group));
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::list));
        //
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnum::group, self::class));
        // $this->assertTrue(CalleeCollector::hasCallee(CalleeEventEnumString::list, self::class));
        //
        // $this->assertTrue(CalleeCollector::hasCallee([self::class, 'user']));
        // $this->assertTrue(CalleeCollector::hasCallee([self::class, 'user'], self::class));
    }

    /**
     * @depends testAddCallee
     * @return void
     */
    #[Depends('testAddCallee')] public function testGetCallee()
    {
        $callee = CalleeCollector::getCallee(CalleeEventEnum::user);
        dd($callee);
        $this->assertEquals([__CLASS__, 'testAddCallee'], $callee[0]);
        // $this->assertEquals([__CLASS__, 'testAddCallee'], CalleeCollector::getCallee(CalleeEventEnumString::add)[0]);
        // $this->assertEquals([__CLASS__, 'del'], CalleeCollector::getCallee(CalleeEventEnumString::delete)[0]);
        //
        // $this->assertEquals([__CLASS__, 'testAddCallee'], CalleeCollector::getCallee(CalleeEventEnum::user, self::class)[0]);
        // $this->assertEquals([__CLASS__, 'testAddCallee'], CalleeCollector::getCallee(CalleeEventEnumString::add, self::class)[0]);
        // $this->assertEquals([__CLASS__, 'del'], CalleeCollector::getCallee(CalleeEventEnumString::delete, self::class)[0]);
        //
        // $this->assertEquals([__CLASS__, 'group'], CalleeCollector::getCallee(CalleeEventEnum::group)[0]);
        // $this->assertEquals([__CLASS__, 'list'], CalleeCollector::getCallee(CalleeEventEnumString::list)[0]);
        //
        // $this->assertEquals([__CLASS__, 'group'], CalleeCollector::getCallee(CalleeEventEnum::group, self::class)[0]);
        // $this->assertEquals([__CLASS__, 'list'], CalleeCollector::getCallee(CalleeEventEnumString::list, self::class)[0]);
        //
        // $this->assertEquals([__CLASS__, 'list'], CalleeCollector::getCallee(CalleeEventEnumString::tryFrom('list'))[0]);
        // $this->assertEquals([__CLASS__, 'list'], CalleeCollector::getCallee(CalleeEventEnumString::tryFrom('list'), self::class)[0]);
        //
        // $this->assertEquals([__CLASS__, 'testAddCallee'], CalleeCollector::getCallee([static::class, 'user'])[0]);
        // $this->assertEquals([__CLASS__, 'testAddCallee'], CalleeCollector::getCallee([static::class, 'user'], self::class)[0]);
        //
        // $event = 'abc';
        // $this->assertNull(CalleeCollector::getCallee(CalleeEventEnumString::tryFrom($event)));
        // $this->assertNull(CalleeCollector::getCallee(CalleeEventEnumString::tryFrom($event), self::class));
    }
}

enum CalleeEventEnum implements CalleeEvent
{
    use CalleeEventTrait;

    case user;

    case member;

    case group;
}

enum CalleeEventEnumString: string implements CalleeEvent
{
    use CalleeEventTrait;

    case add = 'add';

    case update = 'update';

    case delete = 'delete';

    case list = 'list';
}

