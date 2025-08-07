<?php

namespace Riven\Amqp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Riven\Amqp\Builder\ExchangeBuilder;
use Riven\Amqp\Builder\QueueBuilder;
use Riven\Amqp\Enum\AmqpRedisKey;
use Riven\Amqp\Message\ConsumerMessageInterface;
use Riven\Amqp\Message\MessageInterface;
use Riven\Amqp\Message\ProducerMessageInterface;
use Riven\Amqp\Message\Type;
use Throwable;

class AmqpManager
{
    private int $cacheTime = 3600; // 缓存时间「秒」
    private array $declaredExchanges = []; // 交换机缓存
    private array $declaredQueues = []; // 队列缓存
    private bool $init = false; // 是否初始化

    /**
     * @param array $producers 生产者实例数组，键为交换机名，值为 ProducerMessageInterface 实例路径
     * @param array $consumers 消费者实例数组，键为队列名，值为 ConsumerMessageInterface 实例路径
     */
    public function __construct(public array $producers, public array $consumers)
    {
    }

    /**
     * 初始化 AMQP 连接和通道，并声明交换机和队列。
     * @return AmqpManager
     * @throws Throwable
     */
    public function init(): self
    {
        if ($this->init) {
            return $this;
        }
        // 创建 AMQP 连接
        $connection = AMQPConnection::getInstance();
        $channel = $connection->getChannel();
        /* @var ProducerMessageInterface $producer */
        foreach ($this->producers as $producer) {
            $producer = app($producer);
            if (empty($producer->getExchange())) {
                Log::warning("[{$producer->getExchange()}]的交换机配置缺少必需的字段。");
                continue;
            }
            // 声明交换机
            $this->declareExchange($producer, $channel);
        }
        /* @var ConsumerMessageInterface $consumer */
        // 遍历所有队列配置，初始化交换机和消费者实例
        foreach ($this->consumers as $consumer) {
            $consumer = app($consumer);
            if (empty($consumer->getQueue())) {
                Log::warning("[{$consumer->getQueue()}]的队列配置缺少必需的字段");
                continue;
            }
            // 声明队列并绑定交换机
            $this->declareQueue($consumer, $channel);
        }
        $this->init = true; // 初始化完成
        return $this;
    }

    /**
     * 判断交换机是否存在
     */
    public function exchangeExists(ExchangeBuilder $builder): bool
    {
        // 本地缓存
        if (!empty($this->declaredExchanges[$builder->getExchange()])) {
            return $this->declaredExchanges[$builder->getExchange()];
        }
        // Redis 缓存
        if ($this->getExchangeCache($builder->getExchange())) {
            return $this->declaredExchanges[$builder->getExchange()] = true;
        }
        return false;
    }

