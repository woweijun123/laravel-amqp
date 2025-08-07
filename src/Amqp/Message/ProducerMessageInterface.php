<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

/**
 * 生产者消息接口。
 */
interface ProducerMessageInterface extends MessageInterface
{
    /**
     * @param $data
     * @return mixed
     */
    public function setPayload($data): mixed;

    /**
     * @return string
     */
    public function payload(): string;

    /**
     * @return array
     */
    public function getProperties(): array;

    /**
     * @param array $data
     * @param int $delayTime
     * @param bool $confirm
     * @param string $msgId
     * @param int $timeout
     * @return bool
     */
    public static function send(array $data, int $delayTime, bool $confirm = false, string $msgId = '', int $timeout = 5): bool;
}
