<?php

declare(strict_types=1);

namespace Riven\Amqp\Packer;


class PhpSerializerPacker implements PackerInterface
{
    public function pack($data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function unpack(string $data): mixed
    {
        return json_decode($data, true);
    }
}
