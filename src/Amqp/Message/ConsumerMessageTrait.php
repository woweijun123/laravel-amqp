<?php

namespace Riven\Amqp\Message;

use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use Riven\Amqp\Invoke\CalleeEvent;
use Riven\Amqp\Invoke\Exception\InvokeException;
use Riven\Amqp\Invoke\Reflection;
use Riven\Amqp\Result;

trait ConsumerMessageTrait
{
    /**
     * @throws Exception
     */
    public function consumeMessage($data, AMQPMessage $message): Result
    {
        $calleeEvent = $this->calleeEvent();
        if ($calleeEvent && !empty($data['event'])) {
            $result = Reflection::call($calleeEvent::tryFrom($data['event']), compact('data', 'message'), default: true, scope: static::class);
            if (empty($result instanceof Result)) {
                $result = $result ? Result::ACK : Result::NACK;
            }
        } else {
            $result = $this->handle($data, $message);
        }

        return $result;
    }

    /**
     * @param mixed       $data
     * @param AMQPMessage $message
     * @return bool
     * @throws
     */
    public function handle(mixed $data, AMQPMessage $message): bool
    {
        throw new InvokeException('未重写 handle 方法');
    }

    /**
     * 返回注解调用事件类
     * @return CalleeEvent|string|null
     */
    protected function calleeEvent(): CalleeEvent|string|null
    {
        return null;
    }
}
