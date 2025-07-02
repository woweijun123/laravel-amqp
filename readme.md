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

### Lumen

1.  在 `bootstrap/app.php` 文件中注册 `Riven\Providers\AmqpProvider::class` 服务提供者：

    ```php
    // bootstrap/app.php
    $app->register(Riven\Providers\AmqpProvider::class);
    ```

2.  从 `vendor/riven/laravel-amqp/src/config/amqp.php` 复制配置文件 `amqp.php` 到你的 `config` 目录。

3.  在 `bootstrap/app.php` 中使用 `$app->configure('amqp');` 导入你的配置：

    ```php
    // bootstrap/app.php
    $app->configure('amqp');
    ```

### Laravel Zero

1.  在 `config/app.php` 的 `providers` 数组中注册 `Riven\Providers\AmqpProvider::class` 服务提供者：

    ```php
    // config/app.php
    'providers' => [
        // ... 
        Riven\Providers\AmqpProvider::class,
    ]
    ```

2.  从 `vendor/riven/laravel-amqp/src/config/amqp.php` 复制配置文件 `amqp.php` 到你的 `config` 目录中。

-----

## 配置

在发布的 `config/amqp.php` 配置文件中，你可以定义多个 AMQP 连接。你可以通过指定连接名称，在代码中轻松切换和使用这些连接。

以下是一些重要的配置项：

- **`amqp.default`**: 定义默认连接的名称。当你在发布或消费消息时未指定连接时，将使用此默认连接。
- **`amqp.connections.<name>.connection.class`**: 指定底层 AMQP 连接的类。默认使用惰性连接（lazy connection）。你可以将其更改为任何实现 `PhpAmqpLib\Connection\AbstractConnection` 的类。
- **`amqp.connections.<name>.connection.hosts`**: 可以包含多个主机配置。每个主机配置必须包含 `host`、`port`、`user`、`password` 键。可选的 `vhost` 键也可以包含。**惰性连接只支持一个主机配置**，否则会抛出错误。
- **`amqp.connections.<name>.connection.options`**: 内部创建连接实例时，可以传入一个可选的参数数组。
- **`amqp.connections.<name>.message`**: 定义发布消息时的默认属性。
- **`amqp.connections.<name>.exchange`**: 定义发布和消费时交换机的默认属性。
- **`amqp.connections.<name>.queue`**: 定义消费时队列的默认属性。
- **`amqp.connections.<name>.consumer`**: 定义消费时消费者的默认属性。
- **`amqp.connections.<name>.qos`**: 定义消费时 QoS (Quality of Service) 的默认属性。

### Octane 支持

本包对 Laravel Octane 提供了开箱即用的支持。为了**保持 AMQP 连接的活跃**，你需要在 Octane 配置中将 `'amqp'` 添加到 `warm` 数组中：

```php
// config/octane.php
// ... 
'warm' => [
    // ... 
    'amqp', // <-- 添加这一行
],
```

-----

## 用法

你可以通过多种方式来使用 `riven/laravel-amqp` 包，以下示例展示了主要的使用模式：

```php
use Riven\Amqp\ConsumableMessage; // 请注意这里的命名空间
use Riven\Laravel\Amqp\Facades\Amqp;

$messages = 'Hello, RabbitMQ!';
// $messages = ['first message', 'second message'];
// $messages = new Riven\Amqp\ProducibleMessage('custom message');
// $messages = ['another message', new Riven\Amqp\ProducibleMessage('yet another message')];

// 发布消息
Amqp::publish($messages); // 发布到默认连接
Amqp::connection('my_rabbitmq_connection')->publish($messages); // 发布到名为 'my_rabbitmq_connection' 的连接

// 也可以通过 Laravel 的服务容器获取实例
app('amqp')->publish($messages);
app('amqp')->connection('my_rabbitmq_connection')->publish($messages);

app()->make('amqp')->publish($messages);
app()->make('amqp')->connection('my_rabbitmq_connection')->publish($messages);

/** @var \Riven\Laravel\Amqp\AmqpManager $amqpManager */
// $amqpManager->publish($messages); 
// $amqpManager->connection('my_rabbitmq_connection')->publish($messages);

// 消费消息
Amqp::consume(function(ConsumableMessage $message) {
    echo "Received: " . $message->getMessageBody() . PHP_EOL;
    $message->ack(); // 确认消息
}); // 从默认连接消费

Amqp::connection('my_rabbitmq_connection')->consume(function(ConsumableMessage $message) {
    echo "Received from custom connection: " . $message->getMessageBody() . PHP_EOL;
    $message->ack();
}); // 从名为 'my_rabbitmq_connection' 的连接消费
```

