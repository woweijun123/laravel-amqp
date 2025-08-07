<?php

namespace Riven\Amqp\Annotation;

use Attribute;

/**
 * 参数映射转换
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Mapper
{
    /**
     * @var array|string[] 来源 key
     */
    public array $src;

    /**
     * @param string ...$src 当前参数的来源 key
     */
    public function __construct(string ...$src)
    {
        $this->src = $src;
    }
}
