<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

interface ProducerMessageInterface extends MessageInterface
{
    public function setPayload($data);

    public function payload(): string;

    public function getProperties(): array;

    public static function send(array $data, int $delayTime, bool $confirm = false, string $msgId = '', int $timeout = 5): bool;
}
