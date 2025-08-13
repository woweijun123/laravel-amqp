<?php

namespace Riven\Amqp\Commands;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Riven\Amqp\AmqpManager;
use Throwable;

class InitCommand extends Command
{
    protected $signature   = 'amqp:init';
    protected $description = '初始化RabbitMQ交换机和队列';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    #[NoReturn] public function handle(): void
    {
        try {
            $this->amqpManager->init();
            $this->info('AMQP 初始化交换机和队列 成功');
        } catch (Throwable $e) {
            $this->error('AMQP 初始化交换机和队列 失败：' . $e->getMessage());
        }
    }
}
