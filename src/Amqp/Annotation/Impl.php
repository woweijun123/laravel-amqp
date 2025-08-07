<?php

namespace Riven\Amqp\Annotation;

use Attribute;

/**
 * 接口实现类的注解，等于 config/autoload/dependencies.php 文件添加配置
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Impl
{
}
