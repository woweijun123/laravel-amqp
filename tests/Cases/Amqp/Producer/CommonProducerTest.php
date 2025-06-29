<?php

namespace Test\Cases\Amqp\Producer;

use Riven\Amqp\Message\ProducerMessage;
use Test\Cases\TestCase;

class CommonProducerTest extends TestCase
{
    /**
     * @return void
     */
    public function testCommonProducer(): void
    {
        // 通过
        $data = ['id' => 50];
        ProducerMessage::send($data, confirm: true);

        // 通过
        $data = [
            'event' => 'customerServiceComplete', // 订单动作
            'id' => '14', // 申诉单ID
        ];
        // OrderActionProducer::send($data, routingKey: OrderAction::CustomerServiceComplete, confirm: true);
        // OrderActionProducer::send($data, routingKey: OrderAction::CustomerServiceComplete, confirm: true);
        $this->assertTrue(true);
    }
}
