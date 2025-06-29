<?php

declare(strict_types=1);

namespace Riven\Amqp;

enum Result: string
{
    /*
     * Acknowledge the message.
     */
    case ACK = 'ack';

    /*
     * Unacknowledged the message.
     */
    case NACK = 'nack';

    /*
     * Reject the message and requeue it.
     */
    case REQUEUE = 'requeue';

    /*
     * Reject the message and drop it.
     */
    case DROP = 'drop';
}
