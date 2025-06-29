<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

use App\Enums\Amqp\AmqpAttr;
use Riven\Amqp\Builder\QueueBuilder;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @method string getQueue()
 */
trait ConsumerDeadMessageTrait
{
    /**
     * Overwrite.
     */
    public function getQueueBuilder(): QueueBuilder
    {
        return (new QueueBuilder())->setQueue($this->getQueue())
                                   ->setArguments(
                                       new AMQPTable([
                                           // 指定死信交换机
                                           'x-dead-letter-exchange'    => $this->getDeadLetterExchange(),
                                           // 指定死信路由键
                                           'x-dead-letter-routing-key' => $this->getDeadLetterRoutingKey(),
                                       ])
                                   );
    }

    /**
     * 获取死信交换机配置
     * @return string
     */
    protected function getDeadLetterExchange(): string
    {
        return AmqpAttr::DLQExchange->value;
    }

    /**
     * 获取死信路由键配置
     * @return string
     */
    protected function getDeadLetterRoutingKey(): string
    {
        return AmqpAttr::DLQRoutingKey->value;
    }
}
