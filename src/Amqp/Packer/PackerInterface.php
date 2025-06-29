<?php

declare(strict_types=1);

namespace Riven\Amqp\Packer;

interface PackerInterface
{
    public function pack($data): string;

    public function unpack(string $data);
}