### 注意

本说明文档后续将主要使用 **Facade** 示例。如果你在使用 **Lumen**，可以使用 `app('amqp')` 或 `app()->make('amqp')` 等其他方法。本包**不强制要求启用 Facade**。

### 发布消息

要发布消息，请使用 `publish` 方法：

```php
use Riven\Laravel\Amqp\Facades\Amqp;

Amqp::publish($messages, $routingKey, $exchange, $options);
Amqp::connection('my_rabbitmq_connection')->publish($messages, $routingKey, $exchange, $options);
```

- **`$messages`**: `mixed` 类型，**必需**。可以是单个消息，也可以是任何标量类型或 `Riven\Amqp\Producible` 接口实现的数组。
- **`$routingKey`**: `string` 类型，**可选**。默认值为 `''` (空字符串)。
- **`$exchange`**: `null | Riven\Amqp\Exchanges\Exchange` 类型，**可选**。默认值为 `null`。
- **`$options`**: `array` 类型，**可选**。默认值为 `[]`。
    * 键 `message` - 接受 `array` 类型。用于 `PhpAmqpLib\Message\AMQPMessage` 的有效属性。
    * 键 `exchange` - 接受 `array` 类型。参考 `amqp.connections.<name>.exchange` 配置。
    * 键 `publish` - 接受 `array` 类型。参考核心库的 `Riven\Amqp\Producer::publishBatch` 方法的文档。

**额外说明：**

- 如果 `$messages` 中的任何项不是 `Riven\Amqp\Producible` 的实现，它将使用 `Riven\Amqp\ProducibleMessage` 自动转换为 `Riven\Amqp\Producible`。
- 转换时，将尝试使用 `$options['message']` 作为消息属性。如果未设置，则会使用 `amqp.connections.<name>.message` 中的默认属性。
- 如果 `$exchange` 设置为 `null`，将检查 `$options['exchange']`。如果未设置，则会使用 `amqp.connections.<name>.exchange` 中的默认属性。
- 如果 `$options['publish']` 未设置，则会使用 `amqp.connections.<name>.publish` 中的默认属性。

### 消费消息

要消费消息，请使用 `consume` 方法：

```php
use Riven\Laravel\Amqp\Facades\Amqp;

Amqp::consume($handler, $bindingKey, $exchange, $queue, $qos, $options);
Amqp::connection('my_rabbitmq_connection')->consume($handler, $bindingKey, $exchange, $queue, $qos, $options);
```

- **`$handler`**: `callable | Riven\Amqp\Consumable` 类型，**必需**。一个回调函数或 `Riven\Amqp\Consumable` 的实现。
- **`$bindingKey`**: `string` 类型，**可选**。默认值为 `''` (空字符串)。
- **`$exchange`**: `null | Riven\Amqp\Exchanges\Exchange` 类型，**可选**。默认值为 `null`。
- **`$queue`**: `null | Riven\Amqp\Queues\Queue` 类型，**可选**。默认值为 `null`。
- **`$qos`**: `null | Riven\Amqp\Qos\Qos` 类型，**可选**。默认值为 `null`。
- **`$options`**: `array` 类型，**可选**。默认值为 `[]`。
    * 键 `exchange` - 接受 `array` 类型。参考 `amqp.connections.<name>.exchange` 配置。
    * 键 `queue` - 接受 `array` 类型。参考 `amqp.connections.<name>.queue` 配置。
    * 键 `qos` - 接受 `array` 类型。参考 `amqp.connections.<name>.qos` 配置。
    * 键 `consumer` - 接受 `array` 类型。参考 `amqp.connections.<name>.consumer` 配置。
    * 键 `bind` - 接受 `array` 类型。参考核心库的 `Riven\Amqp\Consumer::consume` 方法的文档。

**额外说明：**

