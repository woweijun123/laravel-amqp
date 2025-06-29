<?php

declare(strict_types=1);

namespace Riven\Amqp\Message;

use Riven\Amqp\Builder\ExchangeBuilder;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * @method string getExchange()
 * @method string getType()
 * @property array $properties
 */
trait ProducerDelayedMessageTrait
{
    /**
     * Overwrite.
     */
    public function getExchangeBuilder(): ExchangeBuilder
    {
        return (new ExchangeBuilder())->setExchange($this->getExchange())
                                      ->setType('x-delayed-message')
                                      ->setArguments(new AMQPTable(['x-delayed-type' => $this->getType()]));
    }
}
