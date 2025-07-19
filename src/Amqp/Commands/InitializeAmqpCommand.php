<?php

namespace Riven\Amqp\Commands;

use Illuminate\Console\Command;
use Riven\Amqp\AmqpManager;
use Throwable;

class InitializeAmqpCommand extends Command
{
    protected $signature = 'amqp:init';
    protected $description = '初始化RabbitMQ交换机和队列';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        try {
            $this->amqpManager->init();
            $this->info('RabbitMQ交换机和队列初始化成功');
        } catch (Throwable $e) {
            $this->error('RabbitMQ初始化失败：' . $e->getMessage());
        }
    }
}
