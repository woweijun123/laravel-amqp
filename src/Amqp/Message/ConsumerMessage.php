<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Riven\Amqp\Builder\QueueBuilder;
use Riven\Amqp\Packer\PhpSerializerPacker;
use Riven\Amqp\Result;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Container\ContainerInterface;

/**
 * 抽象消费者消息类
 * 定义了 AMQP 消费者消息的基础结构和通用行为。
 * 具体的消费者需要继承此抽象类并实现其核心消费逻辑。
 */
abstract class ConsumerMessage extends Message implements ConsumerMessageInterface
{
    /**
     * PSR 容器实例，用于依赖注入或获取服务
     * @var ContainerInterface|null
     */
    public ?ContainerInterface $container = null;

    /**
     * 队列名称
     * @var string|null
     */
    protected ?string $queue = null;

    /**
     * 消息处理失败时是否重新入队
     * @var bool
     */
    protected bool $requeue = true;

    /**
     * 消息处理失败时是否重试
     * @var bool
     */
    protected bool $retry = false;

    /**
     * 获取消费者标签「Consumer Tag」, 在 AMQP 中用于唯一标识一个消费者
     * @var null|string
     */
    protected $consumerTag = null;

    /**
     * 是否接收发布者自己发布的消息
     * @var bool
     */
    protected $noLocal = false;

    /**
     * 手动消息确认（false 表示需要手动 ack/nack）
     * @var bool
     */
    protected $noAck = false;

    /**
     * 队列独占（false 表示队列可以被多个消费者同时访问）
     * @var bool
     */
    protected $exclusive = false;

    /**
     * 队列独占（false 表示队列可以被多个消费者同时访问）
     * @var bool
     */
    protected $nowait = false;

    /**
     * 队列参数
     * @var array
     */
    protected $arguments = [];

    /**
     * 队列票据
     * @var null
     */
    protected $ticket = null;

    /**
     * 读写超时
     * @var null
     */
    protected $readWriteTimeout = null;

    /**
     * 心跳间隔
     * @var null
     */
    protected $heartbeat = null;

    /**
     * 消息处理失败时重试次数
     * @var int
     */
    protected int $retryCount = 3;

    /**
     * 路由键，可以是单个字符串或字符串数组
     * @var array|string
     */
    protected array|string $routingKey = [];

    /**
     * Qos 「Quality of Service」 设置
     * prefetch_size: 预取消息的最大字节数
     * prefetch_count: 每次从队列中预取的消息数量
     * global: 是否将 Qos 设置应用于所有消费者
     * @var array|null
     */
    protected ?array $qos = [
        'prefetch_size' => 0,
        'prefetch_count' => 1, // 默认一次处理一条消息
        'global' => false,
    ];

    /**
     * 消费者是否启用
     * @var bool
     */
    protected bool $enable = true;

    /**
     * 最大消费消息数量，0表示不限制
     * @var int
     */
    protected int $maxConsumption = 0;

    /**
     * 等待消息的超时时间（秒），0表示一直等待
     * @var int|float
     */
    protected int|float $waitTimeout = 0;

    /**
     * 消费者实例的数量，或每次处理的消息数量（具体取决于实现）
     * @var int
     */
    protected int $nums = 1;

    /**
     * 实际的消息消费入口方法
     * 该方法会调用抽象的 consume 方法来处理业务逻辑
     *
     * @param mixed $data 已经解包的消息数据
     * @param AMQPMessage $message 原始的 AMQP 消息对象
     * @return Result 消费结果（如：ACK, NACK, REJECT）
     */
    public function consumeMessage(mixed $data, AMQPMessage $message, AMQPStreamConnection $connection): Result
    {
        return $this->consume($data);
    }

    /**
     * 抽象的消费方法，具体的消费逻辑需要子类实现
     * 默认返回 ACK 表示消息成功处理
     *
     * @param mixed $data 已经解包的消息数据
     * @return Result 消费结果
     */
    public function consume($data): Result
    {
        return Result::ACK; // 默认成功确认
    }

