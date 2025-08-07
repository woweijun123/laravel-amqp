<?php

declare(strict_types=1);

namespace Riven\Amqp\Packer;

/**
 * 消息序列化接口
 */
interface PackerInterface
{
    /**
     * 序列化数据
     * @param $data
     * @return string
     */
    public function pack($data): string;

    /**
     * 反序列化数据
     * @param string $data
     * @return mixed
     */
    public function unpack(string $data): mixed;
}
