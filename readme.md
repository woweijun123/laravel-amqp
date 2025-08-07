-----

# riven/laravel-amqp

[](https://www.google.com/search?q=//packagist.org/packages/riven/laravel-amqp)
[](https://www.google.com/search?q=//packagist.org/packages/riven/laravel-amqp)
[](https://www.google.com/search?q=//packagist.org/packages/riven/laravel-amqp)

`php-amqplib` 的 Laravel 友好型封装，为 Laravel 生态系统提供 AMQP 消息队列支持。

  - [Laravel](https://github.com/laravel/laravel)
  - [Lumen](https://github.com/laravel/lumen)
  - [Laravel Zero](https://github.com/laravel-zero/laravel-zero)

-----

## 安装

安装此包，请运行以下 Composer 命令：

```bash
composer require riven/laravel-amqp
```

### Laravel

`Riven\Providers\AmqpProvider::class` 服务提供者应该会自动注册。如果未自动注册，你可以手动将其添加到 `config/app.php` 的 `providers` 数组中：

```php
// config/app.php
'providers' => [
    // ... 
    Riven\Providers\AmqpProvider::class,
]
```

发布配置文件：

```bash
php artisan vendor:publish --provider="Riven\Providers\AmqpProvider"
```

## 用法
以下示例展示了主要的使用模式：

### 步骤1：定义生产者和消费者
生产者
```php
<?php

namespace App\Amqp\Producer;


use App\Annotation\Producer;
use App\Enums\Amqp\AmqpAttr;
use App\Helper\Amqp\Message\ProducerMessage;
use App\Helper\Amqp\Message\Type;

/**
 * 公共消息生产者。
 * 用于向 RabbitMQ 的公共交换机发送消息。
 */
#[Producer]
class CommonProducer extends ProducerMessage
{
    // use ProducerDelayedMessageTrait; // 需要延迟消息时使用

    /**
     * @var string 交换机类型 (来自 `Type::FANOUT`)。
     */
    protected string $type = Type::FANOUT;

    /**
     * @var string 交换机名称 (来自 `AmqpAttr::CommonExchange`)。
     */
    public string $exchange = AmqpAttr::CommonExchange->value;

    /**
     * @var array|string 路由键 (指向 `AmqpAttr::CommonRoutingKey`)。
     */
    public array|string $routingKey = AmqpAttr::CommonRoutingKey->value;
}
```

消费者
```php
<?php

namespace App\Amqp\Consumer;


use App\Annotation\Consumer;
use App\Enums\Amqp\AmqpAttr;
use App\Helper\Amqp\Message\ConsumerMessage;
use App\Helper\Amqp\Message\Type;
use App\Helper\Amqp\Result;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * 公共消息消费者。通过注解被自动发现并注册。
 */
#[Consumer]
class CommonConsumer extends ConsumerMessage
{
    // use ConsumerDeadMessageTrait; // 死信队列「需要死信时配置」
    
    /**
     * @var string 交换机类型：FANOUT (消息广播到所有绑定队列)。
     */
    public string $type = Type::FANOUT;

    /**
     * @var string 交换机名称：使用 `AmqpAttr::CommonExchange` 定义。
     */
    public string $exchange = AmqpAttr::CommonExchange->value;

    /**
     * @var string|null 队列名称：使用 `AmqpAttr::CommonQueue` 定义。
     */
    protected ?string $queue = AmqpAttr::CommonQueue->value;

    /**
     * @var array|string 路由键：对于 FANOUT 交换机通常不生效，但仍建议提供。
     */
    public array|string $routingKey = AmqpAttr::CommonRoutingKey->value;

    /**
     * @var bool 消息处理失败时是否重回队列 (默认 true)。
     */
    protected bool $requeue = true;

    /**
     * 消费者消息处理核心逻辑。
     *
     * @param mixed $data 反序列化后的消息体数据。
     * @param AMQPMessage $message 原始 AMQP 消息对象。
     * @return Result 返回消息处理结果枚举 (如 `Result::ACK`, `Result::NACK`)。
     * @throws Exception 业务逻辑处理异常。
     */
    public function consumeMessage($data, AMQPMessage $message): Result
    {
        Log::info("公共处理消息成功", ['data' => $data]);

        // 在此编写业务逻辑

        return Result::ACK; // 标记消息处理成功
    }
}
```

### 步骤2：初始化交换机、队列、绑定关系
>只需初始化一次，后续不用重复操作。
```bash
php artisan amqp:init
```

### 步骤3：生产者发送消息
```php
// 发布普通消息
CommonProducer::send(['order_id' => 123]);
// 发布延迟消息「单位：秒」
CommonProducer::send(['order_id' => 123], delayTime: 2);
// 发布普通消息并开启发布确认
CommonProducer::send(['order_id' => 123], confirm: true);
```

### 步骤4：启动消费者进程消费
```php
# 示例
php artisan amqp:consume xxx 「xxx填写为具体对应的队列名称」

# 实例
php artisan amqp:consume common_queue
```
