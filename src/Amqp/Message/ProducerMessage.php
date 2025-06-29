<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

use Riven\Amqp\Amqp;
use Riven\Amqp\AmqpManager;
use Riven\Amqp\Exception\MessageException;
use Riven\Amqp\Packer\PhpSerializerPacker;
use Riven\Amqp\Result;
use Riven\Amqp\Invoke\CalleeEvent;
use App\Http\Middleware\RequestLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * 生产者消息抽象类。
 * 定义了 AMQP 生产者消息的基本属性和行为。
 */
abstract class ProducerMessage extends Message implements ProducerMessageInterface
{
    // 消息负载
    protected mixed $payload = '';

    // 路由键，支持字符串或数组
    protected array|string $routingKey = '';

    // 消息属性，默认持久化消息
    protected array $properties = [
        'content_type'  => 'text/plain',
        'delivery_mode' => Amqp::DELIVERY_MODE_PERSISTENT, // 持久化消息
    ];

    // 是否开启强制投递（mandatory），没路由的消息会返回给生产者
    protected bool  $mandatory  = false;
    // 是否开启即时投递（immediate），消息无法立即投递给消费者时会返回给生产者（已废弃，不推荐使用）
    protected bool  $immediate  = false;

    public function getMandatory(): bool
    {
        return $this->mandatory;
    }

    public function getImmediate(): bool
    {
        return $this->immediate;
    }

    public function setImmediate(bool $immediate): void
    {
        $this->immediate = $immediate;
    }

    public function setProperties(array $properties): void
    {
        $this->properties = array_merge($this->getProperties(), $properties);
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setPayload($data): self
    {
        $this->payload = $data;

        return $this;
    }

    /**
     * 获取序列化后的消息负载。
     */
    public function payload(): string
    {
        return $this->serialize();
    }

    /**
     * 序列化消息负载。
     */
    public function serialize(): string
    {
        return app(PhpSerializerPacker::class)->pack($this->payload);
    }

    /**
     * 当 Mandatory 消息未被路由并被 Broker 返回时触发。
     * 子类可重写此方法以自定义处理逻辑。
     */
    public function onMandatoryReturn(int $replyCode, string $replyText, string $exchange, string $routingKey, AMQPMessage $message): void
    {
        // 默认行为：记录警告日志
        Log::warning('Mandatory message was returned by broker', [
            'replyCode'  => $replyCode,
            'replyText'  => $replyText,
            'exchange'   => $exchange,
            'routingKey' => $routingKey,
            'payload'    => $message->getBody(),
            'msgId'      => Arr::get($this->getProperties(), 'message_id', ''),
            'properties' => $this->getProperties(),
            'class'      => static::class,
        ]);
    }

    /**
     * 发送消息到 AMQP 队列。
     *
     * @param array              $data 消息体数据。
     * @param int                $delayTime 延迟时间（秒），0表示不延迟。
     * @param bool               $confirm 是否需要Broker确认消息已接收。
     * @param string             $msgId 消息唯一ID，为空则自动生成。
     * @param int                $timeout 确认超时时间（秒）。
     * @param string|CalleeEvent $routingKey 消息路由键，为空则使用类默认值。
     * @return bool 消息发送是否成功。
     */
    public static function send(
        array $data,
        int $delayTime = 0,
        bool $confirm = false,
        string $msgId = '',
        int $timeout = 5,
        string|CalleeEvent $routingKey = ''
    ): bool
    {
        /* @var ProducerMessage $producerMessage */
        $producerMessage = app(static::class);

        // 设置消息负载
        $producerMessage->setPayload($data);

        // 设置消息ID（如果未提供则自动生成）
        $producerMessage->setProperties(['message_id' => $msgId ?: (RequestLogger::$xRequestId ?: Str::random())]);

        // 设置路由键, 如果未提供则使用类默认值
        if ($routingKey instanceof CalleeEvent) {
            $routingKey = method_exists(static::class, 'buildRoutingKey') ? $producerMessage->buildRoutingKey($routingKey) : '';
        }
        $producerMessage->setRoutingKey($routingKey ?: $producerMessage->getRoutingKey());

        // 如果设置了延迟时间，添加 'x-delay' 头部
        if ($delayTime) {
            $producerMessage->setProperties(['application_headers' => new AMQPTable(['x-delay' => $delayTime * 1000])]);
        }

        try {
             /* @var AmqpManager $amqpManager 通过 AmqpManager 发送消息 */
            $amqpManager    = app(AmqpManager::class);
            $result = $amqpManager->produce($producerMessage, $confirm, $timeout);

            // 记录生产者发送日志
            Log::info('producer success ', [
                'exchange'   => $producerMessage->getExchange(),
                'routingKey' => $producerMessage->getRoutingKey(),
                'msgId'      => Arr::get($producerMessage->getProperties(), 'message_id', ''),
                'payload'    => $data,
                'properties' => $producerMessage->getProperties(),
                'class'      => get_class($producerMessage),
                'confirm'    => $confirm,
                'delayTime'  => $delayTime,
                'timeout'    => $timeout,
                'result'     => $result ? Result::ACK : Result::NACK, // 标记发送结果
            ]);
            return true;
        } catch (MessageException|Throwable $e) {
            // 记录发送错误日志
            Log::error('producer error ' . $e->getMessage(), [
                'exchange'   => $producerMessage->getExchange(),
                'routingKey' => $producerMessage->getRoutingKey(),
                'msgId'      => Arr::get($producerMessage->getProperties(), 'message_id', ''),
                'payload'    => $data,
                'properties' => $producerMessage->getProperties(),
                'class'      => get_class($producerMessage),
                'confirm'    => $confirm,
                'delayTime'  => $delayTime,
                'timeout'    => $timeout,
            ]);
            return false;
        }
    }
}
