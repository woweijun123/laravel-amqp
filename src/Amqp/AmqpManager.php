<?php

namespace Riven\Amqp;

use Riven\Amqp\Builder\ExchangeBuilder;
use Riven\Amqp\Builder\QueueBuilder;
use Riven\Amqp\Enum\RedisKey;
use Riven\Amqp\Exception\ChannelException;
use Riven\Amqp\Exception\MessageException;
use Riven\Amqp\Message\ConsumerMessageInterface;
use Riven\Amqp\Message\MessageInterface;
use Riven\Amqp\Message\ProducerMessageInterface;
use Riven\Amqp\Message\Type;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class AmqpManager
{
    private AMQPStreamConnection $connection; // AMQP 链接
    private AMQPChannel $channel; // AMQP 通道
    private int $prefetchCount = 100;  // 消费者每次从队列预取的消息数量
    private int $cacheTime = 3600; // 缓存时间「秒」
    private array $declaredExchanges = []; // 交换机缓存
    private array $declaredQueues = []; // 队列缓存

    /**
     * @param array $producers 生产者实例数组，键为交换机名，值为 ProducerMessageInterface 实例
     * @param array $consumers 消费者实例数组，键为队列名，值为 ConsumerMessageInterface 实例
     * @throws MessageException
     * @throws Throwable
     */
    public function __construct(public array $producers, public array $consumers)
    {
        $this->init();
    }

    /**
     * @param string $type
     * @return void
     * @throws Exception
     */
    private function connect(string $type = ''): void
    {
        if (!isset($this->connection) || !$this->connection->isConnected()) {
            // 从配置文件中获取AMQP连接配置
            $config = config('amqp');
            if ($type == 'consumer') {
                $config = array_merge($config, config('amqp.consumer'));
            }
            $this->connection = new AMQPStreamConnection(
                $config['host'],               // RabbitMQ 服务器地址
                $config['port'],               // RabbitMQ 端口，默认为 5672
                $config['user'],               // 连接用户名
                $config['password'],           // 连接密码
                $config['vhost'],              // 虚拟主机（Virtual Host），默认为 '/'
                false,                         // 是否开启 insist 模式。如果为 true，客户端会在连接失败时尝试重连（通常不推荐在构造函数直接设置）
                'AMQPLAIN',                    // 认证机制，默认为 'AMQPLAIN'
                null,                          // 客户端属性数组，可用于发送自定义连接属性给 Broker
                'en_US',                       // Locale，语言/地区设置
                $config['connection_timeout'], // TCP 连接超时时间（秒）
                $config['read_write_timeout'], // 读写操作超时时间（秒）
                null,                          // 回调函数，用于处理连接异常（不常用）
                $config['keepalive'],          // TCP Keepalive 模式是否开启（true/false），用于维护 TCP 连接活力
                $config['heartbeat'] // 心跳间隔（秒）。客户端和服务器之间定期发送心跳帧，用于检测死连接。
            );
        }
    }

    /**
     * 获取或创建 channel
     */
    public function getChannel(): AMQPChannel
    {
        if (!isset($this->channel) || !$this->channel->is_open()) {
            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }

    /**
     * 初始化 AMQP 连接和通道，并声明交换机和队列。
     * @return void
     * @throws MessageException
     * @throws Throwable
     */
    protected function init(): void
    {
        // 创建 AMQP 链接
        $this->connect();
        /**
         * 设置 QoS (Quality of Service) 预取数量。
         * @param int  $prefetchSize  预取消息的最大字节数（0 表示不限制）。
         * @param int  $prefetchCount 消费者未确认消息的最大数量。这是核心限制。
         * @param bool $global        此 QoS 设置是否应用于整个通道（true）或仅当前消费者（false）。
         */
        $this->getChannel()->basic_qos(0, $this->prefetchCount, false); // global=false 表示作用于当前消费者

        /* @var ProducerMessageInterface $producer */
        foreach ($this->producers as $producer) {
            if (empty($producer->getExchange())) {
                Log::warning("[{$producer->getExchange()}]的交换机配置缺少必需的字段。");
                continue;
            }
            // 声明交换机
            $this->declareExchange($producer);
        }
        /* @var ConsumerMessageInterface $consumer */
        // 遍历所有队列配置，初始化交换机和消费者实例
        foreach ($this->consumers as $consumer) {
            if (empty($consumer->getQueue())) {
                Log::warning("[{$consumer->getQueue()}]的队列配置缺少必需的字段");
                continue;
            }
            // 声明队列并绑定交换机
            $this->declareQueue($consumer);
        }
    }

    /**
     * 判断交换机是否存在（优先缓存 -> Redis -> Passive 声明）
     */
    public function exchangeExists(ExchangeBuilder $builder): bool
    {
        // 本地缓存
        if (!empty($this->declaredExchanges[$builder->getExchange()])) {
            return $this->declaredExchanges[$builder->getExchange()];
        }

        // Redis 缓存
        $cached   = Redis::hGet(RedisKey::AmqpDeclaredExchange->value, $builder->getExchange());
        if ($cached !== null) {
            return $this->declaredExchanges[$builder->getExchange()] = (bool)$cached;
        }

        // AMQP 被动检查
        try {
            // 用 passive 模式检查是否存在
            $this->getChannel()->exchange_declare(
                $builder->getExchange(),  // 交换机名称
                $builder->getType(),      // 交换机类型 (direct, fanout, topic, headers)
                true,             // 是否被动声明「true: 如果交换机不存在，会抛出异常。 false: 如果不存在，就创建它。」
                $builder->isDurable(),    // 是否持久化
                $builder->isAutoDelete(), // 是否自动删除
                $builder->isInternal(),   // 是否为内部交换机（客户端不能直接发布消息到它）
                $builder->isNowait(),     // 是否等待服务器响应
                $builder->getArguments(), // 其他可选参数
                $builder->getTicket()     // 队列的访问权限凭证
            );
            Redis::hSet(RedisKey::AmqpDeclaredExchange->value, $builder->getExchange(), 1);
            Redis::expire(RedisKey::AmqpDeclaredExchange->value, $this->cacheTime, 'NX');
            return $this->declaredExchanges[$builder->getExchange()] = true;
        } catch (Throwable $e) {
            Redis::hSet(RedisKey::AmqpDeclaredExchange->value, $builder->getExchange(), 1);
            Redis::expire(RedisKey::AmqpDeclaredExchange->value, $this->cacheTime, 'NX');

            return $this->declaredExchanges[$builder->getExchange()] = false;
        }
    }

    /**
     * 声明交换机
     * @param MessageInterface $message
     * @return bool
     * @throws Throwable
     */
    protected function declareExchange(MessageInterface $message): bool
    {
        $builder = $message->getExchangeBuilder();
        if ($this->exchangeExists($builder)) {
            return true;
        }
        try {
            Log::info("交换机 [{$builder->getExchange()}] 不存在 申明中...");
            $this->getChannel()->exchange_declare(
                $builder->getExchange(),    // 交换机名称
                $builder->getType(),        // 交换机类型 (direct, fanout, topic, headers)
                $builder->isPassive(),      // 是否被动声明「true: 如果交换机不存在，会抛出异常。 false: 如果不存在，就创建它。」
                $builder->isDurable(),      // 是否持久化
                $builder->isAutoDelete(),   // 是否自动删除
                $builder->isInternal(),     // 是否为内部交换机（客户端不能直接发布消息到它）
                $builder->isNowait(),       // 是否等待服务器响应
                $builder->getArguments(),   // 其他可选参数
                $builder->getTicket()       // 队列的访问权限凭证
            );
            // redis 缓存
            Redis::hSet(RedisKey::AmqpDeclaredExchange->value, $builder->getExchange(), 1);
            Redis::expire(RedisKey::AmqpDeclaredExchange->value, $this->cacheTime, 'NX');
            $this->declaredExchanges[$builder->getExchange()] = true;

            return true;
        } catch (Throwable $exception) {
            Log::error("[{$message->getExchange()}] 交换机申明失败" . $exception->getMessage());
            // redis 缓存
            Redis::hSet(RedisKey::AmqpDeclaredExchange->value, $builder->getExchange(), 0);
            Redis::expire(RedisKey::AmqpDeclaredExchange->value, $this->cacheTime, 'NX');
            $this->declaredExchanges[$builder->getExchange()] = false;
            throw $exception;
        }
    }

    /**
     * 判断队列是否存在（优先缓存 -> Redis -> Passive 声明）
     */
    public function queueExists(QueueBuilder $builder): bool
    {
        // 本地缓存
        if (!empty($this->declaredQueues[$builder->getQueue()])) {
            return $this->declaredQueues[$builder->getQueue()];
        }

        // Redis 缓存
        $cached   = Redis::hGet(RedisKey::AmqpDeclaredQueue->value, $builder->getQueue());
        if ($cached !== null) {
            return $this->declaredQueues[$builder->getQueue()] = (bool)$cached;
        }

        // AMQP 被动检查
        try {
            // 先用 passive 模式检查队列是否存在
            $this->getChannel()->queue_declare(
                $builder->getQueue(),     // 队列名称
                true,                     // 是否被动声明「true: 如果不存在，会抛出异常。 false: 如果不存在，就创建它。」
                $builder->isDurable(),    // 是否持久化（RabbitMQ 重启后队列不消失）
                $builder->isExclusive(),  // 是否独占队列（只被当前连接使用，连接关闭后自动删除）
                $builder->isAutoDelete(), // 是否自动删除（最后一个消费者断开后自动删除）
                $builder->isNowait(),     // 是否等待服务器响应
                $builder->getArguments(), // 其他可选参数（如 TTL, DLX 等）
                $builder->getTicket()     // 访问权限票据
            );
            Redis::hSet(RedisKey::AmqpDeclaredQueue->value, $builder->getQueue(), 1);
            Redis::expire(RedisKey::AmqpDeclaredQueue->value, $this->cacheTime, 'NX');
            return $this->declaredQueues[$builder->getQueue()] = true;
        } catch (Throwable $e) {
            Redis::hSet(RedisKey::AmqpDeclaredQueue->value, $builder->getQueue(), 0);
            Redis::expire(RedisKey::AmqpDeclaredQueue->value, $this->cacheTime, 'NX');

            return $this->declaredQueues[$builder->getQueue()] = false;
        }
    }

    /**
     * 声明队列并绑定交换机。
     * 支持声明死信队列 (DLQ) 和消息 TTL。
     * @param ConsumerMessageInterface $message
     * @return bool
     */
    protected function declareQueue(ConsumerMessageInterface $message): bool
    {
        $builder = $message->getQueueBuilder();
        if ($this->queueExists($builder)) {
            return true;
        }
        try {
            Log::info("队列 [{$builder->getQueue()}] 不存在 申明中...");
            $this->getChannel()->queue_declare(
                $builder->getQueue(),     // 队列名称
                $builder->isPassive(), // 是否被动声明（只检查队列是否存在，不创建）
                $builder->isDurable(), // 是否持久化（RabbitMQ 重启后队列不消失）
                $builder->isExclusive(), // 是否独占队列（只被当前连接使用，连接关闭后自动删除）
                $builder->isAutoDelete(), // 是否自动删除（最后一个消费者断开后自动删除）
                $builder->isNowait(), // 是否等待服务器响应
                $builder->getArguments(), // 其他可选参数（如 TTL, DLX 等）
                $builder->getTicket() // 访问权限票据
            );
            // redis 缓存
            Redis::hSet(RedisKey::AmqpDeclaredQueue->value, $builder->getQueue(), 1);
            Redis::expire(RedisKey::AmqpDeclaredQueue->value, $this->cacheTime, 'NX');
            $this->declaredQueues[$builder->getQueue()] = true;

            $routineKeys = (array)$message->getRoutingKey();
            foreach ($routineKeys as $routingKey) {
                // 将队列绑定到交换机和路由键
                $this->getChannel()->queue_bind($message->getQueue(), $message->getExchange(), $routingKey);
            }

            // 如果路由键为空且交换机类型为 FANOUT，则不使用路由键进行绑定
            if (empty($routineKeys) && $message->getType() === Type::FANOUT) {
                $this->getChannel()->queue_bind($message->getQueue(), $message->getExchange());
            }

            // 如果消费者消息中定义了 QoS 设置，则重新设置
            if (is_array($qos = $message->getQos())) {
                $size   = $qos['prefetch_size'] ?? null;
                $count  = $qos['prefetch_count'] ?? null;
                $global = $qos['global'] ?? null;
                $this->getChannel()->basic_qos($size, $count, $global);
            }

            return true;
        } catch (Throwable $exception) {
            Log::error("[{$builder->getQueue()}] 队列申明失败" . $exception->getMessage());
            Redis::hSet(RedisKey::AmqpDeclaredQueue->value, $builder->getQueue(), 0);
            Redis::expire(RedisKey::AmqpDeclaredQueue->value, $this->cacheTime, 'NX');
            $this->declaredQueues[$builder->getQueue()] = false;
            throw $exception;
        }
    }

    /**
     * 发送消息到指定交换机。
     * 支持发布确认 (Publisher Confirm) 机制。
     * @param ProducerMessageInterface $producerMessage 生产者消息对象
     * @param bool                     $confirm         是否启用发布确认
     * @param int                      $timeout         发布确认的等待超时时间（秒）
     * @return bool 消息是否成功发布并被Broker确认
     * @throws InvalidArgumentException 如果交换机未在配置中找到
     * @throws Throwable 如果发布过程中发生错误
     */
    public function produce(ProducerMessageInterface $producerMessage, bool $confirm = false, int $timeout = 5): bool
    {
        $confirmed = false; // 消息是否被 Broker 确认
        $nacked    = false;    // 消息是否被 Broker 拒绝
        try {
            // 每次发消息都新建一个 channel, 避免覆盖回调函数问题：Server ack'ed unknown delivery_tag "2"
            $this->channel = $this->connection->channel();
            // 如果启用发布确认
            if ($confirm) {
                $this->channel->confirm_select(); // 开启发布确认模式
                // 设置 ack 处理器：当消息被 Broker 确认时调用
                $this->channel->set_ack_handler(function (AMQPMessage $message) use (&$confirmed) {
                    Log::debug("[Ack received for delivery]", [
                        'exchange'     => $message->getExchange(),
                        'delivery_tag' => $message->getDeliveryTag(),
                        'body'         => $message->getBody(),
                    ]);
                    // 执行后清空监听器, 防止重复执行
                    $this->channel->set_ack_handler(fn() => null);
                    $confirmed = true;
                });
                // 设置 nack 处理器：当消息被 Broker 拒绝时调用（例如：队列不存在，消息路由失败等）
                $this->channel->set_nack_handler(function (AMQPMessage $message) use (&$nacked) {
                    Log::error("[Nack received for delivery]", [
                        'exchange'     => $message->getExchange(),
                        'delivery_tag' => $message->getDeliveryTag(),
                        'body'         => $message->getBody(),
                    ]);
                    // 执行后清空监听器, 防止重复执行
                    $this->channel->set_nack_handler(fn() => null);
                    $nacked = true;
                });
                // 设置 Return 监听器，当消息无法路由且设置了 mandatory 标志时会触发
                $this->channel->set_return_listener(function (
                    int $replyCode,
                    string $replyText,
                    string $exchange,
                    string $routingKey,
                    AMQPMessage $message
                ) use ($producerMessage) {
                    $producerMessage->onMandatoryReturn($replyCode, $replyText, $exchange, $routingKey, $message);
                    // 执行后清空监听器, 防止重复执行
                    $this->channel->set_return_listener(fn() => null);
                });
            }
            // 创建 AMQPMessage 实例
            $message = new AMQPMessage($producerMessage->payload(), $producerMessage->getProperties());
            // 发布消息
            $this->channel->basic_publish(
                $message,
                $producerMessage->getExchange(),    // 交换机名称
                $producerMessage->getRoutingKey(),  // 路由键
                $producerMessage->getMandatory(),   // 如果消息无法路由到队列，是否返回给生产者
                $producerMessage->getImmediate()    // 消息是否必须立即被消费者接收
            );
            // 等待发布确认结果
            if ($confirm) {
                $this->channel->wait_for_pending_acks_returns($timeout); // 等待所有挂起的 ack/nack 确认或返回消息
                $result = $confirmed && !$nacked; // 只有当消息被确认且未被拒绝时才算成功
            } else {
                $result = true; // 如果不使用确认模式，则假定发布成功
            }
        } catch (Throwable $exception) {
            // 缓存交换机和队列不存在
            $this->switchExchangeQueueCache($exception);
            // 捕获异常，关闭通道和连接，并重新抛出异常
            if (isset($this->channel) && $this->channel->is_open()) {
                $this->channel->close();
            }
            if (isset($this->connection) && $this->connection->isConnected()) {
                $this->connection->close();
            }
            throw $exception;
        }

        return $result;
    }

    /**
     * 监听指定队列并消费消息
     * @param string $queueName
     * @return void
     * @throws Exception
     */
    public function consume(string $queueName): void
    {
        // 检查队列对应的消费者是否存在
        if (!isset($this->consumers[$queueName])) {
            throw new InvalidArgumentException("未找到消费者: $queueName");
        }
        /* @var ConsumerMessageInterface $consumer */
        $consumer = $this->consumers[$queueName];
        // 构建唯一的消费者标签，用于标识当前消费者
        $consumerTag = "consumer_{$queueName}_" . uniqid();
        try {
            // 连接
            $this->connect('consumer');
            $this->getChannel()->basic_qos(0, $this->prefetchCount, false); // global=false 表示作用于当前消费者
            // 注册基本消费者回调
            $this->channel->basic_consume(
                $queueName,   // 要消费的队列名称
                $consumerTag, // 消费者标签
                false,        // no_local: 不接收发布者自己发布的消息（通常设置为 false）
                false,        // no_ack: 启用手动消息确认（false 表示需要手动 ack/nack）
                false,        // exclusive: 非排他性消费（允许多个消费者共享队列）
                false,        // nowait: 阻塞等待服务器响应
                function (AMQPMessage $message) use ($consumer) {
                    // 包装消费者回调函数，以处理消息确认/拒绝逻辑
                    $this->getCallback($consumer, $message)();
                }
            );
        } catch (Exception $exception) {
            $this->switchExchangeQueueCache($exception);
            throw $exception;
        }
        // 启动消费者监听循环
        $this->startConsuming($consumer);
    }

    /**
     * 获取消费者回调函数，处理消息的业务逻辑、重试机制和确认/拒绝。
     * @param ConsumerMessageInterface $consumerMessage 消费者消息处理器实例
     * @param AMQPMessage              $message         收到的 AMQP 消息
     * @return callable 实际的消息处理回调函数
     */
    protected function getCallback(ConsumerMessageInterface $consumerMessage, AMQPMessage $message): callable
    {
        return function () use ($consumerMessage, $message) {
            $channel     = $message->getChannel();
            $deliveryTag = $message->getDeliveryTag(); // 消息的投递标签，用于确认/拒绝
            $messageId   = $message->get('message_id'); // 获取消息 ID，用于追踪重试次数

            // 如果消息没有 message_id，无法追踪重试次数，直接拒绝（不重新入队）
            if (empty($messageId)) {
                Log::warning("消息没有message_id，无法跟踪重试计数", [
                    'delivery_tag' => $deliveryTag,
                    'body'         => $message->getBody(),
                    'queue'        => $consumerMessage->getQueue(),
                ]);
                $channel->basic_reject($deliveryTag, false); // false 表示不重新入队，通常会进入死信队列（如果配置了）
                return;
            }
            try {
                // 执行消费逻辑
                $data   = $consumerMessage->unserialize($message->getBody()); // 反序列化消息体
                Log::withContext(['message_id' => $messageId]);
                Log::debug(
                    "------- {$consumerMessage->getQueue()} start -------",
                    ['delivery_tag' => $deliveryTag, 'body' => $message->getBody(), 'queue' => $consumerMessage->getQueue()]
                );
                $result = $consumerMessage->consumeMessage($data, $message); // 调用消费者定义的实际业务处理方法
                Log::debug(
                    "------- {$consumerMessage->getQueue()} end -------",
                    ['delivery_tag' => $deliveryTag, 'body' => $message->getBody(), 'result' => $result]
                );
            } catch (Throwable $e) {
                // 捕获消费过程中发生的异常，记录错误日志
                Log::error("消费异常" . $e->getMessage(), [
                    'exception'    => $e,
                    'delivery_tag' => $deliveryTag,
                    'body'         => $message->getBody(),
                    'queue'        => $consumerMessage->getQueue(),
                ]);
                // 处理消费失败的情况，根据配置决定是否重试
                if ($consumerMessage->isRetry()) {
                    // 获取当前重试次数，如果不存在则为 0
                    $retryCount = app(AmqpRetry::class)->getRetryCount($messageId);
                    if ($retryCount >= $consumerMessage->getRetryCount()) {
                        Log::warning("超过最大重试次数，移动到死信队列「DLQ」", [
                            'delivery_tag' => $deliveryTag,
                            'body'         => $message->getBody(),
                            'retry_count'  => $retryCount,
                            'queue'        => $consumerMessage->getQueue(),
                        ]);
                        // 达到最大重试次数，不重新入队，直接拒绝，会进入死信队列，清理Redis次数
                        app(AmqpRetry::class)->clearRetryCount($messageId); // 从 Redis 删除重试计数
                        $result = Result::DROP;
                    } else {
                        app(AmqpRetry::class)->setRetryCount($messageId, ++$retryCount);
                        $result = Result::NACK;
                    }
                } else {
                    $result = Result::DROP;
                }
            }
            // 根据消费结果进行不同的处理
            switch ($result) {
                case Result::ACK:
                    Log::debug($deliveryTag . ' acked.');
                    app(AmqpRetry::class)->clearRetryCount($messageId); // 从 Redis 删除重试计数
                    $channel->basic_ack($deliveryTag); // 确认消息已成功处理
                    break;
                case Result::NACK:
                    Log::debug($deliveryTag . ' uacked.');
                    $channel->basic_nack($deliveryTag, false, $consumerMessage->isRequeue());
                    break;
                case Result::REQUEUE:
                    Log::debug($deliveryTag . ' requeued.');
                    $channel->basic_reject($deliveryTag, $consumerMessage->isRequeue());
                    break;
                default:
                    Log::debug($deliveryTag . ' rejected.');
                    $channel->basic_reject($deliveryTag, false); // 拒绝消息，不重新入队
                    break;
            }
        };
    }

    /**
     * 启动消费者监听循环。
     * 这个方法会使脚本持续运行，等待和处理来自 RabbitMQ 的消息，并包含自动重连和心跳机制。
     * @param ConsumerMessageInterface $consumer 消费者消息处理器实例，用于获取队列信息
     */
    public function startConsuming(ConsumerMessageInterface $consumer): void
    {
        // 获取重试配置
        $retryEnabled = config('amqp.retry.enabled', true);
        $maxAttempts  = config('amqp.retry.max_attempts', 5);
        $backoffBase  = config('amqp.retry.backoff_base', 2);

        // 初始化上次心跳时间
        $lastHeartbeat = microtime(true);

        // 持续等待消息，直到通道不再处于消费状态
        while ($this->channel->is_consuming()) {
            try {
                $this->channel->wait();           // 阻塞等待消息，处理 IO 事件
                $lastHeartbeat = microtime(true); // 成功接收或等待到消息后更新心跳时间
            } catch (Throwable $e) {
                // 捕获等待过程中的错误，记录日志
                Log::error("AMQP wait error: " . $e->getMessage(), [
                    'code'  => $e->getCode(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'queue' => $consumer->getQueue(),
                ]);

                $currentTime = microtime(true);
                // 如果长时间没有心跳（即没有消息或连接中断），尝试重连
                if ($currentTime - $lastHeartbeat > config('amqp.consumer.heartbeat')) {
                    Log::info("No heartbeat received for Amqp::HEARTBEAT_TIMEOUT seconds, attempting to reconnect...");

                    // 检查是否启用了自动重连
                    if (!$retryEnabled) {
                        Log::warning("Automatic reconnection is disabled.");
                        return; // 如果禁用重连，则停止消费者进程
                    }

                    $attempt     = 0;
                    $reconnected = false;

                    // 循环尝试重连，直到达到最大尝试次数
                    while ($attempt < $maxAttempts) {
                        $attempt++;

                        $reconnected = $this->reconnect(); // 执行重连操作
                        if ($reconnected) {
                            Log::info("Reconnected successfully after $attempt attempt(s).");
                            // 重连成功后更新心跳时间
                            $lastHeartbeat = microtime(true);
                            // 成功重连，跳出重试循环
                            break;
                        }

                        $waitSeconds = $backoffBase ** $attempt; // 计算指数退避等待时间 (2的N次方秒)
                        Log::warning("Reconnection attempt $attempt failed. Retrying in $waitSeconds seconds...");
                        sleep($waitSeconds); // 等待指定时间后再次尝试
                    }

                    // 如果所有重试都失败了
                    if (!$reconnected) {
                        Log::error("Failed to reconnect after $maxAttempts attempts. Stopping consumer.");
                        return; // 无法重连，停止消费者进程
                    }
                }
            }
        }
    }

    /**
     * 重新连接 AMQP 服务器并重新初始化通道、QoS、交换机和队列。
     * @return bool 返回 true 表示重连成功，false 表示失败。
     */
    protected function reconnect(): bool
    {
        try {
            // 关闭现有通道（如果打开）
            if ($this->channel->is_open()) {
                $this->channel->close();
            }
            // 如果连接仍然连接着，则尝试重新连接（通过内部机制）
            if ($this->connection->isConnected()) {
                $this->connection->reconnect(); // 尝试利用现有连接对象重新连接
            } else {
                // 如果连接已完全断开，则根据配置重新创建新地连接对象
                $this->connect('consumer');
            }
            // 重新创建通道
            $this->channel = $this->connection->channel();
            // 检查新通道是否成功打开
            if (!$this->channel->is_open()) {
                throw new ChannelException("Failed to reopen AMQP channel after reconnect.");
            }
            // 重新设置 QoS（预取数量），因为通道是新的
            $this->channel->basic_qos(0, $this->prefetchCount, false);
            Log::info("AMQP connection re-established successfully.");

            return true; // 重连成功
        } catch (Throwable $e) {
            // 捕获重连过程中的异常并记录错误
            Log::error("Failed to reconnect to AMQP: " . $e->getMessage(), ['exception' => $e]);

            return false; // 重连失败
        }
    }

    /**
     * 关闭 AMQP 连接和通道，用于 `register_shutdown_function`。
     * 这个方法通常会在脚本结束时被调用，以确保资源被正确释放。
     */
    public function shutdown(): void
    {
        try {
            // 如果通道存在且处于打开状态，则关闭
            if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (Throwable $e) {
            // 缓存交换机和队列不存在
            $this->switchExchangeQueueCache($e);
            Log::error("关闭AMQP通道失败: " . $e->getMessage());
        }

        try {
            // 如果连接存在且处于连接状态，则关闭
            if ($this->connection instanceof AMQPStreamConnection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Throwable $e) {
            Log::error("关闭AMQP连接失败: " . $e->getMessage());
        }
    }

    /**
     * 缓存交换机和队列不存在
     * @param Throwable|Exception $exception
     * @return void
     */
    protected function switchExchangeQueueCache(Throwable|Exception $exception): void
    {
        // 根据异常信息判定指定交换机不存在，则标记redis中对应的交换机不存在
        if (preg_match("/no exchange '([^']+?)'/", $exception->getMessage(), $matches)) {
            $exchangeName = $matches[1];
            if (key_exists($exchangeName, $this->producers)) {
                Redis::hSet(RedisKey::AmqpDeclaredExchange->value, $exchangeName, 0);
            }
        }
        // 根据异常信息判定指定队列不存在，则标记redis中对应的队列不存在
        if (preg_match("/no queue '([^']+?)'/", $exception->getMessage(), $matches)) {
            $queueName = $matches[1];
            if (key_exists($queueName, $this->consumers)) {
                Redis::hSet(RedisKey::AmqpDeclaredQueue->value, $queueName, 0);
            }
        }
    }
}