    /**
     * 声明交换机
     * @param MessageInterface $message
     * @param AMQPChannel $channel
     * @return bool
     * @throws Throwable
     */
    protected function declareExchange(MessageInterface $message, AMQPChannel $channel): bool
    {
        $builder = $message->getExchangeBuilder();
        if ($this->exchangeExists($builder)) {
            return true;
        }
        try {
            Log::info("交换机 [{$builder->getExchange()}] 不存在 申明中...");
            $channel->exchange_declare(
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
            $this->setExchangeCache($builder->getExchange());
            $this->declaredExchanges[$builder->getExchange()] = true;
            return true;
        } catch (Throwable $exception) {
            Log::error("[{$message->getExchange()}] 交换机申明失败" . $exception->getMessage());
            $this->setExchangeCache($builder->getExchange(), 0);
            $this->declaredExchanges[$builder->getExchange()] = false;
            throw $exception;
        }
    }

    /**
     * 判断队列是否存在
     */
    public function queueExists(QueueBuilder $builder): bool
    {
        // 本地缓存
        if (!empty($this->declaredQueues[$builder->getQueue()])) {
            return $this->declaredQueues[$builder->getQueue()];
        }
        // Redis 缓存
        if ($this->getQueueCache($builder->getQueue())) {
            return $this->declaredQueues[$builder->getQueue()] = true;
        }
        return false;
    }

    /**
     * 声明队列并绑定交换机。
     * 支持声明死信队列 (DLQ) 和消息 TTL。
     * @param ConsumerMessageInterface $message
     * @param AMQPChannel $channel
     * @return bool
     * @throws Throwable
     */
    protected function declareQueue(ConsumerMessageInterface $message, AMQPChannel $channel): bool
    {
        $builder = $message->getQueueBuilder();
        if ($this->queueExists($builder)) {
            return true;
        }
        try {
            Log::info("队列 [{$builder->getQueue()}] 不存在 申明中...");
            $channel->queue_declare(
                $builder->getQueue(), // 队列名称
                $builder->isPassive(), // 是否被动声明（只检查队列是否存在，不创建）
                $builder->isDurable(), // 是否持久化（RabbitMQ 重启后队列不消失）
                $builder->isExclusive(), // 是否独占队列（只被当前连接使用，连接关闭后自动删除）
                $builder->isAutoDelete(), // 是否自动删除（最后一个消费者断开后自动删除）
                $builder->isNowait(), // 是否等待服务器响应
                $builder->getArguments(), // 其他可选参数（如 TTL, DLX 等）
                $builder->getTicket() // 访问权限票据
            );
            $this->setQueueCache($builder->getQueue());
            $this->declaredQueues[$builder->getQueue()] = true;

            $routineKeys = (array)$message->getRoutingKey();
            foreach ($routineKeys as $routingKey) {
                // 将队列绑定到交换机和路由键
                $channel->queue_bind($message->getQueue(), $message->getExchange(), $routingKey);
            }

            // 如果路由键为空且交换机类型为 FANOUT，则不使用路由键进行绑定
            if (empty($routineKeys) && $message->getType() === Type::FANOUT) {
                $channel->queue_bind($message->getQueue(), $message->getExchange());
            }
            return true;
        } catch (Throwable $exception) {
            Log::error("[{$builder->getQueue()}] 队列申明失败" . $exception->getMessage());
            $this->setQueueCache($builder->getQueue(), 0);
            $this->declaredQueues[$builder->getQueue()] = false;
            throw $exception;
        }
    }

    /**
     * 发送消息到指定交换机。
     * 支持发布确认 (Publisher Confirm) 机制。
     * @param ProducerMessageInterface $producerMessage 生产者消息对象
     * @param bool $confirm 是否启用发布确认
     * @param int $timeout 发布确认的等待超时时间（秒）
     * @return bool 消息是否发送成功
     * @throws InvalidArgumentException 如果交换机未在配置中找到
     * @throws Throwable 如果发布过程中发生错误
     */
    public function produce(ProducerMessageInterface $producerMessage, bool $confirm = false, int $timeout = 5): bool
    {
        try {
            $connection = AMQPConnection::getInstance();
            $isSendSuccess = true; // 发送消息成功标志「不用发布确认，则假定发布成功」
            // 启用发布确认
            if ($confirm) {
                $channel = $connection->getConfirmChannel();
                $isSendSuccess = $this->confirmCallback($producerMessage, $channel);
            } else {
                $channel = $connection->getChannel();
            }
            // 创建 AMQPMessage 实例
            $message = new AMQPMessage($producerMessage->payload(), $producerMessage->getProperties());
            // 发布消息
            $channel->basic_publish(
                $message,
                $producerMessage->getExchange(),    // 交换机名称
                $producerMessage->getRoutingKey(),  // 路由键
                $producerMessage->getMandatory(),   // 如果消息无法路由到队列，是否返回给生产者
                $producerMessage->getImmediate()    // 消息是否必须立即被消费者接收
            );
            // 等待发布确认结果
            if ($confirm) {
                $channel->wait_for_pending_acks_returns($timeout); // 等待所有挂起的 ack/nack 确认或返回消息
            }
        } catch (Throwable $exception) {
            // 缓存交换机和队列不存在
            $this->switchExchangeQueueCache($exception);
            throw $exception;
        }

        return $isSendSuccess;
    }

    /**
     * 监听指定队列并消费消息
     * @param string $queueName
     * @return void
     * @throws Throwable
     */
    public function consume(string $queueName): void
    {
        // 检查队列对应的消费者是否存在
        if (empty($this->consumers[$queueName])) {
            throw new InvalidArgumentException("未找到消费者: $queueName");
        }
        try {
            /* @var ConsumerMessageInterface $consumer */
            $consumer = app($this->consumers[$queueName]);
            $connection = AMQPConnection::getInstance(true);
            $channel = $connection->getChannel();
            // 设置 QoS 「Quality of Service」 预取数量
            if (is_array($qos = $consumer->getQos())) {
                $size = $qos['prefetch_size'] ?? 0; // 预取消息的最大字节数（0 表示不限制）
                $count = $qos['prefetch_count'] ?? 1; // 消费者未确认消息的最大数量
                $global = $qos['global'] ?? false; // 设置是否应用于整个通道（true）或仅当前消费者（false）
                $channel->basic_qos($size, $count, $global);
            }
            // 获取消费者最大消费消息数量
            $maxConsumption = $consumer->getMaxConsumption();
            $currentConsumption = 0;
            // 注册基本消费者回调
            $channel->basic_consume(
                $queueName, // 要消费的队列名称
                $consumer->getConsumerTag(), // 消费者标签
                $consumer->isNoLocal(), // no_local: 不接收发布者自己发布的消息（通常设置为 false）
                $consumer->isNoAck(), // no_ack: 启用手动消息确认（false 表示需要手动 ack/nack）
                $consumer->isExclusive(), // exclusive: 独占队列（true 表示仅当前消费者可访问）
                $consumer->isNowait(), // nowait: 不等待服务器响应（true 表示不等待服务器响应）
                function (AMQPMessage $message) use ($consumer) {
                    // 包装消费者回调函数，以处理消息确认/拒绝逻辑
                    $this->handleCallback($consumer, $message);
                },
                $consumer->getTicket(),
                $consumer->getArguments()
            );
            // 启动消费者监听循环, 持续等待消息，直到通道不再处于消费状态
            while ($channel->is_consuming()) {
                $channel->wait(); // 阻塞等待消息，处理 IO 事件
                // 消费数量达到限制，停止消费
                if ($maxConsumption > 0 && ++$currentConsumption >= $maxConsumption) {
                    Log::info("消费者已消费 $currentConsumption 条消息，达到限制，停止消费");
                    break;
                }
            }
        } catch (Throwable $exception) {
            $this->switchExchangeQueueCache($exception);
            Log::error("消费者异常退出进程($queueName)" . $exception->getMessage());
        }
        exit(1); // 异常退出进程, 退出码 1，可用于supervisor重启
    }

    /**
     * 处理消费者回调函数
     * @param ConsumerMessageInterface $consumerMessage 消费者消息处理器实例
     * @param AMQPMessage $message 收到的 AMQP 消息
     * @return void
     */
    protected function handleCallback(ConsumerMessageInterface $consumerMessage, AMQPMessage $message): void
    {
        $channel = $message->getChannel();
        $deliveryTag = $message->getDeliveryTag(); // 消息的投递标签，用于确认/拒绝
        $messageId = $message->get('message_id'); // 获取消息 ID，用于追踪重试次数
        Log::withContext(['message_id' => $messageId]);

        // 如果消息没有 message_id，无法追踪重试次数，直接拒绝（不重新入队）
        if (empty($messageId)) {
            Log::warning("消息没有message_id，无法跟踪重试计数", [
                'delivery_tag' => $deliveryTag,
                'body' => $message->getBody(),
                'queue' => $consumerMessage->getQueue(),
            ]);
            $channel->basic_reject($deliveryTag, false); // false 表示不重新入队，通常会进入死信队列（如果配置了）
            return;
        }
        try {
            // 执行消费逻辑
            Log::info(
                "--- {$consumerMessage->getQueue()} start ---",
                ['delivery_tag' => $deliveryTag, 'body' => $message->getBody(), 'queue' => $consumerMessage->getQueue()]
            );
            // 调用消费者定义的实际业务处理方法
            $result = $consumerMessage->consumeMessage(
                // 反序列化消息体
                $consumerMessage->unserialize($message->getBody()),
                $message
            );
            Log::info("--- {$consumerMessage->getQueue()} end ---", ['result' => $result]);
        } catch (Throwable $e) {
            // 捕获消费过程中发生的异常，记录错误日志
            Log::error("消费异常" . $e->getMessage(), [
                'exception' => $e,
                'delivery_tag' => $deliveryTag,
                'body' => $message->getBody(),
                'queue' => $consumerMessage->getQueue(),
            ]);
            // 处理消费失败的情况，根据配置决定是否重试
            if ($consumerMessage->isRetry()) {
                // 获取当前重试次数，如果不存在则为 0
                $retryCount = app(AmqpRetry::class)->getRetryCount($messageId);
                if ($retryCount >= $consumerMessage->getRetryCount()) {
                    Log::warning("超过最大重试次数，移动到死信队列「DLQ」", [
                        'delivery_tag' => $deliveryTag,
                        'body' => $message->getBody(),
                        'retry_count' => $retryCount,
                        'queue' => $consumerMessage->getQueue(),
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
                app(AmqpRetry::class)->clearRetryCount($messageId); // 从 Redis 删除重试计数
                $channel->basic_ack($deliveryTag); // 确认消息已成功处理
                break;
            case Result::NACK:
                $channel->basic_nack($deliveryTag, false, $consumerMessage->isRequeue());
                break;
            case Result::REQUEUE:
                $channel->basic_reject($deliveryTag, $consumerMessage->isRequeue());
                break;
            default:
                $channel->basic_reject($deliveryTag, false); // 拒绝消息，不重新入队
                break;
        }
    }

    /**
     * 关闭 AMQP 连接和通道，用于 `register_shutdown_function` 在脚本结束时被调用，以确保资源被正确释放
     * @return void
     */
    public function shutdown(): void
    {
        AMQPConnection::shutdown();
    }

    /**
     * 缓存交换机和队列不存在
     * @param Throwable $exception
     * @return void
     */
    protected function switchExchangeQueueCache(Throwable $exception): void
    {
        // 根据异常信息判定指定交换机不存在，则标记redis中对应的交换机不存在
        if (preg_match("/no exchange '([^']+?)'/", $exception->getMessage(), $matches)) {
            $exchangeName = $matches[1];
            if (key_exists($exchangeName, $this->producers)) {
                $this->setQueueCache($exchangeName, 0);
            }
        }
        // 根据异常信息判定指定队列不存在，则标记redis中对应的队列不存在
        if (preg_match("/no queue '([^']+?)'/", $exception->getMessage(), $matches)) {
            $queueName = $matches[1];
            if (key_exists($queueName, $this->consumers)) {
                $this->setQueueCache($queueName, 0);
            }
        }
    }

    /**
     * 设置交换机缓存
     * @param string $exchange
     * @param int $flag 1:存在 0:不存在
     * @return void
     */
    protected function setExchangeCache(string $exchange, int $flag = 1): void
    {
        Redis::hSet(AmqpRedisKey::AmqpDeclaredExchange->value, $exchange, $flag);
        Redis::expire(AmqpRedisKey::AmqpDeclaredExchange->value, $this->cacheTime, 'NX');
    }

    /**
     * 设置队列缓存
     * @param string $queue
     * @param int $flag 1:存在 0:不存在
     * @return void
     */
    protected function setQueueCache(string $queue, int $flag = 1): void
    {
        Redis::hSet(AmqpRedisKey::AmqpDeclaredQueue->value, $queue, $flag);
        Redis::expire(AmqpRedisKey::AmqpDeclaredQueue->value, $this->cacheTime, 'NX');
    }

    /**
     * 获取交换机缓存
     * @param string $exchange
     * @return bool
     */
    protected function getExchangeCache(string $exchange): bool
    {
        return Redis::hGet(AmqpRedisKey::AmqpDeclaredExchange->value, $exchange);
    }

    /**
     * 获取队列缓存
     * @param string $queue
     * @return bool
     */
    protected function getQueueCache(string $queue): bool
    {
        return (bool)Redis::hGet(AmqpRedisKey::AmqpDeclaredQueue->value, $queue);
    }

    /**
     * 发布确认回调
     * @param ProducerMessageInterface $producerMessage
     * @param AMQPChannel $channel
     * @return bool
     */
    protected function confirmCallback(ProducerMessageInterface $producerMessage, AMQPChannel $channel): bool
    {
        $isSendSuccess = true;
        // 设置 ack 处理器：当消息被 Broker 确认时调用
        $channel->set_ack_handler(function (AMQPMessage $message) use ($producerMessage) {
            $producerMessage->ackHandler($message);
        });
        // 设置 nack 处理器：当消息被 Broker 拒绝时调用（例如：队列不存在，消息路由失败等）
        $channel->set_nack_handler(function (AMQPMessage $message) use (&$isSendSuccess, $producerMessage) {
            $producerMessage->nackHandler($message);
            $isSendSuccess = false;
        });
        // 设置 Return 监听器，当消息无法路由且设置了 mandatory 标志时会触发
        $channel->set_return_listener(function (
            int         $replyCode,
            string      $replyText,
            string      $exchange,
            string      $routingKey,
            AMQPMessage $message
        ) use ($producerMessage, &$isSendSuccess) {
            $producerMessage->basicReturnCallback($replyCode, $replyText, $exchange, $routingKey, $message);
            $isSendSuccess = false;
        });
        return $isSendSuccess;
    }
}