    /**
     * 设置队列名称
     * @param string $queue 队列名称
     * @return static
     */
    public function setQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * 获取队列名称
     * @return string 队列名称
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * 获取是否需要重新入队
     * @return bool
     */
    public function isRequeue(): bool
    {
        return $this->requeue;
    }

    /**
     * 消息处理失败时是否重试
     * @return bool
     */
    public function isRetry(): bool
    {
        return $this->retry;
    }

    /**
     * 获取重试次数
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * 获取 Qos 设置
     * @return array|null
     */
    public function getQos(): ?array
    {
        return $this->qos;
    }

    /**
     * 获取队列构建器实例
     * @return QueueBuilder
     */
    public function getQueueBuilder(): QueueBuilder
    {
        return (new QueueBuilder())->setQueue($this->getQueue());
    }

    /**
     * 对消息数据进行反序列化
     * @param string $data 待反序列化的字符串数据
     * @return mixed 反序列化后的数据
     */
    public function unserialize(string $data)
    {
        // 假设 `app()` 是一个获取容器实例或解析依赖的辅助函数
        return app(PhpSerializerPacker::class)->unpack($data);
    }

    /**
     * 获取消费者标签 (Consumer Tag)
     * 在 AMQP 中用于唯一标识一个消费者
     * @return string 消费者标签
     */
    public function getConsumerTag(): string
    {
        return $this->consumerTag ?: "consumer_{$this->getQueue()}_" . uniqid(); // 默认返回空字符串，可由子类重写生成唯一标签
    }

    /**
     * no_local: 不接收发布者自己发布的消息（通常设置为 false）
     * @return bool
     */
    public function isNoLocal(): bool
    {
        return $this->noLocal;
    }

    /**
     * no_ack: 启用手动消息确认（false 表示需要手动 ack/nack）
     * @return bool
     */
    public function isNoAck(): bool
    {
        return $this->noAck;
    }

    /**
     * exclusive: 独占队列（true 表示仅当前消费者可访问）
     * @return bool
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * nowait: 不等待服务器响应（true 表示不等待服务器响应）
     * @return bool
     */
    public function isNowait(): bool
    {
        return $this->nowait;
    }

    /**
     * read_write_timeout: 读写超时
     * @return int|null
     */
    public function readWriteTimeout(): int|null
    {
        return $this->readWriteTimeout;
    }

    /**
     * heartbeat: 心跳间隔
     * @return int|null
     */
    public function heartbeat(): int|null
    {
        return $this->heartbeat;
    }

    /**
     * arguments: 额外的参数
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * ticket: 队列的访问权限凭证
     * @return int|null
     */
    public function getTicket(): ?int
    {
        return $this->ticket;
    }

    /**
     * 检查消费者是否启用
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * 设置消费者启用状态
     * @param bool $enable
     * @return static
     */
    public function setEnable(bool $enable): static
    {
        $this->enable = $enable;

        return $this;
    }

    /**
     * 获取最大消费消息数量
     * @return int
     */
    public function getMaxConsumption(): int
    {
        return $this->maxConsumption;
    }

    /**
     * 设置最大消费消息数量
     * @param int $maxConsumption
     * @return static
     */
    public function setMaxConsumption(int $maxConsumption): static
    {
        $this->maxConsumption = $maxConsumption;

        return $this;
    }

    /**
     * 获取等待消息的超时时间
     * @return int|float
     */
    public function getWaitTimeout(): int|float
    {
        return $this->waitTimeout;
    }

    /**
     * 设置等待消息的超时时间
     * @param int|float $timeout
     * @return static
     */
    public function setWaitTimeout(int|float $timeout): static
    {
        $this->waitTimeout = $timeout;

        return $this;
    }

    /**
     * 获取消费者实例数量或批量处理数量
     * @return int
     */
    public function getNums(): int
    {
        return $this->nums;
    }

    /**
     * 设置消费者实例数量或批量处理数量
     * @param int $nums
     * @return static
     */
    public function setNums(int $nums): static
    {
        $this->nums = $nums;

        return $this;
    }

    /**
     * 设置 PSR 容器实例
     * @param ContainerInterface $container
     * @return static
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * 获取 PSR 容器实例
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }
}
