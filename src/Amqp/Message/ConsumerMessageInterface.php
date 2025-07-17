<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

use Riven\Amqp\Builder\QueueBuilder;
use Riven\Amqp\Result;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Container\ContainerInterface;

/**
 * 消费者消息接口
 * 定义了所有 AMQP 消费者消息类必须实现的方法，
 * 确保消费者行为的一致性。
 */
interface ConsumerMessageInterface extends MessageInterface
{
    /**
     * 核心消息消费方法，由实现类提供具体的业务逻辑。
     *
     * @param mixed $data 已经解包的消息数据
     * @param AMQPMessage $message 原始的 AMQP 消息对象
     * @return Result 消费结果（如：ACK, NACK, REJECT）
     */
    public function consumeMessage(mixed $data, AMQPMessage $message): Result;

    /**
     * 设置队列名称。
     *
     * @param string $queue 队列名称
     * @return static
     */
    public function setQueue(string $queue): static;

    /**
     * 获取队列名称。
     *
     * @return string 队列名称
     */
    public function getQueue(): string;

    /**
     * 判断消息处理失败时是否需要重新入队。
     *
     * @return bool
     */
    public function isRequeue(): bool;

    /**
     * 判断消息处理失败时是否允许重试。
     *
     * @return bool
     */
    public function isRetry(): bool;

    /**
     * 获取重试次数。
     * @return int
     */
    public function getRetryCount(): int;

    /**
     * 获取 Qos (Quality of Service) 设置。
     *
     * @return array|null
     */
    public function getQos(): ?array;

    /**
     * 获取队列构建器实例，用于声明或管理队列。
     *
     * @return QueueBuilder
     */
    public function getQueueBuilder(): QueueBuilder;

    /**
     * 获取消费者标签 (Consumer Tag)，用于唯一标识一个消费者。
     *
     * @return string 消费者标签
     */
    public function getConsumerTag(): string;

    /**
     * no_local: 不接收发布者自己发布的消息（通常设置为 false）
     * @return bool
     */
    public function isNoLocal(): bool;

    /**
     * no_ack: 启用手动消息确认（false 表示需要手动 ack/nack）
     * @return bool
     */
    public function isNoAck(): bool;

    /**
     * exclusive: 独占队列（true 表示仅当前消费者可访问）
     * @return bool
     */
    public function isExclusive(): bool;

    /**
     * nowait: 不等待服务器响应（true 表示不等待服务器响应）
     * @return bool
     */
    public function isNowait(): bool;

    /**
     * read_write_timeout: 读写超时
     * @return bool
     */
    public function readWriteTimeout(): int;

    /**
     * heartbeat: 心跳间隔
     * @return bool
     */
    public function heartbeat(): int;

    /**
     * arguments: 额外的参数
     * @return array
     */
    public function getArguments(): array;

    /**
     * ticket: 队列的访问权限凭证
     * @return int|null
     */
    public function getTicket(): ?int;

    /**
     * 判断消费者是否启用。
     *
     * @return bool
     */
    public function isEnable(): bool;

    /**
     * 设置消费者启用状态。
     *
     * @param bool $enable 启用状态
     * @return static
     */
    public function setEnable(bool $enable): static;

    /**
     * 获取消费者最大消费消息数量。
     *
     * @return int
     */
    public function getMaxConsumption(): int;

    /**
     * 设置消费者最大消费消息数量。
     *
     * @param int $maxConsumption 最大消费数量
     * @return static
     */
    public function setMaxConsumption(int $maxConsumption): static;

    /**
     * 获取等待消息的超时时间。
     *
     * @return int|float 超时时间（秒）
     */
    public function getWaitTimeout(): int|float;

    /**
     * 设置等待消息的超时时间。
     *
     * @param int|float $timeout 超时时间（秒）
     * @return static
     */
    public function setWaitTimeout(int|float $timeout): static;

    /**
     * 设置消费者实例数量或批量处理数量。
     *
     * @param int $nums 数量
     * @return static
     */
    public function setNums(int $nums): static;

    /**
     * 获取消费者实例数量或批量处理数量。
     *
     * @return int
     */
    public function getNums(): int;

    /**
     * 设置 PSR 容器实例，用于依赖注入。
     *
     * @param ContainerInterface $container 容器实例
     * @return static
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * 获取 PSR 容器实例。
     *
     * @return ContainerInterface|null 容器实例
     */
    public function getContainer(): ?ContainerInterface;
}