- 如果 `$handler` 不是 `Riven\Amqp\Consumable` 的实现，它将使用 `Riven\Amqp\ConsumableMessage` 自动转换为 `Riven\Amqp\Consumable`。
- 如果 `$exchange` 设置为 `null`，将检查 `$options['exchange']`。如果未设置，则会使用 `amqp.connections.<name>.exchange` 中的默认属性。
- 如果 `$queue` 设置为 `null`，将检查 `$options['queue']`。如果未设置，则会使用 `amqp.connections.<name>.queue` 中的默认属性。
- 如果 `$qos` 设置为 `null`，将检查 `$options['qos']`。如果未设置，并且 `amqp.connections.<name>.qos.enabled` 设置为真值，则会使用 `amqp.connections.<name>.qos` 中的默认属性。
- 如果 `$options['bind']` 未设置，则会使用 `amqp.connections.<name>.bind` 中的默认属性。
- 如果 `$options['consumer']` 未设置，则会使用 `amqp.connections.<name>.consumer` 中的默认属性。

-----

## 测试

本包提供了测试消息发布场景的断言功能。在使用这些断言之前，你需要启用 `Amqp::fake()`：

```php
<?php

use Riven\Laravel\Amqp\Facades\Amqp;
use PHPUnit\Framework\TestCase;

class MyAmqpTest extends TestCase 
{
    public function testIfMessageWasProduced(): void
    {
        Amqp::fake();
        
        // ... 你的代码逻辑，例如发布消息
        // Amqp::publish('Test Message');

        Amqp::assertPublished(); // 断言至少有一条消息被发布
        // Amqp::assertPublished("Test Message"); // 断言精确消息内容被发布
        // Amqp::assertPublishedCount(5, "Test Message"); // 断言特定消息被发布了指定次数
        // Amqp::assertPublished(Riven\Amqp\ProducibleMessage::class); // 断言特定类的消息被发布
        // Amqp::assertPublished(Riven\Amqp\Producible::class); // 断言特定接口的实现被发布
        
        Amqp::assertPublishedOnConnection('my_rabbitmq_connection'); // 断言消息发布到特定连接
        Amqp::assertPublishedOnExchange('my_exchange'); // 断言消息发布到特定交换机
        Amqp::assertPublishedOnExchangeType('direct'); // 断言消息发布到特定交换机类型
        Amqp::assertPublishedWithRoutingKey('my.routing.key'); // 断言消息使用特定路由键发布
    }

    public function testIfNoMessageWasPublished(): void
    {
        Amqp::fake();
        
        // ... 你的代码逻辑，确保没有消息被发布

        Amqp::assertNotPublished(); // 断言没有消息被发布
        // Amqp::assertNotPublished("Some Message"); // 断言特定消息未被发布
    }
}
```

**断言方法列表：**

- **`Amqp::assertPublishedOnConnection(string $name)`**: 检查是否至少有一条消息发布到指定连接 `$name`。
- **`Amqp::assertPublishedOnExchange(string $name)`**: 检查是否至少有一条消息发布到指定交换机 `$name`。
- **`Amqp::assertPublishedOnExchangeType(string $type)`**: 检查是否至少有一条消息发布到指定交换机类型 `$type`。
- **`Amqp::assertPublishedWithRoutingKey(string $key)`**: 检查是否至少有一条消息使用指定路由键 `$key` 发布。
- **`Amqp::assertPublished($message = null)`**:
    * 如果 `$message` 为 `null`，检查是否至少有一条消息被发布。
    * 否则，按以下顺序检查：
        * 是否有消息与 `$message` **完全匹配**。
        * 是否有消息的类名与 `get_class($message)` **完全匹配**。
        * 是否有消息是 `$message` (当 `$message` 是接口或抽象类时) 的**实现**。
- **`Amqp::assertNotPublished($message = null)`**:
    * 如果 `$message` 为 `null`，检查是否**没有**消息被发布。
    * 否则，按以下顺序检查：
        * 没有发布与 `$message` **完全匹配**的消息。
        * 没有发布与 `get_class($message)` **完全匹配**的消息。
        * 没有发布是 `$message` 实现的消息。
- **`Amqp::assertPublishedCount(int $count, $message = null)`**:
    * 如果 `$message` 为 `null`，检查是否**正好**发布了 `$count` 条消息。
    * 否则，按以下顺序检查并计数：
        * 与 `$message` **完全匹配**的消息数量。
        * 类名与 `get_class($message)` **完全匹配**的消息数量。
        * 是 `$message` 实现的消息数量。

### 注意

在调用 `Amqp::fake()` 之后，尝试使用 `Amqp::consume()` 方法将会抛出异常。这是因为 `fake()` 方法会模拟 AMQP 行为，而不是实际进行网络连接和消费。

-----
