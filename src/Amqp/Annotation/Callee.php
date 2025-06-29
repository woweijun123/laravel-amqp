<?php

namespace Riven\Amqp\Annotation;

use Riven\Amqp\Invoke\CalleeEvent;
use Attribute;

/**
 * 注解调用
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Callee
{
    public function __construct(public CalleeEvent|array $event, public ?string $scope = null)
    {
    }
}
