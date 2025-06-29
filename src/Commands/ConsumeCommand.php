<?php

namespace Riven\Commands;

use Exception;
use Illuminate\Console\Command;
use Riven\Amqp\AmqpManager;

class ConsumeCommand extends Command
{
    protected $signature   = 'amqp:consume {queue}';
    protected $description = '从RabbitMQ队列中消费消息';

    public function __construct(private readonly AmqpManager $amqpManager)
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $this->info('Starting consumer...');
        $queue = $this->argument('queue');

        // 注册信号监听器
        pcntl_signal(SIGTERM, [$this, 'gracefulShutdown']);
        pcntl_signal(SIGINT, [$this, 'gracefulShutdown']);

        // 启动消费者
        $this->amqpManager->consume($queue);

        // 正常退出时手动调用 shutdown
        $this->amqpManager->shutdown();
    }

    /**
     * @return void
     */
    public function gracefulShutdown(): void
    {
        $this->info('Received signal, shutting down gracefully...');
        $this->amqpManager->shutdown();
        exit(0);
    }
}
